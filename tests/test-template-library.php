<?php
/**
 * Starters, duplicate, export and template list actions (M3.3).
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Admin\Actions;
use ArtificialImageGenerator\Rendering\GdRenderer;
use ArtificialImageGenerator\Templates\Repository;
use ArtificialImageGenerator\Templates\Starters;

/**
 * @covers \ArtificialImageGenerator\Templates\Starters
 * @covers \ArtificialImageGenerator\Templates\Repository
 * @covers \ArtificialImageGenerator\Admin\Actions
 */
class Test_Template_Library extends AIMG_TestCase {

	/**
	 * Run a list action and return where it redirects.
	 *
	 * @param string $action      Action.
	 * @param int    $template_id Template ID.
	 *
	 * @return string
	 */
	private function run_action( $action, $template_id ) {
		$_GET     = array(
			'do'       => $action,
			'template' => $template_id,
			'_wpnonce' => wp_create_nonce( 'aimg_template_' . $action . '_' . $template_id ),
		);
		$_REQUEST = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The handler verifies it.

		$location = '';
		$stop     = function ( $url ) use ( &$location ) {
			$location = $url;
			throw new Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $stop );

		try {
			Actions::template_action();
		} catch ( Exception $e ) {
			unset( $e );
		}

		remove_filter( 'wp_redirect', $stop );

		return $location;
	}

	public function test_every_starter_renders() {
		$starters = Starters::all();

		$this->assertCount( 10, $starters );

		foreach ( $starters as $id => $starter ) {
			$this->assertNotEmpty( $starter[0], $id );
			$this->assertNotEmpty( $starter[1]['layers'], $id );

			$image = GdRenderer::render( $starter[1], array( 'title' => 'A starter title that wraps onto more than one line' ) );
			$this->assertNotFalse( $image, $id );
			$this->assertSame( 1200, imagesx( $image ), $id );
		}
	}

	public function test_duplicating_a_1_x_template_creates_a_studio_copy() {
		$template = $this->create_template(
			array(
				'bg_colors'        => '#123456',
				'overlay_position' => 'top-left',
			)
		);

		$copy = Repository::duplicate( $template );

		$this->assertIsInt( $copy );
		$this->assertSame( 'draft', get_post_status( $copy ) );
		$this->assertSame( 'Test template (copy)', get_the_title( $copy ) );
		$this->assertTrue( Repository::has_document( $copy ) );
		$this->assertSame( Repository::get_document( $template ), Repository::get_document( $copy ) );
		$this->assertSame( '#123456', get_post_meta( $copy, '_aimg_bg_colors', true ), '1.x settings are kept for downgrades.' );
		$this->assertSame( 'top-left', get_post_meta( $copy, '_aimg_overlay_position', true ) );
		$this->assertNotEmpty( get_post_meta( $copy, '_aimg_preview_image_url', true ) );
	}

	public function test_duplicating_a_studio_template_copies_the_document() {
		$template = $this->create_template();
		Repository::save_document( $template, Starters::all()['magazine'][1] );

		$response = $this->rest( 'POST', '/aimg/v1/templates/' . $template . '/duplicate' );

		$this->assertSame( 201, $response->get_status() );
		$this->assertTrue( $response->get_data()['hasDocument'] );
		$this->assertSame( Repository::get_document( $template ), $response->get_data()['document'] );
	}

	public function test_export_is_portable() {
		$template = $this->create_template();
		$export   = Repository::export( $template );

		$this->assertSame( 2, $export['aimgTemplate'] );
		$this->assertSame( 'Test template', $export['title'] );
		$this->assertSame( 'background', $export['document']['layers'][0]['type'] );
	}

	public function test_imported_documents_are_sanitized() {
		$data = $this->rest(
			'POST',
			'/aimg/v1/templates/sanitize',
			array(
				'document' => array(
					'layers' => array(
						array( 'type' => '<script>' ),
						array( 'type' => 'text' ),
					),
				),
			)
		)->get_data();

		$this->assertSame( array( 'text' ), wp_list_pluck( $data['document']['layers'], 'type' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 403, $this->rest( 'POST', '/aimg/v1/templates/sanitize', array( 'document' => array() ) )->get_status() );
	}

	public function test_list_actions() {
		$template = $this->create_template();

		$this->assertStringContainsString( 'page=image-generator', $this->run_action( 'default', $template ) );
		$this->assertSame( $template, (int) aimg_get_settings( 'default_template_id' ) );

		$this->run_action( 'undefault', $template );
		$this->assertSame( 0, (int) aimg_get_settings( 'default_template_id' ) );

		$this->run_action( 'duplicate', $template );
		$this->assertCount( 2, aimg_get_templates( array( 'post_status' => 'any' ) ) );

		$this->run_action( 'delete', $template );
		$this->assertNull( get_post( $template ) );
	}

	public function test_list_actions_need_permission() {
		$template = $this->create_template();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->run_action( 'delete', $template );

		$this->assertNotNull( get_post( $template ) );
	}
}
