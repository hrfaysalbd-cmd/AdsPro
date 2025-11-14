<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Adcp_Packages_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => 'package',
            'plural'   => 'packages',
            'ajax'     => false
        ) );
    }

    public function get_columns() {
        return array(
            'cb'           => '<input type="checkbox" />',
            'title'        => 'Title',
            'price'        => 'Price',
            'cycle'        => 'Billing Cycle',
            'allow_coupon' => 'Coupons',
            'created_at'   => 'Created'
        );
    }
    
    public function get_sortable_columns() {
        return array(
            'title' => array('title', false),
            'price' => array('price', false),
            'cycle' => array('cycle', false),
            'created_at' => array('created_at', true)
        );
    }

    public function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="package_id[]" value="%s" />', $item['id']
        );
    }
    
    public function column_title( $item ) {
        $page_slug = 'adcp-package-edit';
        $edit_url = admin_url( 'admin.php?page=' . $page_slug . '&id=' . $item['id'] );
        $delete_url = '#'; // TODO: Add nonce-based delete link

        $actions = array(
            'edit'   => sprintf( '<a href="%s">Edit</a>', esc_url( $edit_url ) ),
            'delete' => sprintf( '<a href="%s" onclick="return confirm(\'Are you sure?\')">Delete</a>', esc_url( $delete_url ) ),
        );
        return sprintf( '<strong><a href="%s">%s</a></strong> %s', esc_url( $edit_url ), esc_html( $item['title'] ), $this->row_actions( $actions ) );
    }

    public function column_price( $item ) {
        // --- THIS IS THE FIX ---
        return '$' . number_format( (float) $item['price'], 2 );
        // --- END FIX ---
    }
    
    public function column_cycle( $item ) {
        return $item['cycle'] === 'monthly' ? 'Monthly' : 'Yearly';
    }
    
    public function column_allow_coupon( $item ) {
        return $item['allow_coupon'] ? 'Yes' : 'No';
    }

    public function column_default( $item, $column_name ) {
        if ( isset( $item[ $column_name ] ) ) {
            return esc_html( $item[ $column_name ] );
        }
        return print_r( $item, true );
    }

    public function prepare_items() {
        global $wpdb;
        $this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
        
        $per_page     = 20;
        $current_page = $this->get_pagenum();
        
        $total_items  = Adcp_Packages_DB::get_package_count(); 

        $this->set_pagination_args( array(
            'total_items' => (int) $total_items,
            'per_page'    => $per_page
        ) );
        
        $orderby = $_GET['orderby'] ?? 'price';
        $order = $_GET['order'] ?? 'ASC';
        
        $this->items = Adcp_Packages_DB::get_packages(array(
            'number' => $per_page,
            'offset' => ($current_page - 1) * $per_page,
            'orderby' => $orderby,
            'order' => $order
        ));
    }
}