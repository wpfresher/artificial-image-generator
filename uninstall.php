<?php
/**
 * Uninstall handler.
 *
 * Runs when the plugin is deleted from the WordPress admin. Transient state is
 * always cleared; templates and settings are only removed when the site owner
 * opted in via Image Generator → Settings → Remove Data on Uninstall.
 *
 * Images already added to the Media Library are never touched: they are regular
 * attachments that posts and pages may still reference.
 *
 * @package ArtificialImageGenerator
 * @since 1.5.0
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit; // Exit if accessed directly.

/**
 * Remove the plugin data for a single site.
 *
 * @since 1.5.0
 * @return void
 */
function aimg_uninstall_site() {
	global $wpdb;

	// Queued admin notices and AI usage counters are never worth keeping.
	delete_option( 'aimg_flash_notices' );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_aimg\_flash\_notices\_%' OR option_name LIKE '\_transient\_timeout\_aimg\_flash\_notices\_%' OR option_name LIKE '\_transient\_aimg\_ai\_usage\_%' OR option_name LIKE '\_transient\_timeout\_aimg\_ai\_usage\_%' OR option_name LIKE '\_transient\_aimg\_kick\_%' OR option_name LIKE '\_transient\_timeout\_aimg\_kick\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	// Pending background jobs would otherwise fire with no handler.
	wp_unschedule_hook( 'aimg_generate_featured_image' );
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'aimg_generate_featured_image' );
	}
	delete_post_meta_by_key( '_aimg_generation_job' );

	$settings    = get_option( 'aimg_settings', array() );
	$remove_data = is_array( $settings ) && isset( $settings['remove_data'] ) ? $settings['remove_data'] : 'no';

	if ( 'yes' !== $remove_data ) {
		return;
	}

	// Delete every template. Post meta is removed along with the post.
	$templates = get_posts(
		array(
			'post_type'      => 'aimg_template',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	// The plugin isn't loaded during uninstall, so its delete hooks don't run.
	$upload_dir = wp_upload_dir();

	foreach ( $templates as $template_id ) {
		$preview = (string) get_post_meta( $template_id, '_aimg_preview_image_url', true );

		if ( empty( $upload_dir['error'] ) && 0 === strpos( $preview, trailingslashit( $upload_dir['baseurl'] ) ) ) {
			$relative = substr( $preview, strlen( trailingslashit( $upload_dir['baseurl'] ) ) );

			if ( false === strpos( $relative, '..' ) ) {
				wp_delete_file( trailingslashit( $upload_dir['basedir'] ) . $relative );
			}
		}

		wp_delete_post( $template_id, true );
	}

	delete_option( 'aimg_settings' );
	delete_option( 'aimg_version' );
}

if ( is_multisite() ) {
	$aimg_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $aimg_site_ids as $aimg_site_id ) {
		switch_to_blog( $aimg_site_id );
		aimg_uninstall_site();
		restore_current_blog();
	}
} else {
	aimg_uninstall_site();
}
