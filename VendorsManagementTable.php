<?php
/**
 * Vendors Management page
 *
 * @package WCVendors/Admin
 */

namespace WCVendors\Admin;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class VendorsManagementTable extends \WP_List_Table{
    public function __construct() {
        parent::__construct( [
            'singular' => __( 'Vendor', 'wc-vendor' ), 
            'plural'   => __( 'Vendors', 'wc-vendor' ), 
            'ajax'     => false 
    
        ] );
    }
    /**
     * 
     * Get all vendor
     * @since 1.0.0
	 * @version 1.0.0
     * @param mixed
     * @return array
     */

    function getVendors($order = '',$orderby='', $search_term='',$page_number=1,$per_page=1){
        global $wpdb;

        $s = '';
        if(! empty($search_term)){
            $s.=" AND (u.user_nicename LIKE '%$search_term%'  OR u.user_email LIKE '%$search_term%'
             OR (m.meta_value LIKE '%$search_term%' AND m.meta_key='pv_shop_name')) ";
        }

        $sql = "SELECT *, m.meta_value AS shopname 
        FROM $wpdb->users u 
        INNER JOIN $wpdb->usermeta m ON m.user_id = u.ID
        WHERE m.meta_key = 'wp_capabilities'
        AND m.meta_value LIKE '%vendor%' $s";
        $sortable_columns = $this->get_sortable_columns();
       
        if ( ! empty( $orderby ) && $orderby!=='user_shop_name' && array_key_exists($orderby, $sortable_columns)) {
            $sql .= ' ORDER BY u.' . esc_sql( $orderby );
            $sql .= ! empty($order ) ? ' ' . esc_sql( $order ) : ' ASC';
        }
        if(! empty($orderby) && $orderby==='user_shop_name'){
            $sql .= "OR m.meta_key='pv_shop_name' GROUP BY u.ID ORDER BY shopname ";
            $sql .= ! empty($order ) ? ' ' . esc_sql( $order ) : ' ASC';
        }
        $sql .= " LIMIT $per_page";

        $sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;
        $vendors = $wpdb->get_results($sql,'ARRAY_A');

        return $vendors;
    }
    public function prepare_items(){
       
        $columns  = $this->get_columns();
		$hidden   = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

        $order = isset($_REQUEST['order']) ? trim($_REQUEST['order'] ) : '';
        $orderby = isset($_REQUEST['orderby']) ? trim($_REQUEST['orderby'] ) : '';
        $search_term = isset($_REQUEST['s']) ? trim($_REQUEST['s'] ) : '';
		$this->process_bulk_action();

		$this->_column_headers = array( $columns,$hidden, $sortable );

        $per_page     = $this->get_items_per_page( 'vendor_per_page');
        $current_page = $this->get_pagenum();
        $vendors = $this->getVendors($order,$orderby,$search_term,$current_page,$per_page);
        $total_items  = count($vendors);
        
        $this->set_pagination_args( [
          'total_items' => $total_items, 
          'per_page'    => $per_page 
        ] );
        
        $this->items =  $vendors;
        
        //var_dump($this->items);
        
    }
    public function column_cb( $item ) {
        return sprintf(
          '<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['ID']
        );
    }
    public function get_columns(){
        $columns = [
            'cb'      => '<input type="checkbox" />',
            'user_nicename'    => __( 'Name','wc-vendors' ),
            'user_email' => __( 'Email','wc-vendors' ),
            'user_registered' => __('Registered Date','wc-vendor'),
            'user_shop_name'    => __('Shop name','vc-vendor')
          ];
        
          return $columns;
    }
    public function column_default($item,$columns_name){
        $shop_name = get_user_meta($item['ID'],'pv_shop_name',true);
        switch($columns_name){
            case 'user_nicename':
            case 'user_email':
            case 'user_registered' :
                return $item[$columns_name];
            case 'user_shop_name' :
                return $shop_name;
                
            default : return '(No) value';
        }
        

    }

    public function process_bulk_action(){


    }

    /**
     * Columns to make sortable.
     *
     * @return array
     */
    public function get_sortable_columns() {
        $sortable_columns = array(
        'user_email' => array( 'user_email', true ),
        'user_registered' => array('user_registered',true),
        'user_shop_name' => array('user_shop_name',true)
        );
    
        return $sortable_columns;
    }

    public function no_items() {
        _e( 'No vendor avaliable.', 'wc-vendor' );
    }

      /**
     * Method for name column
     *
     * @param array $item an array of DB data
     *
     * @return string
     */
    function column_name( $item ) {

        // create a nonce
        $delete_nonce = wp_create_nonce( 'sp_delete_customer' );
    
        $title = '<strong>' . $item['name'] . '</strong>';
    
        $actions = [
        'delete' => sprintf( '<a href="?page=%s&action=%s&customer=%s&_wpnonce=%s">Delete</a>', esc_attr( $_REQUEST['page'] ), 'delete', absint( $item['ID'] ), $delete_nonce )
        ];
    
        return $title . $this->row_actions( $actions );
    }


	/**
	 * Defines the hidden columns
	 *
	 * @access public
	 * @since 1.0.0
	 * @version 1.0.0
	 * @return array $columns
	 */
	public function get_hidden_columns() {
		// get user hidden columns
		$hidden = get_hidden_columns( $this->screen );

		$new_hidden = array();

		foreach ( $hidden as $k => $v ) {
			if ( ! empty( $v ) ) {
				$new_hidden[] = $v;
			}
		}

		return array_merge( array(), $new_hidden );
	}

    /**
     * Delete a Vendor record.
     *
     * @param int $id Vendors ID
     */
    public static function delete_customer( $id ) {
        global $wpdb;
    
        $wpdb->delete(
        "{$wpdb->prefix}customers",
        [ 'ID' => $id ],
        [ '%d' ]
        );
    }

    /**
     * Returns an associative array containing the bulk action
     *
     * @return array
     */
    public function get_bulk_actions() {
        $actions = [
        'bulk-delete' => 'Delete',
        'bulk-disable' => 'Disable',
        'bulk-enable' => 'Enable',
        'bulk-approve' => 'Approve',
        'bulk-unapprove' => 'Unapprove'
        ];
    
        return $actions;
    }

    public function get_views() {
		$views = array(
			'all'  => '<li class="all"><a href="' . admin_url( 'admin.php?page=wcv-vendors-management' ) . '">' . __( 'All', 'wc-vendors' ) . '</a></li>',
			'accepted'  => '<li class="all"><a href="' . admin_url( 'admin.php?page=wcv-vendors-management?vendor_satus=accepted' ) . '">' . __( 'Accepted Vendors', 'wc-vendors' ) . '</a></li>',
			'pendding' => '<li class="all"><a href="' . admin_url( 'admin.php?page=wcv-vendors-management?vendor_satus=pending' ) . '">' . __( 'Pending Vendors', 'wc-vendors' ) . '</a></li>',
		);

		return $views;
	}
    
    
}