<?php
/**
 * First-run setup screen.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Admin\Setup;

/**
 * @covers \ArtificialImageGenerator\Admin\Setup
 */
class Test_Setup extends AIMG_TestCase {

	public function test_only_a_fresh_install_is_marked_for_setup() {
		Setup::activated( false );
		$this->assertFalse( get_option( Setup::OPTION ) );

		Setup::activated( true );
		$this->assertSame( '1', (string) get_option( Setup::OPTION ) );
	}

	public function test_finishing_clears_the_flag_for_admins_only() {
		Setup::activated( true );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 403, rest_do_request( new WP_REST_Request( 'POST', '/aimg/v1/setup/done' ) )->get_status() );
		$this->assertNotFalse( get_option( Setup::OPTION ) );

		wp_set_current_user( $this->admin_id );
		$this->assertSame( 200, rest_do_request( new WP_REST_Request( 'POST', '/aimg/v1/setup/done' ) )->get_status() );
		$this->assertFalse( get_option( Setup::OPTION ) );
	}

	public function test_app_data_offers_three_methods_and_never_sends_keys() {
		$template = $this->create_template();
		$this->set_settings(
			array(
				'api_key'           => 'sk-secret-value-WXYZ',
				'generation_method' => 'ai',
			)
		);

		$data = Setup::data();
		$json = wp_json_encode( $data );

		$this->assertSame( array( 'template', 'ai_template', 'stock_template' ), wp_list_pluck( $data['methods'], 'id' ) );
		$this->assertSame( 'template', $data['values']['generation_method'], 'A method not offered here starts on template.' );
		$this->assertContains( (string) $template, wp_list_pluck( $data['templates'], 'id' ) );
		$this->assertTrue( $data['secrets']['api_key']['saved'] );
		$this->assertStringNotContainsString( 'sk-secret-value', $json );
	}

	public function test_the_notice_shows_on_the_dashboard_until_setup_is_done() {
		Setup::activated( true );
		set_current_screen( 'dashboard' );

		ob_start();
		( new Setup() )->notice();
		$this->assertStringContainsString( 'page=aimg-setup', ob_get_clean() );

		delete_option( Setup::OPTION );
		ob_start();
		( new Setup() )->notice();
		$this->assertSame( '', ob_get_clean() );
	}
}
