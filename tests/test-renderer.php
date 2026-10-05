<?php
/**
 * GD renderer.
 *
 * @package ArtificialImageGenerator
 */

/**
 * @covers ::aimg_generate_thumbnail
 * @covers ::aimg_wrap_title
 */
class Test_Renderer extends AIMG_TestCase {

	/**
	 * Bundled font.
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

	/**
	 * Width of a line of text.
	 *
	 * @param string $text Text.
	 * @param float  $size Font size.
	 *
	 * @return int
	 */
	private function width( $text, $size ) {
		$box = imagettfbbox( $size, 0, $this->font, $text );

		return $box[2] - $box[0];
	}

	/**
	 * Titles whose words all fit.
	 *
	 * @return array
	 */
	public function data_fitting_titles() {
		return array(
			array( 'Hello world' ),
			array( 'How to build a WordPress plugin that generates beautiful featured images automatically' ),
			array( 'Ten tips for better sleep, focus and productivity in 2026' ),
		);
	}

	/**
	 * @dataProvider data_fitting_titles
	 *
	 * @param string $title Title.
	 */
	public function test_titles_that_fit_keep_their_size_and_every_line_fits( $title ) {
		$wrapped = aimg_wrap_title( $title, 50, $this->font, 1120 );

		$this->assertEquals( 50, $wrapped['font_size'] );
		$this->assertSame( $title, implode( ' ', $wrapped['lines'] ) );

		foreach ( $wrapped['lines'] as $line ) {
			$this->assertLessThanOrEqual( 1120, $this->width( $line, 50 ) );
		}
	}

	public function test_a_word_wider_than_the_canvas_shrinks_the_font() {
		$wrapped = aimg_wrap_title( 'M1 tt5:grouped-product-add-to-cart-with-quantity', 50, $this->font, 520 );

		$this->assertLessThan( 50, $wrapped['font_size'] );

		foreach ( $wrapped['lines'] as $line ) {
			$this->assertLessThanOrEqual( 520, $this->width( $line, $wrapped['font_size'] ) );
		}
	}

	public function test_an_unbreakable_word_is_split_at_the_minimum_size() {
		$wrapped = aimg_wrap_title( str_repeat( 'W', 400 ), 50, $this->font, 520 );

		$this->assertEquals( 12, $wrapped['font_size'] );
		$this->assertGreaterThan( 1, count( $wrapped['lines'] ) );
	}

	public function test_a_corrupt_overlay_is_skipped() {
		$upload  = wp_upload_dir();
		$corrupt = $upload['path'] . '/aimg-corrupt.png';
		file_put_contents( $corrupt, 'not a png' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$path = aimg_generate_thumbnail(
			array(
				'template_id' => $this->create_template(),
				'title'       => 'Corrupt overlay',
				'colors'      => array( '#336699' ),
				'width'       => 400,
				'height'      => 200,
				'overlays'    => array( $corrupt ),
			)
		);

		$this->assertFileExists( $path );
		wp_delete_file( $corrupt );
		wp_delete_file( $path );
	}

	public function test_renders_a_png_of_the_requested_size() {
		$path = aimg_generate_thumbnail(
			array(
				'template_id' => $this->create_template(),
				'title'       => 'Size check',
				'colors'      => array( '#336699' ),
				'width'       => 640,
				'height'      => 320,
			)
		);

		$info = getimagesize( $path );
		$this->assertSame( array( 640, 320, IMAGETYPE_PNG ), array( $info[0], $info[1], $info[2] ) );
		wp_delete_file( $path );
	}
}
