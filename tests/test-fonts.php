<?php
/**
 * Fonts: bundled fonts and uploads.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Rendering\Fonts;

/**
 * @covers \ArtificialImageGenerator\Rendering\Fonts
 */
class Test_Fonts extends AIMG_TestCase {

	/**
	 * Tear down each test.
	 */
	public function tear_down() {
		foreach ( array_keys( Fonts::uploaded() ) as $id ) {
			Fonts::delete_upload( $id );
		}
		delete_option( Fonts::OPTION );
		parent::tear_down();
	}

	/**
	 * Copy a file to a temporary path.
	 *
	 * @param string $source File.
	 *
	 * @return string
	 */
	private function temp_copy( $source ) {
		$tmp = wp_tempnam( 'aimg-font' );
		copy( $source, $tmp );

		return $tmp;
	}

	public function test_every_bundled_font_renders_with_gd() {
		foreach ( Fonts::bundled() as $id => $font ) {
			$path = Fonts::path( $id );
			$this->assertStringEndsWith( $font[1], $path, $id );
			$this->assertIsArray( imagettfbbox( 20, 0, $path, 'Café' ), $id );
		}
	}

	public function test_unknown_fonts_fall_back_to_roboto_bold() {
		$this->assertStringEndsWith( 'Roboto-Bold.ttf', Fonts::path( 'no-such-font' ) );
	}

	public function test_uploads_are_validated() {
		$bad = wp_tempnam( 'aimg-font' );
		file_put_contents( $bad, '<?php echo "not a font";' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$font = AIMG_ASSETS_PATH . 'fonts/Inter-Bold.ttf';

		$this->assertSame( 'aimg_font_type', Fonts::add_upload( $this->temp_copy( $font ), 'brand.woff2' )->get_error_code() );
		$this->assertSame( 'aimg_font_invalid', Fonts::add_upload( $bad, 'evil.ttf' )->get_error_code(), 'Checked by content, not name.' );
		$this->assertSame( array(), Fonts::uploaded() );
	}

	public function test_a_valid_upload_can_be_used_and_deleted() {
		$id = Fonts::add_upload( $this->temp_copy( AIMG_ASSETS_PATH . 'fonts/Poppins-Bold.ttf' ), 'Brand Font.ttf' );

		$this->assertIsString( $id );
		$this->assertStringStartsWith( 'custom-', $id );

		$path = Fonts::path( $id );
		$this->assertFileExists( $path );
		$this->assertStringContainsString( '/aimg-fonts/', wp_normalize_path( $path ) );
		$this->assertSame( 'Brand Font', Fonts::all()[ $id ][0] );

		$fonts = $this->rest( 'GET', '/aimg/v1/capabilities' )->get_data()['fonts'];
		$entry = wp_list_filter( $fonts, array( 'id' => $id ) );
		$this->assertTrue( reset( $entry )['uploaded'] );
		$this->assertStringContainsString( '/aimg-fonts/', reset( $entry )['url'] );

		$this->assertSame( 404, $this->rest( 'DELETE', '/aimg/v1/fonts/roboto-bold' )->get_status(), 'Bundled fonts stay.' );
		$this->assertSame( 200, $this->rest( 'DELETE', '/aimg/v1/fonts/' . $id )->get_status() );
		$this->assertFileDoesNotExist( $path );
		$this->assertStringEndsWith( 'Roboto-Bold.ttf', Fonts::path( $id ), 'Layers using it fall back.' );
	}

	public function test_upload_endpoint_needs_a_file_and_permission() {
		$this->assertSame( 400, $this->rest( 'POST', '/aimg/v1/fonts' )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 403, $this->rest( 'POST', '/aimg/v1/fonts' )->get_status() );
	}
}
