<?php
/**
 * AdsCampaignPro Campaigns DB Class
 *
 * Handles all database operations for campaigns.
 */
class Adcp_Campaigns_DB {

    /**
     * Get the table name.
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'adcp_campaigns';
    }

    /**
     * Get a single campaign by ID.
     */
    public static function get_campaign( $id ) {
        global $wpdb;
        $id = absint( $id );
        if ( ! $id ) {
            return null;
        }
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::get_table_name() . " WHERE id = %d", $id ) );
    }

    /**
     * Insert a new campaign.
     */
    public static function insert_campaign( $data ) {
        global $wpdb;
        
        $defaults = array(
            'name'        => '',
            'type'        => 'popup',
            'status'      => 'draft',
            'config'      => '{}',
            'start'       => null,
            'end'         => null,
            'priority'    => 10,
            'contract_id' => null,
            'created_by'  => get_current_user_id(),
        );
        $data = wp_parse_args( $data, $defaults );
        
        $all_formats = array(
            'name'        => '%s',
            'type'        => '%s',
            'status'      => '%s',
            'config'      => '%s',
            'start'       => '%s',
            'end'         => '%s',
            'priority'    => '%d',
            'contract_id' => '%d',
            'created_by'  => '%d',
        );

        // Ensure $data and $formats arrays are aligned
        $data = array_intersect_key( $data, $all_formats );
        $formats = array_intersect_key( $all_formats, $data );

        if ( $wpdb->insert( self::get_table_name(), $data, $formats ) ) {
            return $wpdb->insert_id;
        }
        return false;
    }

    /**
     * Update an existing campaign.
     * --- THIS FUNCTION HAS BEEN RE-WRITTEN TO FIX THE SAVING BUG ---
     */
    public static function update_campaign( $id, $data ) {
        global $wpdb;
        $id = absint( $id );
        if ( ! $id ) {
            return false;
        }

        // Master list of all allowed columns and their formats
        $all_formats = array(
            'name'        => '%s',
            'type'        => '%s',
            'status'      => '%s',
            'config'      => '%s',
            'start'       => '%s',
            'end'         => '%s',
            'priority'    => '%d',
            'contract_id' => '%d',
        );
        
        // --- FIX: Robust data and format filtering ---
        // Loop through the submitted data and build clean arrays
        $update_data = array();
        $update_formats = array();
        
        foreach ( $data as $key => $value ) {
            // Only include data that is in our master list
            if ( array_key_exists( $key, $all_formats ) ) {
                $update_data[$key] = $value;
                $update_formats[] = $all_formats[$key];
            }
        }
        // --- END FIX ---

        if ( empty( $update_data ) ) {
            return false;
        }

        $result = $wpdb->update( 
            self::get_table_name(), 
            $update_data,      // Clean data to update
            array( 'id' => $id ), // WHERE clause
            $update_formats,   // Formats for clean data
            array( '%d' )      // Format for WHERE
        );
        
        return $result !== false;
    }

    /**
     * --- NEW FUNCTION: Fixes the "Critical Error" on delete ---
     * Delete a campaign by ID.
     */
    public static function delete_campaign( $id ) {
        global $wpdb;
        $id = absint( $id );
        if ( ! $id ) {
            return false;
        }
        
        return $wpdb->delete( self::get_table_name(), array( 'id' => $id ), array( '%d' ) );
    }
    // --- END NEW FUNCTION ---
    
    /**
     * Get all active campaigns for rendering.
     */
    public static function get_active_campaigns_for_render() {
        global $wpdb;
        $now = current_time( 'mysql' );
        
        $sql = $wpdb->prepare( "
            SELECT id, name, type, config, priority
            FROM " . self::get_table_name() . "
            WHERE
                status = 'active'
                AND ( start IS NULL OR start <= %s )
                AND ( end IS NULL OR end >= %s )
            ORDER BY priority DESC, id DESC
        ", $now, $now );
        
        return $wpdb->get_results( $sql );
    }
}