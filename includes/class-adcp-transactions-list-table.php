<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * AdsCampaignPro Transactions List Table
 *
 * Renders the list of all transactions.
 */
class Adcp_Transactions_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => 'transaction',
            'plural'   => 'transactions',
            'ajax'     => false
        ) );
    }

    public function get_columns() {
        return array(
            'cb'          => '<input type="checkbox" />',
            'contract_id' => 'Contract',
            'provider'    => 'Provider',
            'txn_id'      => 'Transaction ID',
            'amount'      => 'Amount',
            'status'      => 'Status',
            'created_at'  => 'Date'
        );
    }

    public function get_sortable_columns() {
        return array(
            'contract_id' => array( 'contract_id', false ),
            'amount'      => array( 'amount', false ),
            'status'      => array( 'status', false ),
            'created_at'  => array( 'created_at', true ), // Default sort
        );
    }

    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="txn_id[]" value="%s" />', $item['id'] );
    }

    public function column_contract_id( $item ) {
        $view_url = admin_url( 'admin.php?page=adcp-contract-view&id=' . $item['contract_id'] );
        return sprintf( '<strong><a href="%s">View Contract #%s</a></strong>', esc_url( $view_url ), esc_html( $item['contract_id'] ) );
    }

    public function column_amount( $item ) {
        return '$' . number_format( (float) $item['amount'], 2 );
    }
    
    public function column_status( $item ) {
        $status = ucfirst($item['status']);
        $color = 'orange';
        if ($item['status'] === 'completed' || $item['status'] === 'verified') $color = 'green';
        if ($item['status'] === 'failed') $color = 'red';
        
        return sprintf( '<strong style="color:%s;">%s</strong>', $color, $status );
    }

    // --- THIS IS THE FIX ---
    public function column_default( $item, $column_name ) {
        return esc_html( $item[ $column_name ] ?? '' );
    }
    // --- END FIX ---

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'adcp_transactions';
        
        // --- FIX: Replaced all $this. and $wpdb. with $this-> and $wpdb-> ---
        $this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        $orderby = $_GET['orderby'] ?? 'created_at';
        $order = $_GET['order'] ?? 'DESC';
        $valid_orderby = array_keys( $this->get_sortable_columns() );
        if ( ! in_array( $orderby, $valid_orderby ) ) { $orderby = 'created_at'; }
        
        $total_items = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name" );
        $this->set_pagination_args( array( 'total_items' => (int) $total_items, 'per_page' => $per_page ) );
        
        $offset = ( $current_page - 1 ) * $per_page;
        $this->items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY " . esc_sql( $orderby ) . " " . esc_sql( $order ) . " LIMIT %d OFFSET %d",
            $per_page, $offset
        ), 'ARRAY_A' );
        // --- END FIX ---
    }
}