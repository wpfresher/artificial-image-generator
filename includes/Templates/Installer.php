<?php

namespace ArtificialImageGenerator\Templates;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Gives a fresh install one published template, so automatic images work without setup.
 *
 * Runs once, only after the first activation, and only while the site has no templates.
 *
 * @since 1.9.1
 * @package ArtificialImageGenerator
 */
class Installer {

	/**
	 * Option set on the first activation; removed once the starter is handled.
	 *
	 * @var string
	 */
	const OPTION = 'aimg_install_starter';

	/**
	 * Starter used for the first template. It has no stock or AI layers, so it never makes requests.
	 *
	 * @var string
	 */
	const STARTER = 'classic';

	/**
	 * Installer constructor.
	 */
	public function __construct() {
		add_action( 'init', array( __CLASS__, 'maybe_create_starter' ), 20 );
	}

	/**
	 * Mark a first activation. A site that ran the plugin before keeps what it has.
	 *
	 * @param bool $fresh Whether this is the first activation on the site.
	 *
	 * @return void
	 */
	public static function activated( $fresh ) {
		if ( $fresh ) {
			add_option( self::OPTION, 1 );
		}
	}

	/**
	 * Create the starter template when a first activation is pending.
	 *
	 * @return int Template ID, or 0 when none was created.
	 */
	public static function maybe_create_starter() {
		if ( ! get_option( self::OPTION ) ) {
			return 0;
		}

		delete_option( self::OPTION );

		if ( self::has_templates() ) {
			return 0;
		}

		/**
		 * Filter whether a fresh install gets a starter template.
		 *
		 * @param bool $create Whether to create it.
		 *
		 * @since 1.9.1
		 */
		if ( ! apply_filters( 'aimg_install_starter', true ) ) {
			return 0;
		}

		$starters = Starters::all();

		if ( empty( $starters[ self::STARTER ] ) ) {
			return 0;
		}

		$id = wp_insert_post(
			array(
				'post_type'   => 'aimg_template',
				'post_title'  => $starters[ self::STARTER ][0],
				'post_status' => 'publish',
			)
		);

		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}

		Repository::save_document( $id, $starters[ self::STARTER ][1] );
		Repository::update_preview( $id );

		return (int) $id;
	}

	/**
	 * Whether the site has any template, in any status but auto-draft.
	 *
	 * @return bool
	 */
	private static function has_templates() {
		$found = get_posts(
			array(
				'post_type'        => 'aimg_template',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);

		return ! empty( $found );
	}
}
