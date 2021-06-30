<?php
/**
 * Plugin Name: Custom WooCommerce Order Delivery
 * Plugin URI: https://woocommerce.com/products/woocommerce-order-delivery/
 * Description: Choose a delivery date per day of week, during checkout for the order.
 * Version: 1.8.6
 * Author: Themesquad/Kostali
 * Author URI: https://kostali.com/ 
 * Requires at least: 4.4
 * Tested up to: 5.6
 * WC requires at least: 3.0
 * WC tested up to: 5.1
 * Woo: 976514:beaa91b8098712860ec7335d3dca61c0
 *
 * Text Domain: custom-woocommerce-order-delivery
 * Domain Path: /languages/
 *
 * Copyright: © 2015-2021 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package WC_OD
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once dirname(__FILE__) . '/../woocommerce-order-delivery/woo-includes/woo-functions.php';
}

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), 'beaa91b8098712860ec7335d3dca61c0', '976514' );

/**
 * Check if WooCommerce is active and the minimum requirements are satisfied.
 */
if ( ! is_woocommerce_active() || version_compare( get_option( 'woocommerce_db_version' ), '3.0', '<' ) ) {
	add_action( 'admin_notices', 'wc_od_requirements_notice' );
	return;
}

/**
 * Displays an admin notice when the minimum requirements are not satisfied.
 *
 * @since 1.4.1
 */
function wc_od_requirements_notice() {
	if ( current_user_can( 'activate_plugins' ) ) :
		if ( is_woocommerce_active() ) :
			/* translators: %s: WooCommerce version */
			$message = sprintf( __( '<strong>WooCommerce Order Delivery</strong> requires WooCommerce %s or higher.', 'woocommerce-order-delivery' ), '3.0' );
		else :
			$message = __( '<strong>WooCommerce Order Delivery</strong> requires WooCommerce to be activated to work.', 'woocommerce-order-delivery' );
		endif;

		printf( '<div class="error"><p>%s</p></div>', wp_kses_post( $message ) );
	endif;
}

/**
 * Singleton pattern
 */
if ( ! class_exists( 'WC_OD_Singleton' ) ) {
	require_once  dirname(__FILE__) .'/../woocommerce-order-delivery/includes/class-wc-od-singleton.php';
}

if ( ! class_exists( 'WC_Order_Delivery' ) ) {

	/**
	 * Class WC_Order_Delivery
	 */
	final class WC_Order_Delivery extends WC_OD_Singleton {

		/**
		 * The plugin version.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $version = '1.8.6';


		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 */
		protected function __construct() {
			parent::__construct();

			$this->define_constants();
			$this->includes();
			$this->init_hooks();
		}

		/**
		 * Define constants.
		 *
		 * @since 1.1.0
		 */
		public function define_constants() {
			$this->define( 'WC_OD_VERSION', $this->version );
			$this->define( 'WC_OD_PATH', plugin_dir_path( __FILE__ ) );
			$this->define( 'WC_OD_URL', '/wp-content/plugins/woocommerce-order-delivery/'  );
			$this->define( 'WC_OD_BASENAME', plugin_basename( __FILE__ ) );
		}

		/** /home/hamza/projects/eallie.com/demo/wp-content/plugins/woocommerce-order-delivery/assets
		 * Define constant if not already set.
		 *
		 * @since 1.1.0
		 *
		 * @param string      $name  The constant name.
		 * @param string|bool $value The constant value.
		 */
		private function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		/**
		 * Auto-load in-accessible properties on demand.
		 *
		 * NOTE: Keep backward compatibility with some deprecated properties on this class.
		 *
		 * @since 1.1.0
		 *
		 * @param mixed $key The property name.
		 * @return mixed The property value.
		 */
		public function __get( $key ) {
			switch ( $key ) {
				case 'dir_path':
					wc_deprecated_argument( 'WC_Order_Delivery->dir_path', '1.1.0', 'This property is deprecated and will be removed in future releases. Use the constant WC_OD_PATH instead.' );
					return WC_OD_PATH;

				case 'dir_url':
					wc_deprecated_argument( 'WC_Order_Delivery->dir_url', '1.1.0', 'This property is deprecated and will be removed in future releases. Use the constant WC_OD_URL instead.' );
					return WC_OD_URL;

				case 'date_format':
					wc_deprecated_argument( 'WC_Order_Delivery->date_format', '1.1.0', 'This property is deprecated and will be removed in future releases. Use the function wc_od_get_date_format() instead.' );
					return wc_od_get_date_format( 'php' );

				case 'date_format_js':
					wc_deprecated_argument( 'WC_Order_Delivery->date_format', '1.1.0', 'This property is deprecated and will be removed in future releases. Use the function wc_od_get_date_format() instead.' );
					return wc_od_get_date_format( 'js' );

				case 'prefix':
					wc_deprecated_argument( 'WC_Order_Delivery->prefix', '1.1.0', 'This property is deprecated and will be removed in future releases.' );
					return 'wc_od_';
			}
		}

		/**
		 * Includes the necessary files.
		 *
		 * @since 1.0.0
		 */
		public function includes() {
			/**
			 * Class autoloader.
			 */
			include_once  dirname(__FILE__) .'/../woocommerce-order-delivery/includes/class-wc-od-autoloader.php';

			/**
			 * Abstract classes.
			 */
			include_once  dirname(__FILE__) .'/../woocommerce-order-delivery/includes/abstracts/abstract-class-wc-od-data.php';
			include_once  dirname(__FILE__) .'/../woocommerce-order-delivery/includes/abstracts/abstract-class-wc-od-shipping-methods-data.php';

			/**
			 * Core classes.
			 */
			include_once  dirname(__FILE__) .'/../woocommerce-order-delivery/includes/wc-od-functions.php';
			include_once  dirname(__FILE__) .'/../woocommerce-order-delivery/includes/class-wc-od-install.php';
            include_once  dirname(__FILE__) .'/../woocommerce-order-delivery/includes/class-wc-od-emails.php';
            
			
			$this->custom_includes();
		    $this->include_orders_custom_export();
            
			if ( is_admin() ) {
				include_once  dirname(__FILE__) .'/../woocommerce-order-delivery/includes/admin/class-wc-od-admin.php';
			}

			if ( WC_OD_Utils::is_plugin_active(  dirname(__FILE__) .'/../woocommerce-subscriptions/woocommerce-subscriptions.php' ) ) {
				include_once  dirname(__FILE__) .'/../woocommerce-order-delivery/includes/subscriptions/class-wc-od-subscriptions.php';
			}

		}
		 /**
		 * Kostali custom imports 
		 * wc-od-shipping-delivery-functions class-wc-od-checkout wc-od-shipping-delivery-functions
		 */
		private function custom_includes(){
			
            include_once  dirname(__FILE__) . '/../woocommerce/includes/abstracts/abstract-wc-data.php';
			include_once  dirname(__FILE__) . '/../woocommerce/includes/abstracts/abstract-wc-settings-api.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/abstracts/abstract-class-wc-od-settings-api.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/abstracts/abstract-class-wc-od-shipping-methods-data.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/abstracts/abstract-class-wc-od-settings-api.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/admin/fields/abstract-class-wc-od-admin-field-table.php';
			include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/class-wc-od-utils.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/class-wc-od-delivery-range.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/class-wc-od-delivery-ranges.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/class-wc-od-delivery-date.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/class-wc-od-delivery-dates.php';
			include_once  dirname(__FILE__) . '/includes/wc-od-shipping-delivery-functions.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/class-wc-od-settings.php';
            include_once  dirname(__FILE__) . '/../woocommerce/includes/shortcodes/class-wc-shortcode-checkout.php';
            include_once  dirname(__FILE__) . '/includes/class-wc-od-checkout.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/class-wc-od-order-details.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/class-wc-od-delivery-cache.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/admin/fields/class-wc-od-admin-field-delivery-ranges.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/class-wc-od-delivery-range.php';
            //include_once  dirname(__FILE__) . '';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/class-wc-od-time-frame.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/collections/class-wc-od-collection.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/collections/class-wc-od-collection-time-frames.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/admin/settings/class-wc-od-settings-time-frame.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/admin/settings/class-wc-od-settings-delivery-day-time-frame.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/admin/fields/class-wc-od-admin-field-time-frames.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/admin/settings/class-wc-od-settings-delivery-day.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/class-wc-od-delivery-day.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/collections/class-wc-od-collection.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/collections/class-wc-od-collection-delivery-days.php';
            include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/admin/settings/class-wc-od-settings-delivery-days-time-frame.php';
			include_once  dirname(__FILE__) . '/../woocommerce-order-delivery/includes/admin/fields/class-wc-od-admin-field-delivery-days.php';
			//WC_OD_Delivery_Ranges
            //wp-content/plugins/woocommerce-order-delivery/includes/admin/settings/class-wc-od-settings-delivery-days-time-frame.php
            //wp-content/plugins/woocommerce-order-delivery/includes/class-wc-od-delivery-ranges.php
            //wp-content/plugins/woocommerce-order-delivery/includes/abstracts/abstract-class-wc-od-settings-api.php
            //wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-settings-api.php
			
		}

		private function include_orders_custom_export(){
			include_once  dirname(__FILE__) . '/custom-woocommerce-order-csv-export.php';
		}
		/**
		 * Hook into actions and filters.
		 *
		 * @since 1.1.0
		 */
		private function init_hooks() {
			// Install.
			register_activation_hook( __FILE__, array( 'WC_OD_Install', 'install' ) );

			// Init.
			add_action( 'plugins_loaded', array( $this, 'init' ) );

			// Data stores.
			add_filter( 'woocommerce_data_stores', array( $this, 'register_data_stores' ) );
		}

		/**
		 * Init plugin.
		 *
		 * @since 1.0.0
		 */
		public function init() {
			// Load text domain.
			load_plugin_textdomain( 'woocommerce-order-delivery', false, dirname( WC_OD_BASENAME ) . '/languages' );

			// Load checkout.
			$this->checkout();

			// Load order details.
			$this->order_details();

			// Load cache.
			$this->cache();
		}

		/**
		 * Register data stores.
		 *
		 * @since 1.8.0
		 *
		 * @param array $stores Data stores.
		 * @return array
		 */
		public function register_data_stores( $stores ) {
			$stores['delivery_range'] = 'WC_OD_Data_Store_Delivery_Range';

			return $stores;
		}

		/**
		 * Displays an admin notice when the WooCommerce plugin is not active.
		 *
		 * @since 1.0.0
		 * @deprecated 1.4.1
		 */
		public function woocommerce_not_active() {
			wc_deprecated_function( __METHOD__, '1.4.1', 'This method is deprecated and will be removed in future releases.' );
		}

		/**
		 * Adds custom links to the plugins page.
		 *
		 * @since 1.0.0
		 * @deprecated 1.2.0
		 *
		 * @param array $links The plugin links.
		 * @return array The filtered plugin links.
		 */
		public function action_links( $links ) {
			wc_deprecated_function( __METHOD__, '1.2.0', 'This method is deprecated and will be removed in future releases. Use the method WC_OD_Install::plugin_action_links instead.' );

			return $links;
		}

		/**
		 * Get Settings Class.
		 *
		 * @since 1.0.0
		 *
		 * @return WC_OD_Settings
		 */
		public function settings() {
			return WC_OD_Settings::instance();
		}

		/**
		 * Get Checkout Class.
		 *
		 * @since 1.0.0
		 *
		 * @return WC_OD_Checkout
		 */
		public function checkout() {
			return WC_OD_Checkout::instance();
		}

		/**
		 * Get Order_Details Class.
		 *
		 * @since 1.0.0
		 *
		 * @return WC_OD_Order_Details
		 */
		public function order_details() {
			return WC_OD_Order_Details::instance();
		}

		public function cache() {
			return WC_OD_Delivery_Cache::instance();
		}
	}

	/**
	 * The main function for returning the plugin instance and avoiding
	 * the need to declare the global variable.
	 *
	 * @since 1.0.0
	 *
	 * @return WC_Order_Delivery The *Singleton* instance.
	 */
	function WC_OD() {
		custom_settings();
		return WC_Order_Delivery::instance();
	}

	function custom_checkout_settings(){
		error_log(__CLASS__.':'.__METHOD__);
	}
	function custom_settings(){
		add_action('woocommerce_checkout_shipping',  'custom_checkout_settings');
	}

	WC_OD();
}
