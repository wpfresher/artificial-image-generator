<?php
/**
 * Integration API.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Api;
use ArtificialImageGenerator\Generator;
use ArtificialImageGenerator\Templates\Repository;

/**
 * @covers \ArtificialImageGenerator\Api
 * @covers \ArtificialImageGenerator\Generator::render_template
 */
class Test_Api extends AIMG_TestCase {

	/**
	 * HTTP requests attempted during a test.
	 *
	 * @var string[]
	 */
	private $http = array();

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->http = array();
		add_filter( 'pre_http_request', array( $this, 'trap_http' ), 1, 3 );
	}

	/**
	 * Record and refuse every HTTP request.
	 *
	 * @param mixed  $pre  Pre value.
	 * @param array  $args Args.
	 * @param string $url  URL.
	 *
	 * @return WP_Error
	 */
	public function trap_http( $pre, $args, $url ) {
		$this->http[] = $url;

		return new WP_Error( 'blocked', 'blocked' );
	}

	/**
	 * A v2 template with two text layers.
	 *
	 * @param array $extra_layers Layers appended after the text.
	 *
	 * @return int Template ID.
	 */
	private function create_document_template( $extra_layers = array() ) {
		$id = $this->create_template();

		Repository::save_document(
			$id,
			array(
				'canvas' => array(
					'width'      => 600,
					'height'     => 600,
					'background' => '#112233',
				),
				'layers' => array_merge(
					array(
						array(
							'type'     => 'text',
							'id'       => 'heading',
							'content'  => '{title}',
							'maxLines' => 3,
						),
						array(
							'type'    => 'text',
							'id'      => 'price',
							'content' => 'Only {custom_field:price}',
							'showIf'  => 'custom_field:price',
						),
					),
					$extra_layers
				),
			)
		);

		return $id;
	}

	public function test_version_constant() {
		$this->assertSame( Api::VERSION, AIMG_API_VERSION );
	}

	public function test_summary_describes_template() {
		$id      = $this->create_document_template();
		$summary = aimg_get_template_summary( $id );

		$this->assertSame( $id, $summary['id'] );
		$this->assertSame( 600, $summary['width'] );
		$this->assertSame( 'square', $summary['aspect'] );
		$this->assertTrue( $summary['has_document'] );
		$this->assertFalse( $summary['remote_layers'] );
		$this->assertEqualsCanonicalizing( array( 'title', 'custom_field:price' ), $summary['merge_tags'] );
		$this->assertCount( 2, $summary['text_layers'] );
		$this->assertSame( 3, $summary['text_layers'][0]['max_lines'] );
	}

	public function test_summary_of_legacy_template_is_landscape() {
		$summary = aimg_get_template_summary( $this->create_template() );

		$this->assertFalse( $summary['has_document'] );
		$this->assertSame( 'landscape', $summary['aspect'] );
		$this->assertContains( 'title', $summary['merge_tags'] );
	}

	public function test_summary_rejects_other_posts() {
		$this->assertWPError( aimg_get_template_summary( self::factory()->post->create() ) );
	}

	public function test_templates_lists_only_published() {
		$published = $this->create_template();
		$draft     = $this->create_template();
		wp_update_post(
			array(
				'ID'          => $draft,
				'post_status' => 'draft',
			)
		);

		$ids = wp_list_pluck( aimg_get_template_summaries(), 'id' );

		$this->assertContains( $published, $ids );
		$this->assertNotContains( $draft, $ids );
	}

	public function test_render_uses_tag_overrides() {
		$id = $this->create_document_template();

		$a = aimg_render_template( $id, array( 'tags' => array( 'title' => 'Step one' ) ) );
		$b = aimg_render_template(
			$id,
			array(
				'tags' => array(
					'title'              => 'Step one',
					'custom_field:price' => '$9',
				),
			)
		);

		$this->assertFileExists( $a['path'] );
		$this->assertFileExists( $b['path'] );
		$this->assertSame( $id, $a['template_id'] );
		$this->assertNotSame( md5_file( $a['path'] ), md5_file( $b['path'] ), 'The custom field override must reach the image.' );
		$this->assertSame( array(), $this->http );
	}

	public function test_render_matches_generator_for_title_only() {
		$id   = $this->create_document_template();
		$post = self::factory()->post->create( array( 'post_title' => 'Grow tomatoes' ) );

		$api    = aimg_render_template( $id, array( 'post_id' => $post ) );
		$direct = Generator::render( $id, 'Grow tomatoes', $post );

		$this->assertSame( md5_file( $direct ), md5_file( $api['path'] ) );
	}

	public function test_render_legacy_template() {
		$result = aimg_render_template( $this->create_template(), array( 'tags' => array( 'title' => 'Hello' ) ) );

		$this->assertIsArray( $result );
		$this->assertFileExists( $result['path'] );
	}

	public function test_render_refuses_remote_layers_without_http() {
		$id = $this->create_document_template(
			array(
				array(
					'type'   => 'image',
					'source' => 'ai',
					'prompt' => 'a tomato',
				),
			)
		);

		$this->assertTrue( aimg_get_template_summary( $id )['remote_layers'] );

		$result = aimg_render_template( $id );

		$this->assertWPError( $result );
		$this->assertSame( 'aimg_remote_layers', $result->get_error_code() );
		$this->assertSame( array(), $this->http );
	}

	public function test_render_validates_input() {
		$id = $this->create_document_template();

		$this->assertSame( 'aimg_invalid_tags', aimg_render_template( $id, array( 'tags' => array( 'Bad Name' => 'x' ) ) )->get_error_code() );
		$this->assertSame( 'aimg_invalid_tags', aimg_render_template( $id, array( 'tags' => array( 'title' => array( 'x' ) ) ) )->get_error_code() );
		$this->assertSame( 'aimg_invalid_post', aimg_render_template( $id, array( 'post_id' => 999999 ) )->get_error_code() );
		$this->assertSame( 'aimg_invalid_template', aimg_render_template( self::factory()->post->create() )->get_error_code() );
	}

	public function test_render_strips_markup_and_caps_length() {
		$tags = Api::sanitize_tags( array( 'title' => '<b>Bold</b> &amp; ' . str_repeat( 'x', 900 ) ) );

		$this->assertStringStartsWith( 'Bold & x', $tags['title'] );
		$this->assertSame( Api::MAX_TAG_LENGTH, mb_strlen( $tags['title'] ) );
	}

	public function test_render_falls_back_to_default_template() {
		$id = $this->create_document_template();
		$this->set_settings( array( 'default_template_id' => $id ) );

		$this->assertSame( $id, aimg_render_template( 0 )['template_id'] );
	}

	public function test_import_creates_stamped_attachment() {
		$id     = $this->create_document_template();
		$post   = self::factory()->post->create();
		$result = aimg_render_template( $id, array( 'tags' => array( 'title' => 'Step one' ) ) );

		$attachment = aimg_import_rendered_image(
			$result['path'],
			array(
				'alt'         => 'Step one diagram',
				'caption'     => 'Step one',
				'parent'      => $post,
				'template_id' => $id,
				'source'      => 'sokket',
			)
		);

		$this->assertIsInt( $attachment );
		$this->assertSame( 'Step one diagram', get_post_meta( $attachment, '_wp_attachment_image_alt', true ) );
		$this->assertSame( 'Step one', get_post_field( 'post_excerpt', $attachment ) );
		$this->assertSame( $post, wp_get_post_parent_id( $attachment ) );

		$provenance = get_post_meta( $attachment, Generator::GENERATED_DATA_META, true );
		$this->assertSame( 'sokket', $provenance['source'] );
		$this->assertSame( $id, $provenance['template_id'] );
	}

	public function test_import_refuses_files_outside_uploads() {
		$this->assertWPError( aimg_import_rendered_image( __FILE__ ) );
		$this->assertWPError( aimg_import_rendered_image( '' ) );
	}
}
