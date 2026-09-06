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
 * @since 1.4.9
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit; // Exit if accessed directly.

/**
 * Remove the plugin data for a single site.
 *
 * @since 1.4.9
 * @return void
 */
function aimg_uninstall_site() {
	// Queued admin notices are never worth keeping.
	delete_option( 'aimg_flash_notices' );

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

	foreach ( $templates as $template_id ) {
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
