<?php

class wp_braintree_post {

    public function __construct() {
	
    }

    static function register_post_type() {
	$labels = array(
	    'name'			 => _x( 'Payments', 'Post Type General Name', 'wp_braintree_lang' ),
	    'singular_name'		 => _x( 'Payment', 'Post Type Singular Name', 'wp_braintree_lang' ),
	    'menu_name'		 => __( 'WP Braintree', 'wp_braintree_lang' ),
	    'parent_item_colon'	 => __( 'Parent Payment:', 'wp_braintree_lang' ),
	    'all_items'		 => __( 'Payments', 'wp_braintree_lang' ),
	    'view_item'		 => __( 'View Payment', 'wp_braintree_lang' ),
	    'add_new_item'		 => __( 'Add New Payment', 'wp_braintree_lang' ),
	    'add_new'		 => __( 'Add New', 'wp_braintree_lang' ),
	    'edit_item'		 => __( 'Edit Payment', 'wp_braintree_lang' ),
	    'update_item'		 => __( 'Update Payment', 'wp_braintree_lang' ),
	    'search_items'		 => __( 'Search Payment', 'wp_braintree_lang' ),
	    'not_found'		 => __( 'Not found', 'wp_braintree_lang' ),
	    'not_found_in_trash'	 => __( 'Not found in Trash', 'wp_braintree_lang' ),
	);

	$menu_icon	 = WPB_URL . '/js/images/wp_braintree.png';
	$args		 = array(
	    'label'			 => __( 'payments', 'wp_braintree_lang' ),
	    'description'		 => __( 'WP Braintree', 'wp_braintree_lang' ),
	    'labels'		 => $labels,
	    'supports'		 => array( 'title', 'editor', 'excerpt', 'revisions', 'custom-fields', ),
	    'hierarchical'		 => false,
	    'public'		 => false,
	    'show_ui'		 => true,
	    'show_in_menu'		 => true,
	    'show_in_nav_menus'	 => true,
	    'show_in_admin_bar'	 => true,
	    'menu_position'		 => 80,
	    'menu_icon'		 => $menu_icon,
	    'can_export'		 => true,
	    'has_archive'		 => false,
	    'exclude_from_search'	 => true,
	    'publicly_queryable'	 => false,
	    'capability_type'	 => 'post',
	    'capabilities'		 => array(
		'create_posts' => false, // Removes support for the "Add New" function
	    ),
	    'map_meta_cap'		 => true,
	);

	register_post_type( 'braintree_payment', $args );
    }

    static function insert_post( $data ) {
	$post			 = array();
	$post[ 'post_title' ]	 = sanitize_text_field( $data[ 'item_name' ] . ' - ' . $data[ 'amount' ] . ', ' . __( 'paid by ', 'wp_braintree_lang' ) . $data[ 'name' ] . ' (' . $data[ 'email' ] . ') ' );
	$post[ 'post_status' ]	 = 'publish';

	$output	 = '';
	$output	 .= __( "<h4>Payment Details</h4>", "wp_braintree_lang" ) . "\n";
	$output	 .= __( "Payment Time: ", "wp_braintree_lang" ) . date( "F j, Y, g:i a", $data[ 'date' ] ) . "\n";
	$output	 .= __( "Transaction ID: ", "wp_braintree_lang" ) . $data[ 'trans_id' ] . "\n";
	$output	 .= __( "Order ID: ", "wp_braintree_lang" ) . $data[ 'order_id' ] . "\n";
//        $output .= __("Description: ", "wp_braintree_lang") . $order_details['charge_description'] . "\n";
	$output	 .= "--------------------------------" . "\n";
	$output	 .= __( "Item Name: ", "wp_braintree_lang" ) . $data[ 'item_name' ] . "\n";
	$output	 .= __( "Amount Paid: ", "wp_braintree_lang" ) . $data[ 'amount' ] . "\n";
	$output	 .= "--------------------------------" . "\n";

	$output .= "\n";

	$output	 .= __( "<h4>Customer Details</h4>", "wp_braintree_lang" ) . "\n";
	$output	 .= __( "Name: ", "" ) . $data[ 'name' ] . "\n";
	$output	 .= __( "E-Mail Address: ", "wp_braintree_lang" ) . $data[ 'email' ] . "\n";

	$post[ 'post_content' ]	 = $output;
	$post[ 'post_type' ]	 = 'braintree_payment';

	return wp_insert_post( $post ); //Return post ID
    }

}
