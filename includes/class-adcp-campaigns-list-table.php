<?php
/**
 * AdsCampaignPro Campaigns List Table
 *
 * This file is new and renders the list of all campaigns.
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Adcp_Campaigns_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => 'campaign',
            'plural'   => 'campaigns',
            'ajax'     => false
        ) );
    }

    /**
     * Get the columns for the table.
     */
    public function get_columns() {
        return array(
            'cb'          => '<input type="checkbox" />',
            'name'        => 'Name',
            'status'      => 'Status',
            'type'        => 'Type',
            'shortcode'   => 'Shortcode', // <-- NEW COLUMN
            'client'      => 'Client',
            'impressions' => 'Impressions',
            'clicks'      => 'Clicks',
            'created_at'  => 'Date'
        );
    }
    
    /**
     * Define sortable columns.
     */
    public function get_sortable_columns() {
        return array(
            'name'       => array( 'name', false ),
            'status'     => array( 'status', false ),
            'type'       => array( 'type', false ),
            'created_at' => array( 'created_at', true ),
        );
    }

    /**
     * Render the checkbox column.
     */
    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="campaign_id[]" value="%s" />', $item['id'] );
    }
    
    /**
     * Render the 'name' column with edit/delete actions.
     */
    public function column_name( $item ) {
        $page_slug = 'adcp-campaign-edit';
        $edit_url = admin_url( 'admin.php?page=' . $page_slug . '&id=' . $item['id'] );
        
        // --- FIX: Add nonce to delete link ---
        $delete_nonce = wp_create_nonce( 'adcp_delete_campaign_' . $item['id'] );
        $delete_url = admin_url( 'admin-post.php?action=adcp_delete_campaign&id=' . $item['id'] . '&_wpnonce=' . $delete_nonce );

        $actions = array(
            'edit'   => sprintf( '<a href="%s">Edit</a>', esc_url( $edit_url ) ),
            'delete' => sprintf( '<a href="%s" onclick="return confirm(\'Are you sure?\')" style="color: red;">Delete</a>', esc_url( $delete_url ) ),
        );
        return sprintf( '<strong><a href="%s">%s</a></strong> %s', esc_url( $edit_url ), esc_html( $item['name'] ), $this->row_actions( $actions ) );
    }

    /**
     * Render the 'status' column with colors.
     */
    public function column_status( $item ) {
        $status = ucfirst($item['status']);
        $color = 'gray';
        if ($status === 'Active') $color = 'green';
        if ($status === 'Paused') $color = 'orange';
        return sprintf( '<strong style="color:%s;">%s</strong>', $color, $status );
    }

    /**
     * --- NEW COLUMN RENDERER (FIX for Issue #1) ---
     * Render the 'type' column explicitly.
     */
    public function column_type( $item ) {
        return esc_html( ucfirst($item['type']) );
    }

    /**
     * --- NEW COLUMN RENDERER ---
     * Render the 'shortcode' column.
     */
    public function column_shortcode( $item ) {
        if ( $item['type'] === 'embed' ) {
            $shortcode = sprintf( '[adscampaignpro_render id="%d"]', $item['id'] );
            return sprintf( '<input type="text" readonly value="%s" class="small-text" onclick="this.select();" style="min-width: 200px;">', esc_attr( $shortcode ) );
        }
        return '—'; // Dash for non-embed types
    }
    // --- END NEW COLUMN ---

    /**
     * Render the 'client' column by looking up the client name.
     */
    public function column_client( $item ) {
        if ( ! empty( $item['client_name'] ) ) {
            $view_url = admin_url( 'admin.php?page=adcp-contract-view&id=' . $item['contract_id'] );
            return sprintf( '<a href="%s">%s</a>', esc_url( $view_url ), esc_html( $item['client_name'] ) );
        }
        return '<em>(Admin)</em>';
    }
    
    public function column_impressions( $item ) {
        return number_format( (int) $item['impressions'] );
    }

    public function column_clicks( $item ) {
        return number_format( (int) $item['clicks'] );
    }

    /**
     * Render default columns.
     */
    public function column_default( $item, $column_name ) {
        if ( isset( $item[ $column_name ] ) ) {
            return esc_html( $item[ $column_name ] );
        }
        return print_r( $item, true );
    }
    
    /**
     * Get the campaign data from the database.
     */
    public static function get_campaigns( $per_page = 20, $current_page = 1, $orderby = 'id', $order = 'DESC' ) {
        global $wpdb;
        $tbl_campaigns = $wpdb->prefix . 'adcp_campaigns';
        $tbl_contracts = $wpdb->prefix . 'adcp_contracts';
        $tbl_summary   = $wpdb->prefix . 'adcp_tracking_summary';
        
        $sql = $wpdb->prepare( "
            SELECT 
                c.*, 
                co.client_name,
                COALESCE(SUM(s.impressions), 0) as impressions,
                COALESCE(SUM(s.clicks), 0) as clicks
            FROM {$tbl_campaigns} c
            LEFT JOIN {$tbl_contracts} co ON c.contract_id = co.id
            LEFT JOIN {$tbl_summary} s ON c.id = s.campaign_id
            GROUP BY c.id
            ORDER BY " . esc_sql( $orderby ) . " " . esc_sql( $order ) . "
            LIMIT %d OFFSET %d
        ", $per_page, ( $current_page - 1 ) * $per_page );
        
        return $wpdb->get_results( $sql, 'ARRAY_A' );
    }

    /**
     * Get the total count of campaigns.
     */
    public static function get_campaign_count() {
        global $wpdb;
        return $wpdb->get_var( "SELECT COUNT(id) FROM {$wpdb->prefix}adcp_campaigns" );
    }

    /**
     * Prepare the items for the table.
     */
    public function prepare_items() {
        $this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
        
        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $total_items  = self::get_campaign_count(); 

        $this->set_pagination_args( array(
            'total_items' => (int) $total_items,
            'per_page'    => $per_page
        ) );
        
        $orderby = $_GET['orderby'] ?? 'id';
        $order = $_GET['order'] ?? 'DESC';
        
        $this->items = self::get_campaigns( $per_page, $current_page, $orderby, $order );
    }
}