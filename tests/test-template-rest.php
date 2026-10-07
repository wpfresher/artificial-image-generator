<?php
/**
 * Template REST endpoints (M1 step 3).
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Templates\Repository;
use ArtificialImageGenerator\Templates\RestController;

/**
 * @covers \ArtificialImageGenerator\Templates\RestController
 * @covers \ArtificialImageGenerator\Rendering\Capabilities
 */
class Test_Template_Rest extends AIMG_TestCase {

	/**
	 * A small valid document.
	 *
	 * @param string $color Background color.
	 *
	 * @return array
	 */
	private function document( $color = '#336699' ) {
		return array(
			'canvas' => array(
				'width'  => 300,
				'height' => 150,
			),
			'layers' => array(
				array(
					'type' => 'background',
					'fill' => array(
						'kind'  => 'solid',
						'color' => $color,
					),
				),
				array(
					'type'    => 'text',
					'content' => '{title} {custom_field:subtitle}',
					'size'    => array( 'max' => 20 ),
				),
			),
		);
	}

	/**
	 * Path of an uploads URL.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	private function path( $url ) {
		$uploads = wp_upload_dir();

		return $uploads['basedir'] . substr( $url, strlen( $uploads['baseurl'] ) );
	}

	public function test_create_read_update_delete() {
		$created = $this->rest(
			'POST',
			'/aimg/v1/templates',
			array(
				'title'    => 'Studio & Co',
				'document' => wp_json_encode( $this->document() ),
			)
		);

		$this->assertSame( 201, $created->get_status() );
		$data = $created->get_data();
		$id   = $data['id'];

		$this->assertTrue( $data['hasDocument'] );
		$this->assertSame( 'Studio & Co', $data['title'] );
		$this->assertSame( 'publish', $data['status'] );
		$this->assertSame( array( 300, 150 ), array( $data['width'], $data['height'] ) );
		$this->assertSame( '300', get_post_meta( $id, '_aimg_width', true ), 'Sizes are kept in the 1.x meta for the list and the modal.' );
		$this->assertFileExists( $this->path( $data['preview'] ) );
		$this->assertSame( 2, $data['document']['version'] );

		$read = $this->rest( 'GET', '/aimg/v1/templates/' . $id )->get_data();
		$this->assertSame( $data['document'], $read['document'] );

		$updated = $this->rest(
			'PUT',
			'/aimg/v1/templates/' . $id,
			array(
				'title'    => 'Renamed',
				'status'   => 'draft',
				'document' => $this->document( '#ff0000' ),
			)
		)->get_data();

		$this->assertSame( 'Renamed', $updated['title'] );
		$this->assertSame( 'draft', $updated['status'] );
		$this->assertSame( '#ff0000', $updated['document']['layers'][0]['fill']['color'] );
		$this->assertFileExists( $this->path( $updated['preview'] ) );
		$this->assertFileDoesNotExist( $this->path( $data['preview'] ), 'The old preview is removed.' );

		$this->assertSame( 400, $this->rest( 'PATCH', '/aimg/v1/templates/' . $id, array( 'title' => '  ' ) )->get_status() );

		$deleted = $this->rest( 'DELETE', '/aimg/v1/templates/' . $id );
		$this->assertTrue( $deleted->get_data()['deleted'] );
		$this->assertNull( get_post( $id ) );
		$this->assertFileDoesNotExist( $this->path( $updated['preview'] ), 'The preview goes with the template.' );
		$this->assertSame( 404, $this->rest( 'GET', '/aimg/v1/templates/' . $id )->get_status() );
	}

	public function test_new_templates_start_from_the_starter_document() {
		$this->set_settings( array( 'default_bg_color' => '#123456' ) );

		$data = $this->rest( 'POST', '/aimg/v1/templates', array( 'title' => 'Blank' ) )->get_data();

		$this->assertSame( array( 'background', 'text' ), wp_list_pluck( $data['document']['layers'], 'type' ) );
		$this->assertSame( '#123456', $data['document']['layers'][0]['fill']['color'] );
	}

	public function test_documents_are_sanitized() {
		$data = $this->rest(
			'POST',
			'/aimg/v1/templates',
			array(
				'title'    => 'Hostile',
				'document' => array(
					'canvas' => array( 'width' => 99999 ),
					'layers' => array(
						array( 'type' => 'php' ),
						array(
							'type'   => 'image',
							'_files' => array( ABSPATH . 'wp-config.php' ),
						),
					),
				),
			)
		)->get_data();

		$this->assertSame( 4000, $data['document']['canvas']['width'] );
		$this->assertCount( 1, $data['document']['layers'] );
		$this->assertArrayNotHasKey( '_files', $data['document']['layers'][0] );
	}

	public function test_1_x_templates_are_read_without_being_migrated() {
		$template = $this->create_template();
		$data     = $this->rest( 'GET', '/aimg/v1/templates/' . $template )->get_data();

		$this->assertFalse( $data['hasDocument'] );
		$this->assertSame( 'background', $data['document']['layers'][0]['type'] );
		$this->assertFalse( Repository::has_document( $template ) );
	}

	public function test_only_template_managers_get_in() {
		$template = $this->create_template();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$requests = array(
			array( 'POST', '/aimg/v1/templates', array( 'title' => 'x' ) ),
			array( 'GET', '/aimg/v1/templates/' . $template, array() ),
			array( 'PUT', '/aimg/v1/templates/' . $template, array( 'title' => 'x' ) ),
			array( 'DELETE', '/aimg/v1/templates/' . $template, array() ),
			array( 'POST', '/aimg/v1/templates/preview', array( 'document' => $this->document() ) ),
			array( 'GET', '/aimg/v1/capabilities', array() ),
		);

		foreach ( $requests as $request ) {
			$this->assertSame( 403, $this->rest( $request[0], $request[1], $request[2] )->get_status(), $request[0] . ' ' . $request[1] );
		}

		$this->assertSame( 200, $this->rest( 'GET', '/aimg/v1/templates' )->get_status(), 'The modal list is still open to editors.' );
		$this->assertNotNull( get_post( $template ) );

		add_filter(
			'aimg_manage_templates_capability',
			function () {
				return 'edit_others_posts';
			}
		);
		$this->assertSame( 'edit_others_posts', RestController::capability() );
		$this->assertSame( 200, $this->rest( 'GET', '/aimg/v1/templates/' . $template )->get_status() );
	}

	public function test_ids_that_are_not_templates_are_not_found() {
		$post = self::factory()->post->create();

		$this->assertSame( 404, $this->rest( 'GET', '/aimg/v1/templates/' . $post )->get_status() );
		$this->assertSame( 404, $this->rest( 'DELETE', '/aimg/v1/templates/' . $post )->get_status() );
		$this->assertNotNull( get_post( $post ) );
	}

	public function test_preview_renders_unsaved_documents_in_memory() {
		$post = self::factory()->post->create( array( 'post_title' => 'Real post' ) );
		update_post_meta( $post, 'subtitle', 'from a field' );

		$response = $this->rest(
			'POST',
			'/aimg/v1/templates/preview',
			array(
				'document' => $this->document(),
				'post_id'  => $post,
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertStringStartsWith( 'data:image/png;base64,', $data['image'] );

		$image = imagecreatefromstring( base64_decode( substr( $data['image'], strlen( 'data:image/png;base64,' ) ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$this->assertSame( array( 300, 150 ), array( imagesx( $image ), imagesy( $image ) ) );

		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$other  = self::factory()->post->create( array( 'post_author' => $author ) );
		add_filter(
			'aimg_manage_templates_capability',
			function () {
				return 'upload_files';
			}
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertSame(
			403,
			$this->rest(
				'POST',
				'/aimg/v1/templates/preview',
				array(
					'document' => $this->document(),
					'post_id'  => $other,
				)
			)->get_status(),
			'Merge tags only come from posts the user can edit.'
		);
	}

	public function test_previews_are_rate_limited() {
		$statuses = array();
		for ( $i = 0; $i <= RestController::PREVIEWS_PER_MINUTE; $i++ ) {
			$statuses[] = $this->rest(
				'POST',
				'/aimg/v1/templates/preview',
				array(
					'document' => array(
						'canvas' => array(
							'width'  => 16,
							'height' => 16,
						),
					),
				)
			)->get_status();
		}

		$this->assertSame( 200, $statuses[ RestController::PREVIEWS_PER_MINUTE - 1 ] );
		$this->assertSame( 429, end( $statuses ) );
	}

	public function test_merge_tags_for_previewing_with_a_post() {
		$png  = $this->create_png_attachment( 'aimg-merge-featured.png' );
		$post = self::factory()->post->create( array( 'post_title' => 'Bees & honey' ) );
		set_post_thumbnail( $post, $png );

		$data = $this->rest( 'GET', '/aimg/v1/merge-tags/' . $post )->get_data();

		$this->assertSame( 'Bees & honey', $data['tags']['title'] );
		$this->assertStringContainsString( 'aimg-merge-featured', $data['images']['featured'] );
		$this->assertSame( '', $data['images']['author_avatar'] );
		$this->assertSame( 403, $this->rest( 'GET', '/aimg/v1/merge-tags/999999' )->get_status(), 'Unknown IDs fail the edit check, so IDs cannot be probed.' );

		$other = self::factory()->post->create( array( 'post_author' => self::factory()->user->create( array( 'role' => 'author' ) ) ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 403, $this->rest( 'GET', '/aimg/v1/merge-tags/' . $other )->get_status() );
	}

	public function test_capabilities() {
		$data = $this->rest( 'GET', '/aimg/v1/capabilities' )->get_data();

		$this->assertTrue( $data['canRender'] );
		$this->assertTrue( $data['features']['freetype'] );
		$this->assertContains( 'shape', $data['layerTypes'] );
		$this->assertArrayHasKey( 'png', $data['outputFormats'] );
		$this->assertSame( 'roboto-bold', $data['fonts'][0]['id'] );
		$this->assertStringEndsWith( 'assets/fonts/Roboto-Bold.ttf', $data['fonts'][0]['url'] );
		$this->assertArrayHasKey( 'reading_time', $data['mergeTags'] );
		$this->assertSame( 50, $data['limits']['maxLayers'] );
	}
}
