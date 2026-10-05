<?php
/**
 * Template saving, rendering and deletion.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Generator;

/**
 * @covers \ArtificialImageGenerator\Admin\Actions
 * @covers \ArtificialImageGenerator\Generator
 */
class Test_Templates extends AIMG_TestCase {

	/**
	 * Template ID from a redirect location.
	 *
	 * @param string $location Redirect URL.
	 *
	 * @return int
	 */
	private function edited_id( $location ) {
		parse_str( (string) wp_parse_url( $location, PHP_URL_QUERY ), $query );

		return isset( $query['edit'] ) ? (int) $query['edit'] : 0;
	}

	public function test_saving_with_a_non_template_id_leaves_that_post_untouched() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Keep me',
				'post_content' => 'Content',
			)
		);

		$this->submit_template_form(
			array(
				'template_id' => $page_id,
				'title'       => 'Hijacked',
			)
		);

		$page = get_post( $page_id );
		$this->assertSame( 'page', $page->post_type );
		$this->assertSame( 'Keep me', $page->post_title );
		$this->assertSame( 'Content', $page->post_content );
	}

	public function test_submitted_values_are_validated() {
		$image = $this->create_png_attachment();
		$text  = self::factory()->attachment->create( array( 'post_mime_type' => 'text/plain' ) );

		$id = $this->edited_id(
			$this->submit_template_form(
				array(
					'title'            => 'Validated',
					'status'           => 'trash',
					'bg_colors'        => '#123456',
					'width'            => '400',
					'height'           => '200',
					'title_font_size'  => '30',
					'is_overlay_image' => '1',
					'overlay_images'   => wp_json_encode( array( 'abc', 99999999, $text, $image, $image ) ),
					'overlay_position' => 'nowhere',
				)
			)
		);

		$this->assertSame( 'aimg_template', get_post_type( $id ) );
		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertSame( 'center-center', get_post_meta( $id, '_aimg_overlay_position', true ) );
		$this->assertSame( wp_json_encode( array( $image ) ), get_post_meta( $id, '_aimg_overlay_images', true ) );
		$this->assertNotEmpty( get_post_meta( $id, '_aimg_preview_image_url', true ) );
	}

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
		$url = aimg_generate_preview( $id, '#224466', 300, 150 );
		update_post_meta( $id, '_aimg_preview_image_url', $url );

		$upload = wp_upload_dir();
		$path   = str_replace( $upload['baseurl'], $upload['basedir'], $url );
		$this->assertFileExists( $path );

		wp_delete_post( $id, true );

		$this->assertFileDoesNotExist( $path );
	}
}
