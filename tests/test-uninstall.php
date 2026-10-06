<?php
/**
 * Uninstall tests.
 *
 * @package ArtificialImageGenerator
 */

/**
 * Uninstall cleanup.
 */
class Test_Uninstall extends AIMG_TestCase {

	public function test_pending_jobs_are_removed_and_templates_kept() {
		$template = $this->create_template();
		$post_id  = self::factory()->post->create();

		wp_schedule_single_event( time(), 'aimg_generate_featured_image', array( $post_id, 'ai', false ) );
		update_post_meta( $post_id, '_aimg_generation_job', array( $post_id, 'ai', false ) );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'artificial-image-generator/artificial-image-generator.php' );
		}
		require dirname( __DIR__ ) . '/uninstall.php';

		$this->assertFalse( wp_next_scheduled( 'aimg_generate_featured_image', array( $post_id, 'ai', false ) ) );
		$this->assertSame( '', get_post_meta( $post_id, '_aimg_generation_job', true ) );
		$this->assertNotNull( get_post( $template ) );
	}
}
