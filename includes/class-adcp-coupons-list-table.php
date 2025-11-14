<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Adcp_Coupons_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array( 'singular' => 'coupon', 'plural' => 'coupons', 'ajax' => false ) );
    }

    public function get_columns() {
        return array(
            'cb'     => '<input type="checkbox" />',
            'code'   => 'Code',
            'type'   => 'Type',
            'value'  => 'Value',
            'usage'  => 'Usage',
            'status' => 'Status',
        );
    }

    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="coupon_id[]" value="%s" />', $item['id'] );
    }

    public function column_code( $item ) {
        $edit_url = admin_url( 'admin.php?page=adcp-coupon-edit&id=' . $item['id'] );
        $actions = array( 'edit' => sprintf( '<a href="%s">Edit</a>', esc_url( $edit_url ) ) );
        return sprintf( '<strong><a href="%s">%s</a></strong> %s', esc_url( $edit_url ), esc_html( $item['code'] ), $this->row_actions( $actions ) );
    }

    public function column_type( $item ) { return ucfirst($item['type']); }
    
    public function column_status( $item ) { return ucfirst($item['status']); }

    public function column_value( $item ) {
        return $item['type'] === 'percent' ? $item['value'] . '%' : '$' . number_format( $item['value'], 2 );
    }
    
    public function column_usage( $item ) {
        $used = $item['used_count'];
        $max = $item['max_uses'] ? $item['max_uses'] : '&infin;';
        return "$used / $max";
    }

    public function column_default( $item, $column_name ) { return esc_html( $item[ $column_name ] ?? '' ); }

    public function prepare_items() {
        $this->_column_headers = array( $this->get_columns(), array(), array() );
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = Adcp_Coupons_DB::get_coupon_count();

        $this->set_pagination_args( array( 'total_items' => $total_items, 'per_page' => $per_page ) );
        
        $this->items = Adcp_Coupons_DB::get_coupons(array(
            'number' => $per_page,
            'offset' => ( $current_page - 1 ) * $per_page
        ));
    }
}