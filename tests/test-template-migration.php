<?php
/**
 * Background migration of 1.x templates to v2 documents.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Templates\Migration;
use ArtificialImageGenerator\Templates\Migrator;
use ArtificialImageGenerator\Templates\Repository;

/**
 * @covers \ArtificialImageGenerator\Templates\Migrator
 */
class Test_Template_Migration extends AIMG_TestCase {

	/**
	 * Files to delete after each test.
	 *
	 * @var string[]
	 */
	private $files = array();

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();

		if ( ! aimg_can_render() ) {
			$this->markTestSkipped( 'GD with FreeType is not available.' );
		}

		delete_option( Migrator::OPTION );
		wp_clear_scheduled_hook( Migrator::HOOK );
		add_filter( 'aimg_use_action_scheduler', '__return_false' );
	}

	/**
	 * Tear down each test.
	 */
	public function tear_down() {
		foreach ( $this->files as $file ) {
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}

		wp_clear_scheduled_hook( Migrator::HOOK );

		parent::tear_down();
	}

	/**
	 * An overlay attachment.
	 *
	 * @param string $type png or jpeg.
	 *
	 * @return int Attachment ID.
	 */
	private function overlay( $type = 'png' ) {
		$image = imagecreatetruecolor( 120, 80 );
		imagefilledrectangle( $image, 10, 10, 60, 40, imagecolorallocate( $image, 250, 200, 0 ) );

		ob_start();
		'jpeg' === $type ? imagejpeg( $image ) : imagepng( $image );
		$upload = wp_upload_bits( 'aimg-migration-' . $type . '.' . ( 'jpeg' === $type ? 'jpg' : 'png' ), null, ob_get_clean() );
		imagedestroy( $image );

		$this->files[] = $upload['file'];

		return self::factory()->attachment->create_object( $upload['file'], 0, array( 'post_mime_type' => 'image/' . $type ) );
	}

	/**
	 * A 1.x template with small canvases, so the checks stay fast.
	 *
	 * @param array $meta Meta without the `_aimg_` prefix.
	 *
	 * @return int
	 */
	private function legacy_template( $meta = array() ) {
		return $this->create_template(
			$meta + array(
				'bg_colors'       => '#aa0000,#00aa00',
				'width'           => '300',
				'height'          => '160',
				'title_font_size' => '20',
			)
		);
	}

	public function test_a_1_x_template_gets_a_document_and_keeps_its_meta() {
		$first    = $this->overlay();
		$second   = $this->overlay();
		$template = $this->legacy_template(
			array(
				'is_overlay_image' => 'yes',
				'overlay_images'   => wp_json_encode( array( $first, $second ) ),
				'overlay_position' => 'bottom-right',
			)
		);
		$meta     = get_post_meta( $template );
		$expected = Migration::from_template( $template );

		$this->assertTrue( Migrator::migrate( $template ) );
		$this->assertTrue( Repository::has_document( $template ) );
		$this->assertSame( $expected, Repository::get_document( $template ) );

		unset( $meta[ Repository::META ] );
		$after = get_post_meta( $template );
		unset( $after[ Repository::META ] );
		$this->assertSame( $meta, $after, 'The 1.x meta is not changed.' );
	}

	public function test_a_template_with_a_document_is_left_alone() {
		$template                         = $this->legacy_template();
		$document                         = Migration::from_template( $template );
		$document['canvas']['background'] = '#123456';
		Repository::save_document( $template, $document );

		$this->assertTrue( Migrator::migrate( $template ) );
		$this->assertSame( '#123456', Repository::get_document( $template )['canvas']['background'] );
	}

	public function test_templates_that_would_change_are_skipped() {
		$jpeg   = $this->legacy_template(
			array(
				'is_overlay_image' => 'yes',
				'overlay_images'   => wp_json_encode( array( $this->overlay( 'jpeg' ) ) ),
			)
		);
		$colors = $this->legacy_template( array( 'bg_colors' => '#aa0000,rgb(1,2,3)' ) );

		$this->assertSame( 'aimg_render_mismatch', Migrator::migrate( $jpeg )->get_error_code(), '1.x skipped JPEG overlays.' );
		$this->assertSame( 'aimg_invalid_colors', Migrator::migrate( $colors )->get_error_code() );
		$this->assertFalse( Repository::has_document( $jpeg ) );
		$this->assertFalse( Repository::has_document( $colors ) );
	}

	public function test_filtered_render_args_are_not_migrated() {
		$template = $this->legacy_template();
		add_filter( 'aimg_template_render_args', '__return_empty_array' );

		$this->assertSame( 'aimg_render_args_filtered', Migrator::migrate( $template )->get_error_code() );

		remove_filter( 'aimg_template_render_args', '__return_empty_array' );
	}

	public function test_run_migrates_in_batches_and_reports_skipped_templates() {
		$ids = array();
		for ( $i = 0; $i < Migrator::BATCH + 2; $i++ ) {
			$ids[] = $this->legacy_template();
		}
		$skipped = $this->legacy_template( array( 'bg_colors' => 'nope' ) );

		Migrator::maybe_schedule();
		$this->assertNotFalse( wp_next_scheduled( Migrator::HOOK ) );

		Migrator::run();
		$this->assertFalse( Migrator::state()['done'] );
		wp_clear_scheduled_hook( Migrator::HOOK );

		Migrator::run();
		$state = Migrator::state();

		$this->assertTrue( $state['done'] );
		$this->assertSame( AIMG_VERSION, $state['version'] );
		$this->assertSame( count( $ids ), $state['migrated'] );
		$this->assertSame( array( $skipped => 'aimg_invalid_colors' ), $state['skipped'] );
		$this->assertFalse( wp_next_scheduled( Migrator::HOOK ), 'Nothing is scheduled once done.' );

		foreach ( $ids as $id ) {
			$this->assertTrue( Repository::has_document( $id ) );
		}

		$health = Migrator::site_health_test();
		$this->assertSame( 'recommended', $health['status'] );
		$this->assertStringContainsString( 'edit=' . $skipped, $health['description'] );

		Migrator::maybe_schedule();
		$this->assertFalse( wp_next_scheduled( Migrator::HOOK ), 'A finished migration is not scheduled again.' );
	}

	public function test_site_health_is_good_when_everything_is_migrated() {
		Migrator::run();

		$this->assertTrue( Migrator::state()['done'] );
		$this->assertSame( 'good', Migrator::site_health_test()['status'] );
	}
}
