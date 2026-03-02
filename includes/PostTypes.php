<?php

namespace ArtificialImageGenerator;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Class PostTypes.
 *
 * Responsible for registering custom post types.
 *
 * @since 1.0.0
 * @package ArtificialImageGenerator
 */
class PostTypes {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
	}

	/**
	 * Register custom post types.
	 *
	 * @since 1.0.0
	 */
	public static function register_cpt() {
		$labels = array(
			'name'               => _x( 'Image Templates', 'post type general name', 'artificial-image-generator' ),
			'singular_name'      => _x( 'Image Template', 'post type singular name', 'artificial-image-generator' ),
			'menu_name'          => _x( 'Image Templates', 'admin menu', 'artificial-image-generator' ),
			'name_admin_bar'     => _x( 'Image Templates', 'add new on admin bar', 'artificial-image-generator' ),
			'add_new'            => _x( 'Add New', 'ticket', 'artificial-image-generator' ),
			'add_new_item'       => __( 'Add New Images Template', 'artificial-image-generator' ),
			'new_item'           => __( 'New Images Template', 'artificial-image-generator' ),
			'edit_item'          => __( 'Edit Image Template', 'artificial-image-generator' ),
			'view_item'          => __( 'View Image Template', 'artificial-image-generator' ),
			'all_items'          => __( 'All Image Templates', 'artificial-image-generator' ),
			'search_items'       => __( 'Search Image Templates', 'artificial-image-generator' ),
			'parent_item_colon'  => __( 'Parent Image Templates:', 'artificial-image-generator' ),
			'not_found'          => __( 'No image templates found.', 'artificial-image-generator' ),
			'not_found_in_trash' => __( 'No image templates found in Trash.', 'artificial-image-generator' ),
		);

		$args = array(
			'labels'              => apply_filters( 'aimg_template_post_type_labels', $labels ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_nav_menus'   => false,
			'query_var'           => false,
			'can_export'          => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_position'       => null,
			'supports'            => array(),
		);

		register_post_type( 'aimg_template', apply_filters( 'aimg_template_post_type_args', $args ) );
	}
}
