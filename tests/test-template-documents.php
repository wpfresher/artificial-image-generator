<?php
/**
 * Template documents (schema v2).
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Generator;
use ArtificialImageGenerator\Rendering\GdRenderer;
use ArtificialImageGenerator\Rendering\TextLayout;
use ArtificialImageGenerator\Templates\Migration;
use ArtificialImageGenerator\Templates\Repository;
use ArtificialImageGenerator\Templates\Schema;

/**
 * @covers \ArtificialImageGenerator\Templates\Schema
 * @covers \ArtificialImageGenerator\Templates\Repository
 * @covers \ArtificialImageGenerator\Rendering\TextLayout
 */
class Test_Template_Documents extends AIMG_TestCase {

	/**
	 * Font used for layout tests.
	 *
	 * @var string
	 */
	private $font;

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->font = AIMG_ASSETS_PATH . 'fonts/Roboto-Bold.ttf';
	}

	public function test_sanitize_fills_defaults_and_clamps() {
		$document = Schema::sanitize(
			array(
				'canvas' => array(
					'width'      => 99999,
					'height'     => -5,
					'background' => '#ABC',
				),
				'output' => array(
					'format'  => 'gif',
					'quality' => 500,
				),
			)
		);

		$this->assertSame( 2, $document['version'] );
		$this->assertSame( Schema::MAX_SIZE, $document['canvas']['width'] );
		$this->assertSame( 16, $document['canvas']['height'] );
		$this->assertSame( '#aabbcc', $document['canvas']['background'] );
		$this->assertSame( 'png', $document['output']['format'] );
		$this->assertSame( 100, $document['output']['quality'] );
		$this->assertSame( array(), $document['layers'] );
	}

	public function test_sanitize_never_trusts_layers() {
		$png      = $this->create_png_attachment();
		$post     = self::factory()->post->create();
		$document = Schema::sanitize(
			wp_json_encode(
				array(
					'canvas' => array(
						'width'  => 800,
						'height' => 400,
					),
					'layers' => array_merge(
						array(
							array( 'type' => 'script' ),
							'not a layer',
							array(
								'type'        => 'image',
								'id'          => 'logo',
								'attachments' => array( $png, $post, 'x', $png ),
								'_files'      => array( '/etc/passwd' ),
								'fit'         => 'stretch-everything',
								'opacity'     => 3,
							),
							array(
								'type'    => 'text',
								'id'      => 'logo',
								'content' => '<script>alert(1)</script>{title}',
								'color'   => 'red',
								'size'    => array(
									'max' => 40,
									'min' => 90,
								),
							),
						),
						array_fill( 0, 60, array( 'type' => 'overlay' ) )
					),
				)
			)
		);

		$this->assertCount( Schema::MAX_LAYERS - 2, $document['layers'], 'Unknown entries are dropped and the rest capped.' );

		$image = $document['layers'][0];
		$this->assertSame( array( $png ), $image['attachments'], 'Only real image attachments, once.' );
		$this->assertArrayNotHasKey( '_files', $image );
		$this->assertSame( 'contain', $image['fit'] );
		$this->assertSame( 1.0, $image['opacity'] );

		$text = $document['layers'][1];
		$this->assertNotSame( 'logo', $text['id'], 'IDs are unique.' );
		$this->assertSame( '{title}', $text['content'], 'Scripts are removed with their contents.' );
		$this->assertSame( '#ffffff', $text['color'] );
		$this->assertSame( 40.0, $text['size']['min'], 'The minimum size is never above the maximum.' );
	}

	public function test_custom_layer_types_can_be_registered() {
		add_filter(
			'aimg_template_layers',
			function ( $types ) {
				$types['bogus'] = 'stdClass';
				return $types;
			}
		);

		$this->assertArrayNotHasKey( 'bogus', Schema::layer_types(), 'Only LayerInterface classes are accepted.' );
	}

	public function test_fit_shrinks_text_into_the_box() {
		$text   = 'A rather long title that will not fit in a small box at a large size';
		$layout = TextLayout::fit(
			$text,
			$this->font,
			array(
				'max_size'    => 80,
				'min_size'    => 10,
				'width'       => 500,
				'height'      => 200,
				'line_height' => 1.2,
				'max_lines'   => 0,
			)
		);

		$this->assertLessThan( 80, $layout['font_size'] );
		$this->assertLessThanOrEqual( 200, count( $layout['lines'] ) * $layout['font_size'] * 1.2 );
		$this->assertSame( $text, implode( ' ', $layout['lines'] ), 'Nothing is cut when it fits.' );

		foreach ( $layout['lines'] as $line ) {
			$this->assertLessThanOrEqual( 500, TextLayout::measure( $line, $layout['font_size'], $this->font ) );
		}

		$bigger = TextLayout::fit(
			$text,
			$this->font,
			array(
				'max_size'    => $layout['font_size'] + 1,
				'min_size'    => 10,
				'width'       => 500,
				'height'      => 200,
				'line_height' => 1.2,
				'max_lines'   => 0,
			)
		);
		$this->assertSame( $layout['font_size'], $bigger['font_size'], 'It picks the largest size that fits.' );
	}

	public function test_fit_cuts_with_an_ellipsis_at_the_minimum_size() {
		$layout = TextLayout::fit(
			str_repeat( 'words that keep going ', 30 ),
			$this->font,
			array(
				'max_size'    => 40,
				'min_size'    => 30,
				'width'       => 400,
				'height'      => 1000,
				'line_height' => 1.3,
				'max_lines'   => 2,
			)
		);

		$this->assertSame( 30.0, (float) $layout['font_size'] );
		$this->assertCount( 2, $layout['lines'] );
		$this->assertStringEndsWith( '…', $layout['lines'][1] );
		$this->assertLessThanOrEqual( 400, TextLayout::measure( $layout['lines'][1], 30, $this->font ) );
	}

	public function test_templates_without_a_document_use_their_1_x_meta() {
		$template = $this->create_template( array( 'bg_colors' => '#224466, #abcdef' ) );

		$this->assertFalse( Repository::has_document( $template ) );

		$document = Repository::get_document( $template );
		$this->assertSame( array( 'background', 'overlay', 'text' ), wp_list_pluck( $document['layers'], 'type' ), 'No image layer without overlays.' );
		$this->assertSame( array( '#224466', '#abcdef' ), $document['layers'][0]['fill']['colors'] );
		$this->assertSame( 'background', $document['layers'][1]['color'] );
		$this->assertSame( '{title}', $document['layers'][2]['content'] );
		$this->assertFalse( Repository::has_document( $template ), 'Reading never migrates.' );
	}

	public function test_a_stored_document_is_rendered_instead_of_the_1_x_meta() {
		$template = $this->create_template();
		Repository::save_document(
			$template,
			array(
				'canvas' => array(
					'width'  => 320,
					'height' => 160,
				),
				'output' => array( 'format' => 'jpeg' ),
				'layers' => array(
					array(
						'type' => 'background',
						'fill' => array(
							'kind'  => 'solid',
							'color' => '#ff0000',
						),
					),
					array(
						'type'    => 'text',
						'content' => 'Hi {title}',
						'size'    => array( 'max' => 30 ),
					),
				),
			)
		);

		$path = Generator::render( $template, 'there' );

		$this->assertNotFalse( $path );
		$this->assertSame( 'jpg', pathinfo( $path, PATHINFO_EXTENSION ) );
		$this->assertSame( array( 320, 160 ), array_slice( getimagesize( $path ), 0, 2 ) );
		$this->assertSame( '#224466', get_post_meta( $template, '_aimg_bg_colors', true ), 'The 1.x meta is kept for downgrades.' );

		$image  = imagecreatefromjpeg( $path );
		$corner = imagecolorsforindex( $image, imagecolorat( $image, 2, 2 ) );
		$this->assertGreaterThan( 240, $corner['red'] );
		$this->assertLessThan( 15, $corner['green'] );

		wp_delete_file( $path );

		$response = $this->rest(
			'POST',
			'/aimg/v1/generate',
			array(
				'template_id' => $template,
				'title'       => 'REST',
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'image/jpeg', get_post_mime_type( $response->get_data()['id'] ), 'REST renders the document too.' );
	}

	public function test_rendering_needs_a_published_template() {
		$template = $this->create_template();
		Repository::save_document( $template, Migration::from_template( $template ) );
		wp_update_post(
			array(
				'ID'          => $template,
				'post_status' => 'draft',
			)
		);

		$this->assertFalse( Generator::render( $template, 'Nope' ) );
	}

	public function test_hidden_layers_are_skipped() {
		$document = Schema::sanitize(
			array(
				'canvas' => array(
					'width'      => 40,
					'height'     => 40,
					'background' => '#000000',
				),
				'layers' => array(
					array(
						'type'    => 'overlay',
						'color'   => '#ffffff',
						'opacity' => 1,
						'visible' => false,
					),
				),
			)
		);

		$image = GdRenderer::render( $document );
		$this->assertSame( 0, imagecolorat( $image, 20, 20 ) & 0xFFFFFF );
	}
}
