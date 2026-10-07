<?php
/**
 * Template saving, rendering and deletion.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Generator;
use ArtificialImageGenerator\Templates\Repository;

/**
 * @covers \ArtificialImageGenerator\Generator
 */
class Test_Templates extends AIMG_TestCase {

	public function test_draft_templates_are_not_rendered() {
		$id = $this->create_template();
		$this->assertNotFalse( Generator::get_render_args( $id ) );

		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'draft',
			)
		);

		$this->assertFalse( Generator::get_render_args( $id ) );
		$this->assertSame( 400, $this->rest( 'POST', '/aimg/v1/generate', array( 'template_id' => $id ) )->get_status() );
	}

	public function test_template_title_fallback_is_plain_text() {
		$id = $this->create_template();
		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => 'Cats &amp; Dogs',
			)
		);

		$this->assertSame( 'Cats & Dogs', Generator::get_render_args( $id )['title'] );
	}

	public function test_deleting_a_template_deletes_its_preview() {
		$id  = $this->create_template();
		$url = Repository::update_preview( $id );

		$upload = wp_upload_dir();
		$path   = str_replace( $upload['baseurl'], $upload['basedir'], $url );
		$this->assertFileExists( $path );

		wp_delete_post( $id, true );

		$this->assertFileDoesNotExist( $path );
	}

	public function test_the_deprecated_preview_helper_still_renders() {
		$this->setExpectedDeprecated( 'aimg_generate_preview' );

		$this->assertNotEmpty( aimg_generate_preview( $this->create_template(), '#224466', 300, 150 ) );
	}

	public function test_the_classic_form_handler_is_gone() {
		$this->assertFalse( has_action( 'admin_post_aimg_update_template' ) );
		$this->assertFalse( method_exists( 'ArtificialImageGenerator\Admin\Actions', 'update_template' ) );
	}
}
