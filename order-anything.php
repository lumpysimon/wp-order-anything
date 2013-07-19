<?php
/*
Plugin Name:  Order Anything
Description:  Set the order of any non-hierarchical custom post type using drag n drop.
Version:      0.0001dev
License:      GPL v2 or later
Plugin URI:   https://github.com/lumpysimon/wp-order-anything
Author:       Simon Blackbourn @ Lumpy Lemon
Author URI:   https://twitter.com/lumpysimon
Author Email: simon@lumpylemon.co.uk
Text Domain:  ll_order_anything
Domain Path:  /languages/


	-------
	Credits
	-------

	This plugin is based on "My Page Order" by Andrew Charlton (http://www.geekyweekly.com/mypageorder)



	------------
	What it does
	------------

	Allows you to manually specify the order of any non-hierarchical custom post type
	in the admin screens and on the front-end of your website.



	-------
	License
	-------

	Copyright (c) Lumpy Lemon Ltd. All rights reserved.

	Released under the GPL license:
	http://www.opensource.org/licenses/gpl-license.php

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.



	---------
	Changelog
	---------

	0.0001dev
	Development version. Incomplete. May well break.



	------
	@TODO@
	------

	Options page to replace $types array
	Investigate prev/next links
	Post type name in page title & h2
	readme.txt
	Localisation



*/



defined( 'ABSPATH' ) or die();



if ( ! defined( 'LLPD_PLUGIN_PATH' ) )
	define( 'LLPD_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

if ( ! defined( 'LLPD_PLUGIN_DIR' ) )
	define( 'LLPD_PLUGIN_DIR', plugin_dir_url( __FILE__ ) );



lumpy_reorder_anything::get_instance();



class lumpy_reorder_anything {



	private static $instance = null;

	var $types = array( 'page', 'farm', 'gallery', 'video', 'product', 'staff' );



	public static function get_instance() {

		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;

	}



	function __construct() {

		add_action( 'admin_menu',          array( $this, 'menu' ) );
		add_action( 'admin_print_scripts', array( $this, 'scripts' ) );
		add_action( 'admin_init',          array( $this, 'register_styles' ) );
		add_action( 'admin_print_styles',  array( $this, 'add_styles' ) );

		add_filter( 'pre_get_posts',       array( $this, 'sort' ) );
		add_filter( 'pre_get_posts',       array( $this, 'admin_sort' ) );

	}



	function menu() {

		foreach ( $this->types as $type ) {

			add_submenu_page(
				'edit.php?post_type=' . $type,
				'Order',
				'Order',
				'edit_posts',
				'reorder_' . $type,
				array( $this, 'page' )
				);

		}

	}



	function scripts() {

		if ( isset( $_GET['page'] ) and 0 === strpos( $_GET['page'], 'reorder_' ) ) {

			wp_enqueue_script( 'jquery-ui-core' );
			wp_enqueue_script( 'jquery-ui-sortable' );

		}

	}



	function register_styles() {

		wp_register_style(
			'll-reorder-anything',
			LLPD_PLUGIN_DIR . 'inc/style.css',
			null,
			filemtime( LLPD_PLUGIN_PATH . 'inc/style.css' )
			);

	}



	function add_styles() {

		wp_enqueue_style( 'll-reorder-anything' );

	}



	function page() {

		global $wpdb;

		$parent_id = 0;
		$success  = '';

		if ( isset( $_POST['btn_sub_pages'] ) ) {
			$parent_id = $_POST['pages'];
		} elseif ( isset( $_POST['hdn_parent_id'] ) ) {
			$parent_id = $_POST['hdn_parent_id'];
		}

		if ( isset( $_POST['btn_return_parent'] ) ) {
			$parentsParent = $wpdb->get_row( $wpdb->prepare( "SELECT post_parent FROM $wpdb->posts WHERE ID = %d ", $_POST['hdn_parent_id'] ), ARRAY_N );
			$parent_id = $parentsParent[0];
		}

		if ( isset( $_POST['btn_order_pages'] ) ) {
			$success = $this->update_order();
		}

		$subPageStr = $this->get_sub_pages( $parent_id );

		?>

		<div class="wrap">

			<form name="frm_reorder_pages" method="post" action="">

				<h2>Order</h2>

				<?php echo $success; ?>

				<p>Choose an item from the drop down to order its subitems or order the items on this level by dragging and dropping them into the desired order.</p>

				<?php if ( '' != $subPageStr ) { ?>

					<h3>Order Subitems</h3>
					<select id="pages" name="pages">
						<?php echo $subPageStr; ?>
					</select>
					&nbsp;<input type="submit" name="btn_sub_pages" class="button" id="btn_sub_pages" value="Order Subpages">

				<?php } ?>

				<h3>Order</h3>

				<ul id="ll_reorder_list">

					<?php
					$results = $this->page_query( $parent_id );
					foreach ( $results as $row ) {
						echo "<li id='id_$row->ID' class='lineitem'>$row->post_title</li>";
					}
					?>

				</ul>

				<input type="submit" name="btn_order_pages" id="btn_order_pages" class="button-primary" value="Click to Order" onclick="javascript:orderPages(); return true;">
				<?php echo $this->get_parent_link( $parent_id ); ?>
				&nbsp;&nbsp;<strong id="update_text"></strong>
				<input type="hidden" id="hdn_reorder_pages" name="hdn_reorder_pages">
				<input type="hidden" id="hdn_parent_id" name="hdn_parent_id" value="<?php echo $parent_id; ?>">
			</form>

		</div>

		<script type="text/javascript">
		// <![CDATA[

			function reorder_pages_addloadevent(){
				jQuery("#ll_reorder_list").sortable({
					placeholder: "sortable-placeholder",
					revert: false,
					tolerance: "pointer"
				});
			};

			addLoadEvent(reorder_pages_addloadevent);

			function orderPages() {
				jQuery("#update_text").html("Updating Order...");
				jQuery("#hdn_reorder_pages").val(jQuery("#ll_reorder_list").sortable("toArray"));
			}

		// ]]>
		</script>

		<?php

	}



	function get_type() {

		return substr( $_GET['page'], 8 );

	}



	function get_target() {

		$type = $this->get_type();

		return 'edit.php?post_type=' . $type . '&page=reorder_' . $type;

	}



	function update_order() {

		if ( isset( $_POST['hdn_reorder_pages'] ) and '' != $_POST['hdn_reorder_pages'] ) {

			global $wpdb;

			$hdn_reorder_pages = $_POST['hdn_reorder_pages'];
			$ids              = explode( ',', $hdn_reorder_pages );
			$result           = count( $ids );

			for ( $i = 0; $i < $result; $i++ ) {
				$str = str_replace( 'id_', '', $ids[$i] );
				$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET menu_order = %d WHERE id = %d ", $i, $str ) );
			}

			return '<div id="message" class="updated fade"><p>Order updated.</p></div>';

		} else {

			return '<div id="message" class="updated fade"><p>An error occured, order could not be saved.</p></div>';

		}

	}



	function get_sub_pages( $parent_id ) {

		global $wpdb;

		$subPageStr = "";
		$results    = $this->page_query( $parent_id );
		$type = $this->get_type();

		foreach ( $results as $row ) {

			$postCount=$wpdb->get_row($wpdb->prepare("SELECT count(*) as postsCount FROM $wpdb->posts WHERE post_parent = %d and post_type = '" . $type . "' AND post_status != 'trash' AND post_status != 'auto-draft' ", $row->ID) , ARRAY_N);
			if($postCount[0] > 0)
		    	$subPageStr = $subPageStr."<option value='$row->ID'>".__($row->post_title)."</option>";

		}

		return $subPageStr;

	}



	function page_query( $parent_id ) {

		global $wpdb;

		$type = $this->get_type();

		return $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE post_parent = %d and post_type = '" . $type . "' AND post_status != 'trash' AND post_status != 'auto-draft' ORDER BY menu_order ASC", $parent_id) );

	}



	function get_parent_link( $parent_id ) {

		if ( 0 != $parent_id ) {

			return "&nbsp;&nbsp;<input type='submit' class='button' id='btn_return_parent' name='btn_return_parent' value='Return to parent page' />";

		} else {

			return '';

		}

	}



	function sort( $wp_query ) {

		if ( is_admin() )
			return;

		if ( ! in_array( $wp_query->query['post_type'], $this->types ) )
			return;

		$wp_query->set( 'orderby', 'menu_order' );
		$wp_query->set( 'order', 'ASC' );

	}



	function admin_sort( $wp_query ) {

		if ( ! is_admin() )
			return;

		if ( ! in_array( $wp_query->query['post_type'], $this->types ) )
			return;

		$wp_query->set( 'orderby', 'menu_order' );
		$wp_query->set( 'order', 'ASC' );

	}



}
