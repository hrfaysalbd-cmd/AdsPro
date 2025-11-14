<?php
/**
 * AdsCampaignPro Tracking DB Class
 *
 * Handles all database operations for tracking events.
 */
class Adcp_Tracking_DB {

    /**
     * Get the table name.
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'adcp_tracking';
    }

    /**
     * Insert a single tracking event.
     */
    public static function insert_event( $data ) {
        global $wpdb;
        
        $defaults = array(
            'campaign_id' => 0,
            'event_type'  => null,
            'cookie_id'   => '',
            'ip_hash'     => '',
            'user_agent'  => '',
            'page_url'    => '',
            'meta'        => '{}',
        );
        $data = wp_parse_args( $data, $defaults );

        $all_formats = array(
            'campaign_id' => '%d',
            'event_type'  => '%s',
            'cookie_id'   => '%s',
            'ip_hash'     => '%s',
            'user_agent'  => '%s',
            'page_url'    => '%s',
            'meta'        => '%s',
        );

        // --- THIS IS THE FIX ---
        // Ensure $data and $formats arrays are aligned
        $data = array_intersect_key( $data, $all_formats );
        $formats = array_intersect_key( $all_formats, $data );
        // --- END FIX ---
        
        if ( $wpdb->insert( self::get_table_name(), $data, $formats ) ) {
            return $wpdb->insert_id;
        }
        return false;
    }
}