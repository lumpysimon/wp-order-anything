<?php
/*
Plugin Name:  Order Anything
Description:  Set the order of any custom post type using drag n drop.
Version:      0.4
License:      GPL v2 or later
Plugin URI:   https://github.com/lumpysimon/wp-order-anything
Author:       Simon Blackbourn @ Lumpy Lemon
Author URI:   http://simonblackbourn.net
Text Domain:  lumpy_order_anything
Domain Path:  /languages/



	-------
	Credits
	-------

	This plugin is based on "My Page Order" by Andrew Charlton: http://wordpress.org/plugins/my-page-order



	------------
	What it does
	------------

	Allows you to manually specify the order of any custom post type
	in the admin screens and on the front-end of your website
	by dragging n dropping the posts on an easy-to-use admin screen.



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

	0.4 (21st Feb 2016)
	Use get_posts instead of $wpdb->get_results (for compatibility with Polylang plugin)

	0.3 (5th May 2015)
	Test for correct capability to determine if a post type can be ordered

	0.2 (13th Feb 2015)
	Only apply ordering to main query
	Get rid of a load of crap

	0.1 (July 2013)
	Development version. Incomplete. May well break.



	------
	@TODO@
	------

	Investigate prev/next links
	readme.txt
	Inline docs
	Localisation



*/



defined( 'ABSPATH' ) or die();



if ( ! defined( 'LLOA_VERSION' ) ) {
	define( 'LLOA_VERSION', '0.4' );
}

if ( ! defined( 'LLOA_PLUGIN_PATH' ) ) {
	define( 'LLOA_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'LLOA_PLUGIN_DIR' ) ) {
	define( 'LLOA_PLUGIN_DIR', plugin_dir_url( __FILE__ ) );
}



$lumpy_order_anything = new lumpy_order_anything;



class lumpy_order_anything {



	function __construct() {

		add_action( 'admin_menu',          array( $this, 'menu' ) );
		add_action( 'admin_menu',          array( $this, 'create_options_page' ) );
		add_action( 'admin_init',          array( $this, 'settings_init' ) );
		add_action( 'admin_print_scripts', array( $this, 'scripts' ) );
		add_action( 'admin_init',          array( $this, 'register_styles' ) );
		add_action( 'admin_print_styles',  array( $this, 'add_styles' ) );

		add_filter( 'pre_get_posts',       array( $this, 'sort' ) );
		add_filter( 'pre_get_posts',       array( $this, 'admin_sort' ) );

	}



	function types() {

		$opts = $this->get_settings();

		$types = array();

		if ( is_array( $opts ) )
			$types = array_keys( $opts );

		return $types;

	}



	function menu() {

		if ( $types = $this->types() ) {

			foreach ( $types as $type ) {

				$pto = get_post_type_object( $type );

				add_submenu_page(
					'edit.php?post_type=' . $type,
					'Order',
					'Order',
					$pto->cap->edit_posts,
					'order_' . $type,
					array( $this, 'page' )
					);

			}

		}

	}



	function scripts() {

		if ( isset( $_GET['page'] ) and 0 === strpos( $_GET['page'], 'order_' ) ) {

			wp_enqueue_script( 'jquery-ui-core' );
			wp_enqueue_script( 'jquery-ui-sortable' );

			wp_enqueue_script(
				'lumpy-order-anything',
				LLOA_PLUGIN_DIR . 'inc/order-anything.js',
				array( 'jquery' ),
				filemtime( LLOA_PLUGIN_PATH . 'inc/order-anything.js' )
				);

		}

	}



	function register_styles() {

		wp_register_style(
			'lumpy-order-anything',
			LLOA_PLUGIN_DIR . 'inc/order-anything.css',
			null,
			filemtime( LLOA_PLUGIN_PATH . 'inc/order-anything.css' )
			);

	}



	function add_styles() {

		wp_enqueue_style( 'lumpy-order-anything' );

	}



	function page() {

		global $wpdb;

		$type         = $this->get_post_type();
		$plural       = $type->labels->name;
		$plural_low   = strtolower( $plural );
		$singular     = $type->labels->singular_name;
		$singular_low = strtolower( $singular );
		$parent_id    = 0;
		$success      = '';

		if ( isset( $_POST['sub_items'] ) ) {
			$parent_id = $_POST['items'];
		} elseif ( isset( $_POST['hdn_parent_id'] ) ) {
			$parent_id = $_POST['hdn_parent_id'];
		}

		if ( isset( $_POST['btn_return_parent'] ) ) {
			$parents_parent = $wpdb->get_row( $wpdb->prepare( "SELECT post_parent FROM $wpdb->posts WHERE ID = %d ", $_POST['hdn_parent_id'] ), ARRAY_N );
			$parent_id = $parents_parent[0];
		}

		if ( isset( $_POST['order_items'] ) ) {
			$success = $this->update_order();
		}

		$sub_items = $this->get_sub_items( $parent_id );

		?>

		<div class="wrap">

			<form name="lumpy_order_items" method="post" action="">

				<h2>Order <?php echo $plural; ?></h2>

				<?php echo $success; ?>

				<?php if ( $sub_items ) { ?>

					<div id="lumpy_order_sub_items">
						<h3>Order Sub-<?php echo $plural_low; ?></h3>
						<select id="items" name="items">
							<?php echo $sub_items; ?>
						</select>
						<input type="submit" name="sub_items" class="button" id="sub_items" value="Order sub-<?php echo $plural_low; ?>">
					</div>

				<?php } ?>

				<p>Order <?php echo $plural_low; ?> by dragging and dropping them into the desired order.</p>

				<ul id="lumpy_order_list">

					<?php
					$results = $this->post_query( $parent_id );
					foreach ( $results as $row ) {
						echo '<li id="id_' . $row->ID . '" class="lineitem">' . $row->post_title . '</li>';
					}
					?>

				</ul>

				<input type="submit" name="order_items" id="order_items" class="button-primary" value="Order <?php echo $plural_low; ?>" onclick="javascript:orderItems(); return true;">
				<?php echo $this->get_parent_link( $parent_id, $singular_low ); ?>
				&nbsp;&nbsp;<strong id="update_text"></strong>
				<input type="hidden" id="hdn_order_items" name="hdn_order_items">
				<input type="hidden" id="hdn_parent_id" name="hdn_parent_id" value="<?php echo $parent_id; ?>">
			</form>

		</div>

		<?php

	}



	function get_post_type() {

		return get_post_type_object( $this->get_type() );

	}



	function get_type() {

		return sanitize_title( substr( $_GET['page'], 6 ) );

	}



	function get_target() {

		$type = $this->get_type();

		return 'edit.php?post_type=' . $type . '&page=order_' . $type;

	}



	function update_order() {

		if ( isset( $_POST['hdn_order_items'] ) and '' != $_POST['hdn_order_items'] ) {

			global $wpdb;

			$hdn_order_items = $_POST['hdn_order_items'];
			$ids             = explode( ',', $hdn_order_items );
			$result          = count( $ids );

			for ( $i = 0; $i < $result; $i++ ) {
				$id = str_replace( 'id_', '', $ids[$i] );
				$wpdb->query(
					$wpdb->prepare( "
						UPDATE $wpdb->posts
						SET menu_order = %d
						WHERE id = %d
						",
						$i,
						$id
						)
					);
			}

			return '<div id="message" class="updated fade"><p>Order updated.</p></div>';

		} else {

			return '<div id="message" class="updated fade"><p>An error occured, order could not be saved.</p></div>';

		}

	}



	function get_sub_items( $parent_id ) {

		global $wpdb;

		$out     = '';
		$results = $this->post_query( $parent_id );
		$type    = $this->get_type();

		foreach ( $results as $row ) {

			$post_count = $wpdb->get_row(
				$wpdb->prepare( "
					SELECT count(*) as postscount
					FROM $wpdb->posts
					WHERE post_parent = %d
						AND post_type = '%s'
						AND post_status != 'trash'
						AND post_status != 'auto-draft'
					",
					$row->ID,
					$type
					),
				ARRAY_N
				);

			if ( $post_count[0] > 1 ) {
		    	$out .= '<option value="' . $row->ID . '">' . $row->post_title . '</option>';
		    }

		}

		return $out;

	}



	function post_query( $parent_id ) {

		global $wpdb;

		$type = $this->get_type();

		$args = array(
			'post_type'      => $type,
			'post_status'    => array( 'publish', 'draft' ),
			'post_parent'    => $parent_id,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'posts_per_page' => -1
			);

		return get_posts( $args );

	}



	function get_parent_link( $parent_id, $item ) {

		if ( 0 != $parent_id )
			return '&nbsp;&nbsp;<input type="submit" class="button" id="btn_return_parent" name="btn_return_parent" value="Return to parent ' . $item . '">';

		return;

	}



	function sort( $wp_query ) {

		if ( is_admin() )
			return;

		if ( ! $wp_query->is_main_query() )
			return;

		if ( ! isset( $wp_query->query['post_type'] ) )
			return;

		if ( ! in_array( $wp_query->query['post_type'], $this->types() ) )
			return;

		$wp_query->set( 'orderby', 'menu_order' );
		$wp_query->set( 'order', 'ASC' );

	}



	function admin_sort( $wp_query ) {

		if ( ! is_admin() )
			return;

		if ( ! isset( $wp_query->query['post_type'] ) )
			return;

		if ( ! in_array( $wp_query->query['post_type'], $this->types() ) )
			return;

		$wp_query->set( 'orderby', 'menu_order' );
		$wp_query->set( 'order', 'ASC' );

	}



	/**
	 * retrieve the options from the database
	 * @return array 'lumpy-order-anything' options
	 */
	function get_settings() {

		return get_option( 'lumpy-order-anything' );

	}



	/**
	 * use the WordPress settings API to initiate the various settings
	 * @return null
	 */
	function settings_init() {

		register_setting(
			'lumpy-order-anything',
			'lumpy-order-anything',
			array( $this, 'validate' )
			);

	}



	/**
	 * make sure that no dodgy stuff is trying to sneak through
	 * @param  array $input options to validate
	 * @return array        validated options
	 */
	function validate( $inputs ) {

		$new = array();

		if ( $inputs ) {
			foreach ( $inputs as $k => $v ) {
				$new[$k] = absint( $v );
			}
		}

		return $new;

	}



	/**
	 * update the options in the database
	 * @param  array $opts new options settings to save
	 * @return null
	 */
	function update_settings( $opts ) {

		update_option( 'lumpy-order-anything', $opts );

	}



	function create_options_page() {

		add_options_page(
			'Order Anything Settings',
			'Order Anything',
			'manage_options',
			'lumpy-order-anything',
			array( $this, 'render_options_page' )
			);

	}



	function render_options_page() {

		$opts = self::get_settings();

		$types = array(
			'page' => get_post_type_object( 'page' )
			);

		if ( $cpts = get_post_types( array( '_builtin' => false ), 'objects' ) ) {
			$types = array_merge( $types, $cpts );
		}

		?>

		<div class="wrap">

		<?php screen_icon( 'options-general' ); ?>
		<h2>Order Anything Configuration</h2>

		<p>Choose which post types can have their order manually set.</p>

		<form method="post" action="options.php">

			<?php settings_fields( 'lumpy-order-anything' ); ?>

			<table class="form-table">

				<tbody>

					<?php foreach ( $types as $type ) { ?>

						<tr valign="top">
							<th scope="row"><?php echo $type->labels->name; ?></th>
							<td>
								<input name="lumpy-order-anything[<?php echo $type->name; ?>]" type="checkbox" value="1" <?php checked( isset( $opts[$type->name] ) ); ?>>
							</td>
						</tr>

					<?php } ?>

				</tbody>

			</table>

			<p class="submit">
				<input class="button-primary" name="lumpy-order-anything-submit" type="submit" value="Save Settings">
			</p>

		</form>

		</div>

		<?php

	}



} // class
