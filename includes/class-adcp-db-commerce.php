<?php
/**
 * AdsCampaignPro Commerce DB Classes
 *
 * Handles DB operations for Packages, Coupons, Extras, and Contracts.
 */

// --- PACKAGES ---

class Adcp_Packages_DB {
    private static function get_table_name() { global $wpdb; return $wpdb->prefix . 'adcp_packages'; }

    public static function get_package( $id ) {
        global $wpdb; $id = absint( $id );
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::get_table_name() . " WHERE id = %d", $id ) );
    }

    public static function get_packages( $args = array() ) {
        global $wpdb;
        $defaults = array( 'number' => 20, 'offset' => 0, 'orderby' => 'price', 'order' => 'ASC' );
        $args = wp_parse_args( $args, $defaults );
        
        $sql = $wpdb->prepare( 
            "SELECT * FROM " . self::get_table_name() . " ORDER BY " . esc_sql( $args['orderby'] ) . " " . esc_sql( $args['order'] ) . " LIMIT %d OFFSET %d",
            $args['number'], $args['offset']
        );
        return $wpdb->get_results( $sql, 'ARRAY_A' );
    }

    public static function get_package_count() {
        global $wpdb;
        return $wpdb->get_var( "SELECT COUNT(id) FROM " . self::get_table_name() );
    }

    public static function insert_package( $data ) {
        global $wpdb;
        $all_formats = array( 'title' => '%s', 'description' => '%s', 'price' => '%f', 'cycle' => '%s', 'features' => '%s', 'allow_coupon' => '%d' );
        
        // --- THIS IS THE FIX ---
        $data = array_intersect_key( $data, $all_formats );
        $formats = array_intersect_key( $all_formats, $data );
        // --- END FIX ---

        if ( $wpdb->insert( self::get_table_name(), $data, $formats ) ) {
            return $wpdb->insert_id;
        }
        return false;
    }
    
    public static function update_package( $id, $data ) {
        global $wpdb; $id = absint( $id );
        $all_formats = array( 'title' => '%s', 'description' => '%s', 'price' => '%f', 'cycle' => '%s', 'features' => '%s', 'allow_coupon' => '%d' );
        
        // --- THIS IS THE FIX ---
        $data = array_intersect_key( $data, $all_formats );
        $formats = array_intersect_key( $all_formats, $data );
        // --- END FIX ---

        if ( empty( $data ) ) { return false; }
        $result = $wpdb->update( self::get_table_name(), $data, array( 'id' => $id ), $formats, array( '%d' ) );
        return $result !== false;
    }
    
    public static function get_all_packages_for_form() {
        global $wpdb;
        return $wpdb->get_results( "SELECT id, title, price, cycle FROM " . self::get_table_name() . " ORDER BY price ASC" );
    }
}

// --- COUPONS ---

class Adcp_Coupons_DB {
    private static function get_table_name() { global $wpdb; return $wpdb->prefix . 'adcp_coupons'; }

    public static function get_coupon( $id ) {
        global $wpdb; $id = absint( $id );
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::get_table_name() . " WHERE id = %d", $id ) );
    }

    public static function get_coupons( $args = array() ) {
        global $wpdb;
        $defaults = array( 'number' => 20, 'offset' => 0, 'orderby' => 'id', 'order' => 'DESC' );
        $args = wp_parse_args( $args, $defaults );
        
        $sql = $wpdb->prepare( 
            "SELECT * FROM " . self::get_table_name() . " ORDER BY " . esc_sql( $args['orderby'] ) . " " . esc_sql( $args['order'] ) . " LIMIT %d OFFSET %d",
            $args['number'], $args['offset']
        );
        return $wpdb->get_results( $sql, 'ARRAY_A' );
    }

    public static function get_coupon_count() {
        global $wpdb;
        return $wpdb->get_var( "SELECT COUNT(id) FROM " . self::get_table_name() );
    }

    public static function insert_coupon( $data ) {
        global $wpdb;
        $all_formats = array( 'code' => '%s', 'type' => '%s', 'value' => '%f', 'max_uses' => '%d', 'limit_per_user' => '%d', 'start_date' => '%s', 'end_date' => '%s', 'status' => '%s' );
        
        // --- THIS IS THE FIX ---
        $data = array_intersect_key( $data, $all_formats );
        $formats = array_intersect_key( $all_formats, $data );
        // --- END FIX ---
        
        if ( $wpdb->insert( self::get_table_name(), $data, $formats ) ) {
            return $wpdb->insert_id;
        }
        return false;
    }
    
    public static function update_coupon( $id, $data ) {
        global $wpdb; $id = absint( $id );
        $all_formats = array( 'code' => '%s', 'type' => '%s', 'value' => '%f', 'max_uses' => '%d', 'limit_per_user' => '%d', 'start_date' => '%s', 'end_date' => '%s', 'status' => '%s' );
        
        // --- THIS IS THE FIX ---
        $data = array_intersect_key( $data, $all_formats );
        $formats = array_intersect_key( $all_formats, $data );
        // --- END FIX ---

        if ( empty( $data ) ) { return false; }
        $result = $wpdb->update( self::get_table_name(), $data, array( 'id' => $id ), $formats, array( '%d' ) );
        return $result !== false;
    }

    public static function get_coupon_by_code( $code ) {
        global $wpdb;
        $code = sanitize_text_field( $code );
        $today = current_time( 'Y-m-d' );

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::get_table_name() . "
             WHERE code = %s
             AND status = 'active'
             AND ( max_uses = 0 OR used_count < max_uses )
             AND ( start_date IS NULL OR start_date <= %s )
             AND ( end_date IS NULL OR end_date >= %s )",
            $code, $today, $today
        ) );
    }
}

// --- EXTRA PACKAGES ---

class Adcp_Extras_DB {
    private static function get_table_name() { global $wpdb; return $wpdb->prefix . 'adcp_extras'; }

    public static function get_extra( $id ) {
        global $wpdb; $id = absint( $id );
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::get_table_name() . " WHERE id = %d", $id ) );
    }

    public static function get_extras( $args = array() ) {
        global $wpdb;
        $defaults = array( 'number' => 20, 'offset' => 0, 'orderby' => 'price', 'order' => 'ASC' );
        $args = wp_parse_args( $args, $defaults );
        
        $sql = $wpdb->prepare( 
            "SELECT * FROM " . self::get_table_name() . " ORDER BY " . esc_sql( $args['orderby'] ) . " " . esc_sql( $args['order'] ) . " LIMIT %d OFFSET %d",
            $args['number'], $args['offset']
        );
        return $wpdb->get_results( $sql, 'ARRAY_A' );
    }

    public static function get_extra_count() {
        global $wpdb;
        return $wpdb->get_var( "SELECT COUNT(id) FROM " . self::get_table_name() );
    }

    public static function insert_extra( $data ) {
        global $wpdb;
        $all_formats = array( 'title' => '%s', 'description' => '%s', 'price' => '%f', 'delivery_time' => '%d' );
        
        // --- THIS IS THE FIX ---
        $data = array_intersect_key( $data, $all_formats );
        $formats = array_intersect_key( $all_formats, $data );
        // --- END FIX ---
        
        if ( $wpdb->insert( self::get_table_name(), $data, $formats ) ) {
            return $wpdb->insert_id;
        }
        return false;
    }
    
    public static function update_extra( $id, $data ) {
        global $wpdb; $id = absint( $id );
        $all_formats = array( 'title' => '%s', 'description' => '%s', 'price' => '%f', 'delivery_time' => '%d' );
        
        // --- THIS IS THE FIX ---
        $data = array_intersect_key( $data, $all_formats );
        $formats = array_intersect_key( $all_formats, $data );
        // --- END FIX ---

        if ( empty( $data ) ) { return false; }
        $result = $wpdb->update( self::get_table_name(), $data, array( 'id' => $id ), $formats, array( '%d' ) );
        return $result !== false;
    }
    
    public static function get_all_extras_for_form() {
        global $wpdb;
        return $wpdb->get_results( "SELECT id, title, price FROM " . self::get_table_name() . " ORDER BY price ASC" );
    }
}

// --- CONTRACTS ---

class Adcp_Contracts_DB {
    private static function get_table_name() { global $wpdb; return $wpdb->prefix . 'adcp_contracts'; }

    public static function insert_contract( $data ) {
        global $wpdb;
        
        $defaults = array(
            'client_name'    => '',
            'client_email'   => '',
            'client_phone'   => '',
            'data'           => '{}',
            'status'         => 'pending',
            'tracking_token' => null,
            'grand_total'    => 0.00,
            'payment_status' => 'pending',
        );
        $data = wp_parse_args( $data, $defaults );

        $all_formats = array(
            'client_name'    => '%s',
            'client_email'   => '%s',
            'client_phone'   => '%s',
            'data'           => '%s',
            'status'         => '%s',
            'grand_total'    => '%f',
            'payment_status' => '%s',
            'tracking_token' => '%s',
        );

        // --- THIS IS THE FIX ---
        $data = array_intersect_key( $data, $all_formats );
        $formats = array_intersect_key( $all_formats, $data );
        // --- END FIX ---

        if ( $wpdb->insert( self::get_table_name(), $data, $formats ) ) {
            return $wpdb->insert_id;
        }
        return false;
    }
}