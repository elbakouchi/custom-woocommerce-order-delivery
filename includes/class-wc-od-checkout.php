<?php
/**
 * Class to handle the plugin behaviour in the checkout page
 *
 * @package WC_OD
 * @since   1.0.0
 */

use function YoastSEO_Vendor\GuzzleHttp\debug_resource;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_OD_Checkout' ) ) {

	/**
	 * Class WC_OD_Checkout
	 */
	class WC_OD_Checkout extends WC_OD_Singleton {

		/**
		 * The first allowed date for ship an order.
		 *
		 * Calculate this data is a heavy process, so we defined this property
		 * to store the value and execute the process just one time per request.
		 *
		 * @since 1.0.0
		 *
		 * @var int A timestamp representing the first allowed date to ship an order.
		 */
		private $first_shipping_date;

		/**
		 * The first allowed date for deliver an order.
		 *
		 * Calculate this data is a heavy process, so we defined this property
		 * to store the value and execute the process just one time per request.
		 *
		 * @since 1.0.0
		 *
		 * @var int A timestamp representing the first allowed date to deliver an order.
		 */
		private $first_delivery_date;

		private $cart_products_timeslots = [];

		private $prefix = '_del_date_'; 
		
		private $days_names = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];


		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 */
		protected function __construct() {
			parent::__construct();

			// WP Hooks.
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'wp_footer', array( $this, 'print_calendar_settings' ) );

			// WooCommerce hooks.
			add_filter( 'woocommerce_checkout_fields', array( $this, 'checkout_fields' ) );
			add_filter( 'woocommerce_checkout_get_value', array( $this, 'checkout_get_value' ), 10, 2 );
			add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'checkout_fragments' ) );
			add_action( 'woocommerce_checkout_shipping', array( $this, 'checkout_content' ), 99 );
			add_action( 'woocommerce_after_checkout_validation', array( $this, 'checkout_validation' ) );
			add_action( 'woocommerce_checkout_create_order', array( $this, 'update_order_meta' ) );

		}


		private function get_current_product_timeslots($product_id, $day = null){
			$timeslots = array();
			if(is_null($day)){		
				foreach($this->days_names as $_day){
					$entry = '_del_date_dd_'.$_day.'_timeslots';
					$_ = get_post_meta($product_id,$entry, true);
					if (is_array($_)){
						$timeslots[array_keys($this->days_names, $_day)[0]] = $_;
					}
				}
				$entry = '_del_date_Override_WOD_dates' . $product_id;
				if(!boolval( get_post_meta($product_id,$entry, true))){
				  		
				}
			}else{
				$entry = '_del_date_dd_'.$day.'_timeslots';
				$_ = get_post_meta($product_id,$entry, true);
				$timeslots[array_keys($this->days_names, $day)[0]] = $_;
			}
			return $timeslots;
		}

		/**
		 * Gets the chosen shipping method for the specified package.
		 *
		 * @since 1.5.0
		 *
		 * @param int $package The shipping package index.
		 * @return string|false The shipping method ID. False otherwise.
		 */
		public function get_shipping_method( $package = 0 ) {
			$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );

			return ( ! empty( $chosen_methods ) && isset( $chosen_methods[ $package ] ) ? $chosen_methods[ $package ] : false );
		}

		/**
		 * Gets if the selected shipping method is local pickup or not.
		 *
		 * @since 1.4.0
		 *
		 * @return bool
		 */
		public function is_local_pickup() {
			return ( 0 === strpos( (string) $this->get_shipping_method(), 'local_pickup' ) );
		}

		/**
		 * Gets if it's necessary to display the 'Shipping & Delivery' information on the checkout page.
		 *
		 * @since 1.4.0
		 *
		 * @global WC_Ship_Multiple $wcms The 'Ship to Multiple Addresses' plugin instance.
		 *
		 * @return bool
		 */
		public function needs_details() {
			global $wcms;

			$needs_details = ( is_checkout() && WC()->cart->needs_shipping() &&
				( ! $this->is_local_pickup() || wc_string_to_bool( WC_OD()->settings()->get_setting( 'enable_local_pickup' ) ) ) &&
				( ! $wcms || ! $wcms->cart->cart_has_multi_shipping() )
			);

			/**
			 * Filter if it's necessary to display the 'Shipping & Delivery' information on the checkout page.
			 *
			 * @since 1.4.0
			 *
			 * @param bool $needs_details
			 */
			return apply_filters( 'wc_od_checkout_needs_details', $needs_details );
		}

		/**
		 * Gets if it's necessary to display a date field on the checkout page.
		 *
		 * @since 1.4.0
		 *
		 * @return bool
		 */
		public function needs_date() {
			$needs_date = (
				$this->needs_details() &&
				'calendar' === WC_OD()->settings()->get_setting( 'checkout_delivery_option' )
			);

			/**
			 * Filters if it's necessary to display a date field on the checkout page.
			 *
			 * @since 1.4.0
			 *
			 * @param bool $needs_date
			 */
			return apply_filters( 'wc_od_checkout_needs_date', $needs_date );
		}

		/**
		 * Enqueue scripts.
		 *
		 * @since 1.0.0
		 */
		public function enqueue_scripts() {
			// Don't use the 'needs_date' method. The conditions may vary on refreshing the fragments.
			if ( ! is_checkout() || ! WC()->cart->needs_shipping() ) {
				return;
			}

			$suffix = wc_od_get_scripts_suffix();

			wc_od_enqueue_datepicker( 'checkout' );
			wp_enqueue_script( 'wc-od-checkout', WC_OD_URL . "assets/js/wc-od-checkout{$suffix}.js", array( 'jquery', 'wc-od-datepicker' ), WC_OD_VERSION, true );
		}

		/**
		 * Gets the delivery date field arguments.
		 *
		 * @since 1.0.1
		 * @since 1.5.0 Added `$args` parameter.
		 *
		 * @param array $args Optional. The arguments to overwrite.
		 * @return array An array with the delivery date field arguments.
		 */
		public function get_delivery_date_field_args( $args = array() ) {
			// Use the first delivery date as placeholder.
			if ( 'auto' === WC_OD()->settings()->get_setting( 'delivery_fields_option' ) ) {
				$delivery_date = $this->get_first_delivery_date();

				if ( $delivery_date ) {
					$args['placeholder'] = wc_od_localize_date( $delivery_date );
				}
			}
			//print_r($args);
			return $this->custom_wc_od_get_delivery_date_field_args( $args, 'checkout' );
		}

		/**
		 * Gets the delivery date field arguments.
		 *
		 * @since 1.1.0
		 * @since 1.5.0 Updated default values for the `return` and `label` parameters.
		 *
		 * @param array  $args    Optional. The arguments to overwrite.
		 * @param string $context Optional. The context in which the form field is used.
		 *
		 * @return array An array with the delivery date field arguments.
		 */
		function custom_wc_od_get_delivery_date_field_args( $args = array(), $context = '' ) {
			$defaults = array(
				'type'              => 'delivery_date',
				'label'             => _x( 'Delivery Date', 'field label', 'woocommerce-order-delivery' ),
				'placeholder'       => '',
				'class'             => array( 'form-row-wide' ),
				'required'          => ( 'required' === WC_OD()->settings()->get_setting( 'delivery_fields_option' ) ),
				'return'            => false,
				'value'             => '',
				'custom_attributes' => array(
					'readonly' => 'true',
				),
			);

			// Add priority to allow sorting the field.
			if ( 'checkout' === $context ) {
				$defaults['priority'] = 10;
			}

			/**
			 * Filters the arguments for the delivery date field.
			 *
			 * @since 1.0.0
			 * @since 1.1.0 Added `$context` parameter.
			 *
			 * @param array  $args    The arguments for the delivery date field.
			 * @param string $context The context in which the form field is used.
			 */
			$_ = WC_OD()->settings()->get_setting( 'delivery_fields_option' );
			// print_r($_);
			// echo 'Context:<br>';
			// print_r($context);
			// echo 'Args:<br>';
			// print_r($args);
			// echo 'Defaults:<br>';
			// print_r($defaults);
			return apply_filters( 'custom_wc_od_delivery_date_field_args', wp_parse_args( $args, $defaults ), $context );
		}

		public function get_product_timeslots_dayofweek($dayofweek, $index=0){
			$result = array();

			foreach($this->cart_products_timeslots as $product_id=>$timeslots){
				foreach($timeslots as $day=>$timeslot){
					//echo 'looped timeslots';print_r($timeslot);
					if($day == $dayofweek){
						foreach($timeslot as $key=>$value){
							$index += $key;
							$result['time-frame:'.$index] = $value;
						}
					}
				}
			}
			return $result;
		} 


		/**
		 * Register the delivery fields in the checkout form.
		 *
		 * @since 1.5.0
		 *
		 * @param array $fields The checkout fields.
		 * @return array
		 */
		public function checkout_fields( $fields ) {
			if ( ! $this->needs_date() ) {
				return $fields;
			}

			$delivery_date = $this->checkout_get_value( null, 'delivery_date' );
		//	print_r($delivery_date);
		    // error_log(__CLASS__ . ' ' . __METHOD__ .' delivery_date: ' .print_r($delivery_date, true));
			$fields['delivery'] = array(
				'delivery_date' => $this->get_delivery_date_field_args(),
			);
			//error_log(__CLASS__ . ' ' . __METHOD__ .' fields: ' .print_r($fields, true));
			if ( $delivery_date ) {
				add_filter( 'wc_od_get_time_frames_for_date', array( $this, 'filter_unavailable_time_frames' ), 10, 2 );
				
				$dayofweek = date('w', strtotime($delivery_date));
				// echo 'day of week', $dayofweek, '<br>';
				// print_r($product_timeslots);
				// echo '<br>';
				
				$choices = wc_od_get_time_frames_choices_for_date(
					$delivery_date,
					array(
						'shipping_method' => $this->get_shipping_method(),
					),
					'checkout'
				);
				//$custom_choices = 
				$product_timeslots = $this->get_product_timeslots_dayofweek($dayofweek, count($choices));
				//error_log(__CLASS__ . ' ' . __METHOD__ .' custom choices: ' .print_r($product_timeslots, true));
				$choices = array_merge($choices, $product_timeslots);
				// echo 'Choices:';
				// print_r($choices);
				// echo '<br>';
				
				if ( ! empty( $choices ) ) {
					$fields['delivery']['delivery_time_frame'] = array(
						'label'    => _x( 'Time frame', 'checkout field label', 'woocommerce-order-delivery' ),
						'type'     => 'select',
						'class'    => array( 'form-row-wide' ),
						'required' => true,// ( 'required' === WC_OD()->settings()->get_setting( 'delivery_fields_option' ) ),
						'options'  => $choices,
						'priority' => 20,
					);
					//debug_print_r(__FILE__, __CLASS__  , __METHOD__ ,' choices and custom choices ' ,$choices, __LINE__);
				}
			}
			
			return $fields;
		}

		/**
		 * Filter a checkout field value from the posted data.
		 *
		 * Load the delivery fields value on refreshing the checkout fragments.
		 *
		 * @since 1.4.0
		 *
		 * @param mixed  $value The field value.
		 * @param string $input The input key.
		 *
		 * @return mixed
		 */
		public function checkout_get_value( $value, $input ) {
			// We cannot use the method 'WC_Checkout->get_checkout_fields()' due to nested calls.
			if ( 0 === strpos( $input, 'delivery_' ) ) {
				$value = wc_od_get_posted_data( $input );
			}

			return $value;
		}

		/**
		 * Register the checkout fragments.
		 *
		 * Allow refresh the content when the checkout form changes.
		 *
		 * @since 1.4.0
		 *
		 * @param array $fragments The fragments to update in the checkout form.
		 * @return mixed An array with the checkout fragments.
		 */
		public function checkout_fragments( $fragments ) {
			//print_r($fragments);
			ob_start();
			$this->checkout_content();
			$fragments['#wc-od'] = ob_get_clean();

			$fragments = $this->custom_add_calendar_settings_fragment( $fragments );
			//error_log(__METHOD__.': '.print_r($fragments, true));
			return $fragments;
		}

		/**
		 * Adds the custom content to the checkout form.
		 *
		 * @since 1.0.0
		 */
		public function checkout_content() {
			if ( ! $this->needs_details() ) {
				// Only prints the container to allow the fragment refresh.
				echo '<div id="wc-od"></div>';
				return;
			}

			$checkout = WC()->checkout();

			// Backward compatibility.
			$delivery_date_field_args = $this->get_delivery_date_field_args(
				array(
					'return' => true,
				)
			);

			$cart_data = WC()->session->get('cart');
			
			$products = [];
			
			foreach($cart_data as $key=>$value){foreach($value as $k=>$v){if($k=='product_id'){$products[]=$v;}}}
			
			$products = array_unique($products);
			$overrides = [];
			
			foreach($products as $post_id){
				//if(count($this->cart_products_timeslots) != count($products)){
					$this->cart_products_timeslots[$post_id] = $this->get_current_product_timeslots($post_id);
				//}
				$pm = get_post_meta($post_id, $this->prefix . 'Override_WOD_dates' . $post_id, true);
				if ( empty( $pm ) ) $pm = '0';
				if( boolval( $pm ) ){
					error_log  ( $pm . ": " . $post_id );
				}else{
					
				}
			}

			$shipping_method = $this->get_shipping_method();
			$range           = WC_OD_Delivery_Ranges::get_range_matching_shipping_method( $shipping_method );
				
			//	print_r($this->cart_products_timeslots);
			/**
			 * Filter the arguments used by the checkout/form-delivery-date.php template.
			 *
			 * @since 1.1.0
			 * @since 1.5.0 The parameter `delivery_date_field` is deprecated.
			 *
			 * @param array $args The arguments.
			 */
			$args = apply_filters(
				'wc_od_checkout_delivery_details_args',
				array(
					'title'               => __( 'Shipping and delivery', 'woocommerce-order-delivery' ),
					'checkout'            => $checkout,
					'delivery_date_field' => woocommerce_form_field( 'delivery_date', $delivery_date_field_args, $checkout->get_value( 'delivery_date' ) ),
					'delivery_option'     => WC_OD()->settings()->get_setting( 'checkout_delivery_option' ),
					'shipping_date'       => wc_od_localize_date( $this->get_first_shipping_date() ),
					'delivery_range'      => array(
						'min' => (isset($range) && $range)?$range->get_from():1,
						'max' => (isset($range) && $range)?$range->get_to():1,
					),
				)
			);
			wc_od_get_template( 'checkout/form-delivery-date.php', $args );
		}
		/**
		 * Gets the arguments used to calculate the delivery date.
		 *
		 * @since 1.1.0
		 *
		 * @return array An array with the arguments.
		 */
		public function get_delivery_date_args() {
			$today           = wc_od_get_local_date();
			$start_timestamp = strtotime( $this->min_delivery_days() . ' days', $today );
			$end_timestamp   = strtotime( ( $this->max_delivery_days() + 1 ) . ' days', $today ); // Non-inclusive.
			$delivery_days   = WC_OD()->settings()->get_setting( 'delivery_days' );
			$disabled_dates  = wc_od_get_disabled_days(
				array(
					'type'    => 'delivery',
					'start'   => date( 'Y-m-d', $start_timestamp ),
					'end'     => date( 'Y-m-d', $end_timestamp ),
					'country' => WC()->customer->get_shipping_country(),
					'state'   => WC()->customer->get_shipping_state(),
				)
			);

			$disabled_dates = array_merge(
				$disabled_dates,
				WC_OD_Delivery_Dates::get_disabled_dates(
					array(
						'start_date'    => $start_timestamp,
						'end_date'      => $end_timestamp,
						'delivery_days' => $delivery_days,
						'disabled_days' => $disabled_dates,
					),
					'Y-m-d'
				)
			);

			/**
			 * Filter the arguments used to calculate the delivery date.
			 *
			 * @since 1.1.0
			 * @since 1.5.0 Added `shipping_method` parameter.
			 *
			 * @param array $args The arguments.
			 */
			return apply_filters(
				'wc_od_delivery_date_args',
				array(
					'shipping_method' => $this->get_shipping_method(),
					'start_date'      => $start_timestamp,
					'end_date'        => $end_timestamp,
					'delivery_days'   => $delivery_days,
					'disabled_days'   => $disabled_dates,
				)
			);
		}
		/**
		 * Gets the status (enabled or disabled) of the delivery days for the specified arguments.
		 *
		 * @since 1.5.0
		 * @since 1.6.0 The parameter `$delivery_days` also accepts a WC_OD_Collection_Delivery_Days object.
		 *
		 * @param mixed  $delivery_days Optional. WC_OD_Collection_Delivery_Days object or an array with the delivery days data.
		 * @param array  $args          Optional. The arguments.
		 * @param string $context       Optional. The context.
		 * @return array
		 */
		function custom_wc_od_get_delivery_days_status( $delivery_days = array(), $args = array(), $context = '' ) {
			$gotten_delivery_days = wc_od_get_delivery_days( $delivery_days );
			$statuses      = array();

			foreach ( $gotten_delivery_days as $index => $delivery_day ) {
				$statuses[ $index ] = wc_od_get_delivery_day_status( $delivery_day, $args, $context );
			}

			/**
			 * Filters the status of the delivery days.
			 *
			 * @since 1.5.0
			 * @since 1.6.0 The `$delivery_days` parameter is a WC_OD_Collection_Delivery_Days object.
			 *
			 * @param array                          $statuses      An array with the status of each delivery day.
			 * @param WC_OD_Collection_Delivery_Days $delivery_days The delivery days.
			 * @param array                          $args          The arguments.
			 * @param string                         $args          The context.
			 */
			return apply_filters( 'custom_wc_od_get_delivery_days_status', $statuses, $delivery_days, $args, $context );
		}
		/**
		 * 
		 */
		public function convert_timslots($days_timeslots, $weekday){
			$timeslots_array = [];
			$timeslots_woodd_objs = [];
			if($days_timeslots){
				//print_r($days_timeslots);
				foreach($days_timeslots as $timeslots){
					//print_r($timeslots);
					[$start, $end] = explode(' - ', $timeslots); 
					$start = explode(' ', $start)[0];
					$end   = explode(' ', $end)[0];
					
					$timeslot_obj = array(
						'enabled'          => 'yes',
						'number_of_orders' => 0,
						'time_frames'      => [array(
							'title' => 'Day of week '.$weekday,
							'time_from' => $start,
							'time_to' => $end,
							'number_of_orders' => 0,
						//	'shipping_methods_option' => '',
						//	'shipping_methods' => array()
						)],
					);
					//print_r($timeslot_obj);
					$timeslots_array[]      = $timeslot_obj;
					$dump                   = new WC_OD_Delivery_Day($timeslot_obj, $weekday);
					$timeslots_woodd_objs[] = $dump;

				}	
				return [$timeslots_array, $timeslots_woodd_objs];
			}
			return [false,false];
		}

		/**
		 * 
		 */
		public function get_ride_of_rudandancy($raw_timeslots_array, $timeslots_objs_array, $entry, $day_index){

			$customs = get_option( $entry  );
			
			[$timeslots_array, $timeslots_woodd_obj] = $this->convert_timslots($customs , $day_index);
			if(is_array($timeslots_array)) {
				$raw_timeslots_array = array_merge($raw_timeslots_array, $timeslots_array);
				$timeslots_objs_array = array_merge($timeslots_objs_array, $timeslots_woodd_obj);
			}
			
			return [$raw_timeslots_array, $timeslots_objs_array];
		}

		/**
		 * Gets the day that have timeslots from options.
		 *
		 * @since 1.1.0
		 *
		 * @return array An array with the calendar settings.
		 */
		public function get_days_of_week_having_timeslots(){
			$timeslots_array = [];
			$timeslots_woodd_objs = [];
			[ $timeslots_array, $timeslots_woodd_objs ] = $this->get_ride_of_rudandancy( $timeslots_array, $timeslots_woodd_objs, 'dd_sunday_timeslots'   , 0);
			[ $timeslots_array, $timeslots_woodd_objs ] = $this->get_ride_of_rudandancy( $timeslots_array, $timeslots_woodd_objs, 'dd_monday_timeslots'   , 1);
			[ $timeslots_array, $timeslots_woodd_objs ] = $this->get_ride_of_rudandancy( $timeslots_array, $timeslots_woodd_objs, 'dd_tuesday_timeslots'  , 2);
			[ $timeslots_array, $timeslots_woodd_objs ] = $this->get_ride_of_rudandancy( $timeslots_array, $timeslots_woodd_objs, 'dd_wednesday_timeslots', 3);
			[ $timeslots_array, $timeslots_woodd_objs ] = $this->get_ride_of_rudandancy( $timeslots_array, $timeslots_woodd_objs, 'dd_thursday_timeslots' , 4);
			[ $timeslots_array, $timeslots_woodd_objs ] = $this->get_ride_of_rudandancy( $timeslots_array, $timeslots_woodd_objs, 'dd_friday_timeslots'   , 5);
			[ $timeslots_array, $timeslots_woodd_objs ] = $this->get_ride_of_rudandancy( $timeslots_array, $timeslots_woodd_objs, 'dd_saturday_timeslots' , 6);
			
			return [$timeslots_array, new WC_OD_Collection_Delivery_Days( $timeslots_woodd_objs )];
			 
		}

		public function merge_days_timeslots($native_delivery_timeslots, $custom_delivery_timeslots){
			$dump = [];
			foreach($native_delivery_timeslots as $index=>$n){
				//echo $index,'<br>';
				//print_r($n);
				$n['enabled'] = $custom_delivery_timeslots[$index]['enabled'];
				$n['time_frames'] = $custom_delivery_timeslots[$index]['time_frames'];
				$dump[$index] = $n;
				//echo $index,'<br>';
			}
			return $dump;
		}
		/**
		 * Gets the calendar settings.
		 *
		 * @since 1.1.0
		 *
		 * @return array An array with the calendar settings.
		 */
		public function get_calendar_settings() {
			$date_format = wc_od_get_date_format( 'php' );
			$args        = $this->get_delivery_date_args();
		
			[$days_timeslots, $days_timeslots_collection_obj]  = $this->get_days_of_week_having_timeslots();
			
			$_ = $this->merge_days_timeslots($args['delivery_days'], $days_timeslots);
		
			$delivery_days_status = $this->custom_wc_od_get_delivery_days_status(
				$_,
				array(
					'shipping_method' => $args['shipping_method'],
				),
				'checkout'
			);

			
			$daysOfWeekDisabled = array_keys($delivery_days_status, 'no', true);

			return wc_od_get_calendar_settings(
				array(
					'startDate'          => wc_od_localize_date( $args['start_date'], $date_format ),
					'endDate'            => wc_od_localize_date( ( wc_od_get_timestamp( $args['end_date'] ) - DAY_IN_SECONDS ), $date_format ), // Inclusive.
					'daysOfWeekDisabled' => $daysOfWeekDisabled, 
					'datesDisabled'      => array_map( 'wc_od_localize_date', $args['disabled_days'] ),
				),
				'checkout'
			);
		}

		/**
		 * Prints the script with the calendar settings.
		 *
		 * NOTE: This script is equivalent to use the wp_localize_script(), but adding an id attribute to the script tag.
		 * This id is necessary to identify the script to refresh on the update_order_review_fragments action.
		 *
		 * @since 1.1.0
		 */
		public function print_calendar_settings() {
			$settings = ( $this->needs_date() ? $this->get_calendar_settings() : array() );
			?>
			<script id="wc_od_checkout_l10n" type="text/javascript">
				/* <![CDATA[ */
				
				var wc_od_checkout_l10n = <?php echo wp_json_encode( $settings ); ?>;
				/* ]]> */
			</script>
			<?php
		}

		/**
		 * Adds the calendar settings fragment.
		 *
		 * NOTE: Allow refresh the calendar settings when the checkout form change.
		 *
		 * @since 1.1.0
		 *
		 * @param array $fragments The fragments to update in the checkout form.
		 * @return mixed An array with the checkout fragments.
		 */
		public function custom_add_calendar_settings_fragment( $fragments ) {
			ob_start();
			$this->print_calendar_settings();
			$fragments['#wc_od_checkout_l10n'] = ob_get_clean();
			//error_log(__METHOD__. ': ' .print_r($fragments, true));
			return $fragments;
		}

		/**
		 * Validates the delivery fields on the checkout process.
		 *
		 * TODO: Use the $data and $errors parameters when the minimum WC version is 3.0+.
		 *
		 * @since 1.5.0
		 */
		public function checkout_validation() {
			if ( ! $this->needs_date() ) {
				return;
			}

			$checkout = WC()->checkout();
			$fields   = $checkout->get_checkout_fields( 'delivery' );
			print_r($fields);

			foreach ( $fields as $field_id => $field ) {
				/**
				 * Filters the callback used to validate the checkout field.
				 *
				 * @since 1.5.0
				 *
				 * @param mixed $callback The validation callback.
				 * @param array $field    The field data.
				 */
				$callback = apply_filters( "wc_od_checkout_{$field_id}_validation_callback", array( $this, 'validate_' . $field_id ), $field );

				if ( is_callable( $callback ) ) {
					call_user_func( $callback, $checkout->get_value( $field_id ), $field );
				}
			}
		}

		/**
		 * Validates the delivery date field.
		 *
		 * @since 1.0.0
		 * @since 1.5.0 Added `$value` and `$field` parameters.
		 *
		 * @param mixed $value The field value.
		 * @param array $field The field data.
		 */
		public function validate_delivery_date( $value, $field ) {
			// Validation: Invalid date.
			if ( $value && ! wc_od_validate_delivery_date( $value, $this->get_delivery_date_args(), 'checkout' ) ) {
				/* translators: %s: field name */
				wc_add_notice( sprintf( __( '%s is not valid.', 'woocommerce-order-delivery' ), '<strong>' . esc_html( $field['label'] ) . '</strong>' ), 'error' );
			}
		}

		/**
		 * Validates the delivery time frame field.
		 *
		 * @since 1.5.0
		 *
		 * @param mixed $value The field value.
		 * @param array $field The field data.
		 */
		public function validate_delivery_time_frame( $value, $field ) {
			//return $value;
			if ( $value && ! in_array( $value, array_keys( $field['options'] ), true ) ) {
				/* translators: %s: field label */
				wc_add_notice( sprintf( __( '%s is not valid.', 'woocommerce-order-delivery' ), '<strong>' . esc_html( $field['label'] ) . '</strong>' ), 'error' );
			}
		}

		/**
		 * Validates if the day of the week is enabled for the delivery.
		 *
		 * @since 1.0.0
		 * @deprecated 1.1.0 This validation is done in the 'validate_delivery_date' method.
		 *
		 * @param boolean $valid Is valid the delivery date?.
		 * @return boolean True if the delivery date is valid. False otherwise.
		 */
		public function validate_delivery_day( $valid ) {
			wc_deprecated_function( __METHOD__, '1.1.0' );

			return $valid;
		}

		/**
		 * Validates if the minimum days for the delivery is satisfied.
		 *
		 * @since 1.0.0
		 * @deprecated 1.1.0 This validation is done in the 'validate_delivery_date' method.
		 *
		 * @param boolean $valid Is valid the delivery date?.
		 * @return boolean True if the delivery date is valid. False otherwise.
		 */
		public function validate_minimum_days( $valid ) {
			wc_deprecated_function( __METHOD__, '1.1.0' );

			return $valid;
		}

		/**
		 * Validates if the maximum days for the delivery is satisfied.
		 *
		 * @since 1.0.0
		 * @deprecated 1.1.0 This validation is done in the 'validate_delivery_date' method.
		 *
		 * @param boolean $valid Is valid the delivery date?.
		 * @return boolean True if the delivery date is valid. False otherwise.
		 */
		public function validate_maximum_days( $valid ) {
			wc_deprecated_function( __METHOD__, '1.1.0' );

			return $valid;
		}

		/**
		 * Validates that not exists events for the delivery date.
		 *
		 * @since 1.0.0
		 * @deprecated 1.1.0 This validation is done in the 'validate_delivery_date' method.
		 *
		 * @param boolean $valid Is valid the delivery date?.
		 * @return boolean True if the delivery date is valid. False otherwise.
		 */
		public function validate_no_events( $valid ) {
			wc_deprecated_function( __METHOD__, '1.1.0' );

			return $valid;
		}

		/**
		 * Gets the delivery date to save with the order.
		 *
		 * @since 1.1.0
		 *
		 * @return string|false The delivery date string. False otherwise.
		 */
		public function get_order_delivery_date() {
			$checkout      = WC()->checkout();
			$delivery_date = $checkout->get_value( 'delivery_date' );

			// Assigns a delivery date automatically.
			if ( ! $delivery_date && 'auto' === WC_OD()->settings()->get_setting( 'delivery_fields_option' ) ) {
				$delivery_date = $this->get_first_delivery_date( 'checkout-auto' );
			}

			if ( $delivery_date ) {
				// Stores the date in the ISO 8601 format.
				$delivery_date = wc_od_localize_date( $delivery_date, 'Y-m-d' );
			}

			return ( $delivery_date ? $delivery_date : false );
		}

		/**
		 * Gets the shipping date to save with the order.
		 *
		 * @since 1.1.0
		 *
		 * @param string|int $delivery_date Optional. The order delivery date.
		 * @return string|false The shipping date string. False otherwise.
		 */
		public function get_order_shipping_date( $delivery_date = null ) {
			if ( $delivery_date ) {
				// Assigns a shipping date from the delivery date.
				$shipping_date = wc_od_get_last_shipping_date(
					array(
						'shipping_method'             => $this->get_shipping_method(),
						'delivery_date'               => $delivery_date,
						'disabled_delivery_days_args' => array(
							'type'    => 'delivery',
							'country' => WC()->customer->get_shipping_country(),
							'state'   => WC()->customer->get_shipping_state(),
						),
					),
					'checkout-auto'
				);
			} else {
				// The shipping date posted by the customer.
				$checkout      = WC()->checkout();
				$shipping_date = $checkout->get_value( 'shipping_date' );
			}

			if ( $shipping_date ) {
				// Stores the date in the ISO 8601 format.
				$shipping_date = wc_od_localize_date( $shipping_date, 'Y-m-d' );
			}

			return ( $shipping_date ? $shipping_date : false );
		}

		/**
		 * Gets the time frame to save with the order.
		 *
		 * @since 1.5.0
		 *
		 * @param string|int $delivery_date The order delivery date.
		 * @return array|false An array with the time frame data. False otherwise.
		 */
		public function get_order_time_frame( $delivery_date ) {
			$checkout      = WC()->checkout();
			$time_frame_id = $checkout->get_value( 'delivery_time_frame' );
			$time_frame    = null;

			if ( $time_frame_id ) {
				$time_frame = wc_od_get_time_frame_for_date( $delivery_date, $time_frame_id );
			} elseif ( 'auto' === WC_OD()->settings()->get_setting( 'delivery_fields_option' ) ) {
				$time_frames = wc_od_get_time_frames_for_date(
					$delivery_date,
					array(
						'shipping_method' => $this->get_shipping_method(),
					),
					'checkout-auto'
				);

				$time_frame = $time_frames->first();
			}

			return ( $time_frame ? wc_od_time_frame_to_order( $time_frame ) : false );
		}

		/**
		 * Saves the order delivery date during checkout.
		 *
		 * @since 1.0.0
		 * @since 1.1.0 Accepts a WC_Order as parameter.
		 * @since 1.7.0 Doesn't accept an Order ID as parameter.
		 *
		 * @param WC_Order $order Order object.
		 */
		public function update_order_meta( $order ) {
			if ( ! $order instanceof WC_Order ) {
				wc_doing_it_wrong( __FUNCTION__, 'Expected a WC_Order object.', '1.7.0' );
				return;
			}

			if ( ! $this->needs_date() ) {
				return;
			}

			$delivery_date = $this->get_order_delivery_date();
			$shipping_date = $this->get_order_shipping_date( $delivery_date );

			if ( $delivery_date ) {
				$order->update_meta_data( '_delivery_date', $delivery_date );

				$time_frame = $this->get_order_time_frame( $delivery_date );

				if ( $time_frame ) {
					$order->update_meta_data( '_delivery_time_frame', $time_frame );
				}
			}

			if ( $shipping_date ) {
				$order->update_meta_data( '_shipping_date', $shipping_date );
			}
		}

		/**
		 * Gets the first day to ship the orders.
		 *
		 * @since 1.0.0
		 * @since 1.5.5 Added `$context` parameter.
		 *
		 * @param string $context Optional. The context.
		 * @return int A timestamp representing the first allowed date to ship the orders.
		 */
		public function get_first_shipping_date( $context = 'checkout' ) {
			if ( ! $this->first_shipping_date ) {
				$this->first_shipping_date = wc_od_get_first_shipping_date( array(), $context );
			}

			return $this->first_shipping_date;
		}

		/**
		 * Gets the first day to deliver the orders.
		 *
		 * @since 1.0.0
		 * @since 1.4.0 Added `$context` parameter.
		 *
		 * @param string $context Optional. The context.
		 * @return int A timestamp representing the first allowed date to deliver the orders.
		 */
		public function get_first_delivery_date( $context = 'checkout' ) {
			if ( ! $this->first_delivery_date ) {
				$this->first_delivery_date = wc_od_get_first_delivery_date(
					array(
						'shipping_date'      => $this->get_first_shipping_date( $context ),
						'shipping_method'    => $this->get_shipping_method(),
						'end_date'           => strtotime( ( $this->max_delivery_days() + 1 ) . ' days', wc_od_get_local_date() ), // Non-inclusive.
						'disabled_days_args' => array(
							'type'    => 'delivery',
							'country' => WC()->customer->get_shipping_country(),
							'state'   => WC()->customer->get_shipping_state(),
						),
					),
					$context
				);
			}

			return $this->first_delivery_date;
		}

		/**
		 * Gets the minimum days for delivery.
		 *
		 * @since 1.0.0
		 *
		 * @return int The minimum days for delivery.
		 */
		public function min_delivery_days() {
			$min_delivery_days = 0;
			$delivery_date     = $this->get_first_delivery_date();

			if ( $delivery_date ) {
				$min_delivery_days = ( ( $delivery_date - wc_od_get_local_date() ) / DAY_IN_SECONDS );
			}

			/**
			 * Filters the minimum days for delivery.
			 *
			 * @since 1.0.0
			 *
			 * @param int $min_delivery_days The minimum days for delivery.
			 */
			$min_delivery_days = apply_filters( 'wc_od_min_delivery_days', $min_delivery_days );

			return intval( $min_delivery_days );
		}

		/**
		 * Gets the maximum days for delivery.
		 *
		 * @since 1.0.0
		 *
		 * @return int The maximum days for delivery.
		 */
		public function max_delivery_days() {
			/**
			 * Filters the maximum days for delivery.
			 *
			 * @since 1.0.0
			 *
			 * @param int $max_delivery_days The maximum days for delivery.
			 */
			$max_delivery_days = apply_filters( 'wc_od_max_delivery_days', WC_OD()->settings()->get_setting( 'max_delivery_days' ) );

			return intval( $max_delivery_days );
		}

		/**
		 * Checks if the time frames have room for more orders. Otherwise the time frame will be removed.
		 *
		 * @since 1.8.0
		 *
		 * @param WC_OD_Collection_Time_Frames $time_frames A collection of time frames.
		 * @param int|string                   $timestamp The timestamp date.
		 *
		 * @return WC_OD_Collection_Time_Frames
		 */
		public function filter_unavailable_time_frames( $time_frames, $timestamp ) {
			$available_time_frames = new WC_OD_Collection_Time_Frames();

			/* @var WC_OD_Time_Frame $time_frame A WC_OD_Time_Frame object. */
			foreach ( $time_frames as $index => $time_frame ) {
				if ( ! wc_od_time_frame_is_full( $timestamp, $time_frame ) ) {
					$available_time_frames->set( $index, $time_frame );
				}
			}

			return $available_time_frames;
		}
	}
}
