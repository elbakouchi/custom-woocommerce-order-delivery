

<?php // only copy this line if needed


function ____($order){
        $dump = [];
        foreach ( $order->get_items() as $item_id => $item ) {
                $product      = $item->get_product();
                $product_name = $item->get_name();
                $allmeta      = $item->get_meta_data();
                //  debug_print_r(__FILE__, '', __FUNCTION__, 'all order product meta', $allmeta, __LINE__);
                $carting      = $item->get_meta( '_tmcartepo_data', true );
                //  debug_print_r(__FILE__, '', __FUNCTION__, '_tmcartepo_data', $carting, __LINE__);
                //$product_type = $item->get_type();
                foreach($carting as $c){
                        $dump[$product_name] .= $c['name'] . ':' . $c['value'] ; 
                }
        }
        //debug_print_r(__FILE__, '', __FUNCTION__, 'ORDER PRODUCTS. ITEMS', $dump, __LINE__);  
        return implode("", $dump);
}

/**
 * Step 1. Add `example` column header and remove the `billing_company` column
 *
 * @param array $column_headers the original column headers
 * @param \CSV_Export_Generator $csv_generator the generator instance
 * @return array the updated column headers
 */
function sv_wc_csv_export_modify_column_headers_example( $column_headers, $csv_generator ) {

        // add the new `example` column header
        //debug_print_r(__FILE__, '', __FUNCTION__, 'POKPOK', $column_headers, __LINE__);

        $column_headers['delivery_date'] = 'Delivery date';
        $column_headers['time_frame'   ] = 'Time frame';
        $column_headers['tm_meta'      ] = 'Lines';
        // example of removing a column from the export (e.g. the `billing_company` column)
        unset( $column_headers['order_id']);
        unset( $column_headers['order_number']);
        //unset( $column_headers['order_number_formatted']);
        // unset( $column_headers['order_date']);
        // unset( $column_headers['status']);
        // unset( $column_headers['order_total']);
        //unset( $column_headers['shipping_total']);
        //unset( $column_headers['tax_total']);
        //unset( $column_headers['shipping_tax_total']);
        //unset( $column_headers['discount_total']);
        //unset( $column_headers['refunded_total']);
        // unset( $column_headers['payment_method']);
        // unset( $column_headers['order_currency']);
        // unset( $column_headers['customer_id']);
        // unset( $column_headers['billing_first_name']);
        // unset( $column_headers['billing_last_name']);
        // unset( $column_headers['billing_email']);
        // unset( $column_headers['billing_phone']);
        // unset( $column_headers['billing_address_1']);
        // unset( $column_headers['billing_address_2']);
        // unset( $column_headers['billing_postcode']);
        // unset( $column_headers['billing_city']);
        // unset( $column_headers['billing_state']);
        // unset( $column_headers['billing_country']);
        // unset( $column_headers['billing_company']);
        // unset( $column_headers['vat_number']);
        unset( $column_headers['shipping_first_name']);
        unset( $column_headers['shipping_last_name']);
        unset( $column_headers['shipping_address_1']);
        unset( $column_headers['shipping_address_2']);
        unset( $column_headers['shipping_postcode']);
        unset( $column_headers['shipping_city']);
        unset( $column_headers['shipping_state']);
        unset( $column_headers['shipping_country']);
        unset( $column_headers['shipping_company']);
        unset( $column_headers['customer_note']);
        unset( $column_headers['line_items']);
        unset( $column_headers['shipping_items']);
        unset( $column_headers['tax_items']);
        unset( $column_headers['fee_items']);
        unset( $column_headers['coupon_items']);
        unset( $column_headers['refunds']);
        unset( $column_headers['order_notes']);
        unset( $column_headers['download_permissions']);

        return $column_headers;
}
add_filter( 'wc_customer_order_export_csv_order_headers', 'sv_wc_csv_export_modify_column_headers_example', 10, 2 );


/**
 * Step 2. Add `example` column data
 *
 * @param array $order_data the original column data
 * @param \WC_Order $order the order object
 * @param \CSV_Export_Generator $csv_generator the generator instance
 * @return array the updated column data
 */
function sv_wc_csv_export_modify_row_data_delivery_date( $order_data, $order, $csv_generator ) {

       // ____($order);
        // Example showing how to extract order metadata into it's own column
        $meta_key_delivery_date = is_callable( array( $order, 'get_meta' ) ) ? $order->get_meta( '_delivery_date' ) : $order->meta_key_delivery_date;
        $meta_key_time_frame    = is_callable( array( $order, 'get_meta' ) ) ? $order->get_meta( '_delivery_time_frame' ) : $order->meta_key_delivery_time_frame;
        $meta_key_tm_meta       = ____($order);// is_callable( array( $order, 'get_meta' ) ) ? $order->get_meta( '_tmcartepo_data' ) : $order->meta_key_delivery_tm_date;
        // debug_print_r(__FILE__, '', __FUNCTION__, 'meta_key_time_frame', $meta_key_time_frame, __LINE__);
        // debug_print_r(__FILE__, '', __FUNCTION__, 'meta_key_tm_meta', $meta_key_tm_meta, __LINE__);
        // debug_print_r(__FILE__, '', __FUNCTION__, 'order', $order->get_meta( '_tmcartepo_data' ), __LINE__);
        $custom_data = array(
                'delivery_date' => $meta_key_delivery_date,
                'time_frame'    => $meta_key_time_frame['time_from'] . ' - ' .$meta_key_time_frame['time_to'],
                'tm_meta'       => $meta_key_tm_meta
        );

        return sv_wc_csv_export_add_custom_order_data( $order_data, $custom_data, $csv_generator );
}
add_filter( 'wc_customer_order_export_csv_order_row', 'sv_wc_csv_export_modify_row_data_delivery_date', 10, 3 );


if ( ! function_exists( 'sv_wc_csv_export_add_custom_order_data' ) ) :

/**
 * Helper function to add custom order data to CSV Export order data
 *
 * @param array $order_data the original column data that may be in One Row per Item format
 * @param array $custom_data the custom column data being merged into the column data
 * @param \CSV_Export_Generator $csv_generator the generator instance
 * @return array the updated column data
 */
function sv_wc_csv_export_add_custom_order_data( $order_data, $custom_data, $csv_generator ) {

        $new_order_data   = array();

        if ( sv_wc_csv_export_is_one_row( $csv_generator ) ) {

                foreach ( $order_data as $data ) {
                        $new_order_data[] = array_merge( (array) $data, $custom_data );
                }

        } else {
                $new_order_data = array_merge( $order_data, $custom_data );
        }

        return $new_order_data;
}

endif;


if ( ! function_exists( 'sv_wc_csv_export_is_one_row' ) ) :

/**
 * Helper function to check the export format
 *
 * @param \CSV_Export_Generator $csv_generator the generator instance
 * @return bool - true if this is a one row per item format
 */
function sv_wc_csv_export_is_one_row( $csv_generator ) {

        $one_row_per_item = false;

        if ( version_compare( wc_customer_order_csv_export()->get_version(), '4.0.0', '<' ) ) {

                // pre 4.0 compatibility
                $one_row_per_item = ( 'default_one_row_per_item' === $csv_generator->order_format || 'legacy_one_row_per_item' === $csv_generator->order_format );

        } elseif ( isset( $csv_generator->format_definition ) ) {

                // post 4.0 (requires 4.0.3+)
                $one_row_per_item = 'item' === $csv_generator->format_definition['row_type'];
        }

        return $one_row_per_item;
}

endif;

?>

