<?php
/**
 * Golden-image tests: the 1.7 renderer must draw 1.x templates exactly as 1.6.0 did.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Rendering\GdRenderer;
use ArtificialImageGenerator\Rendering\TextLayout;
use ArtificialImageGenerator\Templates\Migration;
use ArtificialImageGenerator\Templates\Repository;

/**
 * @covers \ArtificialImageGenerator\Rendering\GdRenderer
 * @covers \ArtificialImageGenerator\Templates\Migration
 * @covers \ArtificialImageGenerator\Rendering\TextLayout
 */
class Test_Renderer_Parity extends AIMG_TestCase {

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

		parent::tear_down();
	}

	/**
	 * A test image in uploads.
	 *
	 * @param string $name   File name.
	 * @param int    $width  Width.
	 * @param int    $height Height.
	 * @param string $type   png or jpeg.
	 *
	 * @return string Path.
	 */
	private function fixture( $name, $width, $height, $type = 'png' ) {
		$image = imagecreatetruecolor( $width, $height );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );

		for ( $x = 0; $x < $width; $x++ ) {
			for ( $y = 0; $y < $height; $y++ ) {
				$alpha = (int) ( 127 * $x / max( 1, $width - 1 ) );
				imagesetpixel( $image, $x, $y, imagecolorallocatealpha( $image, ( $x * 7 ) % 256, ( $y * 5 ) % 256, 180, $alpha ) );
			}
		}

		ob_start();
		'jpeg' === $type ? imagejpeg( $image ) : imagepng( $image );
		$upload = wp_upload_bits( $name, null, ob_get_clean() );
		imagedestroy( $image );

		$this->files[] = $upload['file'];

		return $upload['file'];
	}

	/**
	 * Template with the given meta.
	 *
	 * @param array $meta Meta without the `_aimg_` prefix.
	 *
	 * @return int
	 */
	private function template( $meta ) {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'aimg_template',
				'post_status' => 'publish',
				'post_title'  => 'Parity template',
			)
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, '_aimg_' . $key, $value );
		}

		return $id;
	}

	/**
	 * Render with both renderers and return the two PNGs' contents.
	 *
	 * @param array $args Render arguments.
	 * @param int   $seed Random seed.
	 *
	 * @return string[] Legacy and new file contents.
	 */
	private function both( $args, $seed = 7 ) {
		mt_srand( $seed ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_seeding_mt_srand
		$legacy = aimg_legacy_generate_thumbnail( $args );

		mt_srand( $seed ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_seeding_mt_srand
		$new = aimg_generate_thumbnail( $args );

		$this->assertNotFalse( $legacy, 'The 1.6.0 renderer produced an image.' );
		$this->assertNotFalse( $new, 'The new renderer produced an image.' );

		$this->files[] = $legacy;
		$this->files[] = $new;

		return array( file_get_contents( $legacy ), file_get_contents( $new ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Assert both renderers wrote the same bytes.
	 *
	 * @param array  $args    Render arguments.
	 * @param string $message Case description.
	 * @param int    $seed    Random seed.
	 */
	private function assert_parity( $args, $message, $seed = 7 ) {
		list( $legacy, $new ) = $this->both( $args, $seed );

		$this->assertTrue( $legacy === $new, 'Different image for: ' . $message );
	}

	public function data_titles() {
		return array(
			'short'         => array( 'Hello' ),
			'wraps'         => array( 'A post about bees and honey production in the modern world of agriculture' ),
			'very long'     => array( str_repeat( 'Lorem ipsum dolor sit amet consectetur ', 8 ) ),
			'unbroken word' => array( 'Supercalifragilisticexpialidocious-and-a-very-long-url-like-https://example.com/a/b/c/d/e' ),
			'entities'      => array( 'Tom & Jerry’s "Best" <Episode>' ),
			'accents'       => array( 'Café déjà vu — naïve résumé, ÅÄÖ' ),
			'extra spaces'  => array( "  Spaces   and\ttabs  " ),
			'one character' => array( 'X' ),
			'braces'        => array( 'Literal {title} in a {title}' ),
		);
	}

	/**
	 * @dataProvider data_titles
	 *
	 * @param string $title Title.
	 */
	public function test_titles( $title ) {
		$template = $this->template( array( 'title_font_size' => '40' ) );

		$this->assert_parity(
			array(
				'template_id' => $template,
				'title'       => $title,
				'colors'      => array( '#224466' ),
				'width'       => 1200,
				'height'      => 600,
			),
			$title
		);
	}

	public function test_font_sizes_and_canvas_sizes() {
		$cases = array(
			array( '', 1200, 600 ),
			array( '0', 1200, 600 ),
			array( '18.5', 1200, 630 ),
			array( '72', 800, 800 ),
			array( '120', 400, 200 ),
			array( '28', 1080, 1920 ),
			array( '64', 150, 150 ),
		);

		foreach ( $cases as $case ) {
			$template = $this->template( array( 'title_font_size' => $case[0] ) );

			$this->assert_parity(
				array(
					'template_id' => $template,
					'title'       => 'Growing tomatoes: tips for a big summer harvest',
					'colors'      => array( '#e74c3c' ),
					'width'       => $case[1],
					'height'      => $case[2],
				),
				implode( ' / ', $case )
			);
		}
	}

	public function test_palettes_and_settings() {
		$template = $this->template( array( 'title_font_size' => '40' ) );
		$args     = array(
			'template_id' => $template,
			'title'       => 'Palette check',
			'width'       => 600,
			'height'      => 300,
		);

		foreach ( array( 1, 2, 3, 4, 5, 6 ) as $seed ) {
			$this->assert_parity( $args + array( 'colors' => array( '#e74c3c', '#2ecc71', '#3498db', '#9B59B6' ) ), 'random palette, seed ' . $seed, $seed );
		}

		$this->assert_parity(
			$args + array(
				'colors' => array(
					3 => 'not-a-color',
					7 => '#ABCDEF',
				),
			),
			'invalid entry and sparse keys',
			3
		);
		$this->assert_parity( $args + array( 'colors' => array( 'rgb(1,2,3)' ) ), 'invalid only' );

		$this->set_settings(
			array(
				'default_bg_color'   => '#123456',
				'default_text_color' => '#FF0000',
			)
		);
		$this->assert_parity( $args + array( 'colors' => array() ), 'default background and red text' );

		$this->set_settings(
			array(
				'default_bg_color'   => 'bad',
				'default_text_color' => 'also bad',
			)
		);
		$this->assert_parity( $args + array( 'colors' => array() ), 'invalid settings' );
	}

	public function test_overlays() {
		$wide   = $this->fixture( 'aimg-parity-wide.png', 300, 120 );
		$tall   = $this->fixture( 'aimg-parity-tall.png', 90, 400 );
		$large  = $this->fixture( 'aimg-parity-large.png', 2400, 1000 );
		$jpeg   = $this->fixture( 'aimg-parity.jpg', 200, 200, 'jpeg' );
		$png_as = $this->fixture( 'aimg-parity-fake.png', 200, 200, 'jpeg' );

		$positions = array_merge( aimg_get_overlay_positions(), array( '', 'not-a-position' ) );

		foreach ( $positions as $position ) {
			$template = $this->template(
				array(
					'title_font_size'  => '44',
					'overlay_position' => $position,
				)
			);

			foreach ( array( $wide, $tall, $large ) as $overlay ) {
				$this->assert_parity(
					array(
						'template_id' => $template,
						'title'       => 'Overlay at ' . $position,
						'colors'      => array( '#336699' ),
						'width'       => 1200,
						'height'      => 600,
						'overlays'    => array( $overlay ),
					),
					basename( $overlay ) . ' at ' . $position
				);
			}
		}

		$template = $this->template( array( 'overlay_position' => 'top-right' ) );
		$base     = array(
			'template_id' => $template,
			'title'       => 'Edge cases',
			'colors'      => array( '#336699' ),
			'width'       => 900,
			'height'      => 500,
		);

		$this->assert_parity( $base + array( 'overlays' => array( $jpeg ) ), 'JPEG overlay is skipped' );
		$this->assert_parity( $base + array( 'overlays' => array( $png_as ) ), 'JPEG named .png is skipped' );
		$this->assert_parity( $base + array( 'overlays' => array( $wide, $tall ) ), 'two overlays are both drawn' );
		$this->assert_parity( $base + array( 'overlays' => array( '/no/such/file.png' ) ), 'missing file' );
	}

	public function test_templates_render_through_generator_as_before() {
		$overlay  = $this->fixture( 'aimg-parity-generator.png', 400, 300 );
		$id       = self::factory()->attachment->create_object(
			$overlay,
			0,
			array( 'post_mime_type' => 'image/png' )
		);
		$template = $this->template(
			array(
				'bg_colors'        => '#111111, #eeeeee',
				'width'            => '1000',
				'height'           => '500',
				'title_font_size'  => '50',
				'is_overlay_image' => 'yes',
				'overlay_images'   => wp_json_encode( array( $id ) ),
				'overlay_position' => 'left-center',
			)
		);

		$args = ArtificialImageGenerator\Generator::get_render_args( $template, 'Through the generator' );

		$this->assert_parity( $args, 'Generator::get_render_args()' );
	}

	public function test_migrated_document_matches_1_x() {
		$overlay  = $this->fixture( 'aimg-parity-migrated.png', 320, 240 );
		$id       = self::factory()->attachment->create_object(
			$overlay,
			0,
			array( 'post_mime_type' => 'image/png' )
		);
		$template = $this->template(
			array(
				'bg_colors'        => '#aa0000,#00aa00,#0000aa',
				'width'            => '1200',
				'height'           => '630',
				'title_font_size'  => '48',
				'is_overlay_image' => 'yes',
				'overlay_images'   => wp_json_encode( array( $id ) ),
				'overlay_position' => 'bottom-center',
			)
		);

		$title    = 'Saved as a v2 document, drawn the same';
		$document = Migration::from_template( $template );

		foreach ( array( 11, 12, 13 ) as $seed ) {
			mt_srand( $seed ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_seeding_mt_srand
			$legacy        = aimg_legacy_generate_thumbnail( ArtificialImageGenerator\Generator::get_render_args( $template, $title ) );
			$this->files[] = $legacy;

			mt_srand( $seed ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_seeding_mt_srand
			$new           = GdRenderer::save( GdRenderer::render( $document, array( 'title' => $title ) ), $document, 'aimg-parity-migrated' );
			$this->files[] = $new;

			$this->assertTrue( file_get_contents( $legacy ) === file_get_contents( $new ), 'Seed ' . $seed ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
	}

	public function test_saving_or_duplicating_a_1_x_template_draws_the_same() {
		$template = $this->template(
			array(
				'bg_colors'       => '#aa0000,#00aa00,#0000aa',
				'width'           => '1200',
				'height'          => '630',
				'title_font_size' => '48',
			)
		);
		$title    = 'Opened and saved in the Studio';
		$args     = ArtificialImageGenerator\Generator::get_render_args( $template, $title );

		$copy = Repository::duplicate( $template );
		$this->rest( 'PUT', '/aimg/v1/templates/' . $template, array( 'document' => Repository::get_document( $template ) ) );
		$this->assertTrue( Repository::has_document( $template ) );

		foreach ( array( $template, $copy ) as $id ) {
			$document = Repository::get_document( $id );

			foreach ( array( 21, 22, 23 ) as $seed ) {
				mt_srand( $seed ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_seeding_mt_srand
				$legacy        = aimg_legacy_generate_thumbnail( $args );
				$this->files[] = $legacy;

				mt_srand( $seed ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_seeding_mt_srand
				$new           = GdRenderer::save( GdRenderer::render( $document, array( 'title' => $title ) ), $document, 'aimg-parity-saved' );
				$this->files[] = $new;

				$this->assertTrue( file_get_contents( $legacy ) === file_get_contents( $new ), 'Template ' . $id . ', seed ' . $seed ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}
	}

	public function test_wrap_matches_1_x() {
		$font = AIMG_ASSETS_PATH . 'fonts/Roboto-Bold.ttf';

		foreach ( $this->data_titles() as $title ) {
			foreach ( array( 12, 24.5, 40, 90, 200 ) as $size ) {
				foreach ( array( 70, 300, 1120 ) as $width ) {
					$this->assertSame(
						aimg_legacy_wrap_title( $title[0], $size, $font, $width ),
						TextLayout::wrap( $title[0], $size, $font, $width ),
						"{$title[0]} / {$size} / {$width}"
					);
				}
			}
		}
	}
}
