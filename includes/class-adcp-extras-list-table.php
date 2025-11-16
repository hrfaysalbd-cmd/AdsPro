<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Adcp_Extras_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array( 'singular' => 'extra', 'plural' => 'extras', 'ajax' => false ) );
    }

    public function get_columns() {
        return array(
            'cb'            => '<input type="checkbox" />',
            'title'         => 'Title',
            'price'         => 'Price',
            'delivery_time' => 'Delivery (Days)',
        );
    }

    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="extra_id[]" value="%s" />', $item['id'] );
    }

    public function column_title( $item ) {
        $edit_url = admin_url( 'admin.php?page=adcp-extra-edit&id=' . $item['id'] );
        $actions = array( 'edit' => sprintf( '<a href="%s">Edit</a>', esc_url( $edit_url ) ) );
        return sprintf( '<strong><a href="%s">%s</a></strong> %s', esc_url( $edit_url ), esc_html( $item['title'] ), $this->row_actions( $actions ) );
    }

    public function column_price( $item ) { 
        // --- THIS IS THE FIX ---
        return '$' . number_format( (float) $item['price'], 2 ); 
        // --- END FIX ---
    }
    
    public function column_delivery_time( $item ) { return $item['delivery_time'] . ' days'; }

    public function column_default( $item, $column_name ) { return esc_html( $item[ $column_name ] ?? '' ); }

    public function prepare_items() {
        $this->_column_headers = array( $this->get_columns(), array(), array() );
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = Adcp_Extras_DB::get_extra_count();

        $this->set_pagination_args( array( 'total_items' => (int) $total_items, 'per_page' => $per_page ) );
        
        $this->items = Adcp_Extras_DB::get_extras(array(
            'number' => $per_page,
            'offset' => ( $current_page - 1 ) * $per_page
        ));
    }
}