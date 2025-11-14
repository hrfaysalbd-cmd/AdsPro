<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Adcp_Contracts_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => 'contract',
            'plural'   => 'contracts',
            'ajax'     => false
        ) );
    }

    public function get_columns() {
        return array(
            'cb'            => '<input type="checkbox" />',
            'client_name'   => 'Client',
            'status'        => 'Status',
            'payment_status'=> 'Payment',
            'grand_total'   => 'Total',
            'created_at'    => 'Date Submitted'
        );
    }
    
    public function get_sortable_columns() {
        return array(
            'client_name'   => array( 'client_name', false ),
            'status'        => array( 'status', false ),
            'grand_total'   => array( 'grand_total', false ),
            'created_at'    => array( 'created_at', true ),
        );
    }

    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="contract_id[]" value="%s" />', $item['id'] );
    }

    public function column_client_name( $item ) {
        $view_url = admin_url( 'admin.php?page=adcp-contract-view&id=' . $item['id'] );
        $actions = array(
            'view' => sprintf( '<a href="%s">View/Approve</a>', esc_url( $view_url ) ),
        );
        return sprintf( '<strong><a href="%s">%s</a></strong><br>%s %s', 
            esc_url( $view_url ), 
            esc_html( $item['client_name'] ), 
            esc_html( $item['client_email'] ),
            $this->row_actions( $actions )
        );
    }
    
    public function column_status( $item ) {
        $status = $item['status'];
        $color = 'orange';
        if ($status === 'approved') $color = 'green';
        if ($status === 'rejected') $color = 'red';
        return sprintf( '<strong style="color:%s;">%s</strong>', $color, ucfirst($status) );
    }
    
    public function column_payment_status( $item ) {
        return ucfirst($item['payment_status']);
    }

    public function column_grand_total( $item ) {
        // --- THIS IS THE FIX ---
        return '$' . number_format( (float) $item['grand_total'], 2 );
        // --- END FIX ---
    }

    public function column_default( $item, $column_name ) {
        return esc_html( $item[ $column_name ] ?? '' );
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'adcp_contracts';
        
        $this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        $orderby = $_GET['orderby'] ?? 'created_at';
        $order = $_GET['order'] ?? 'DESC';
        $valid_orderby = array_keys( $this->get_sortable_columns() );
        if ( ! in_array( $orderby, $valid_orderby ) ) {
            $orderby = 'created_at';
        }
        
        $total_items = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name" );

        $this->set_pagination_args( array(
            'total_items' => (int) $total_items,
            'per_page'    => $per_page
        ) );
        
        $offset = ( $current_page - 1 ) * $per_page;
        $this->items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY " . esc_sql( $orderby ) . " " . esc_sql( $order ) . " LIMIT %d OFFSET %d",
            $per_page, $offset
        ), 'ARRAY_A' );
    }
}