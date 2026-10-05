<?php
/**
 * Settings and admin notices.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Admin\Settings;

/**
 * @covers \ArtificialImageGenerator\Admin\Settings
 * @covers \ArtificialImageGenerator\Plugin
 */
class Test_Settings extends AIMG_TestCase {

	/**
	 * Settings handler.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->settings = new Settings();
	}

	public function test_api_key_is_not_printed() {
		update_option( 'aimg_settings', array( 'api_key' => 'sk-secret-value-ABCD' ) );

		ob_start();
		$this->settings->api_key_field();
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'sk-secret-value', $html );
		$this->assertStringContainsString( 'ABCD', $html );
	}

	public function test_api_key_handling() {
		update_option( 'aimg_settings', array( 'api_key' => 'sk-saved' ) );

		$this->assertSame(
			'sk-saved',
			$this->settings->sanitize_settings(
				array(
					'api_key_form' => '1',
					'api_key'      => '',
				)
			)['api_key'],
			'Empty form field keeps the key.'
		);
		$this->assertSame(
			'sk-new',
			$this->settings->sanitize_settings(
				array(
					'api_key_form' => '1',
					'api_key'      => 'sk-new',
				)
			)['api_key'],
			'New key replaces it.'
		);
		$this->assertSame(
			'',
			$this->settings->sanitize_settings(
				array(
					'api_key_form'   => '1',
					'remove_api_key' => '1',
				)
			)['api_key'],
			'Remove checkbox clears it.'
		);
		$this->assertSame( '', $this->settings->sanitize_settings( array( 'api_key' => '' ) )['api_key'], 'Saving the option directly still clears it.' );
	}

	public function test_ai_settings_are_sanitized() {
		$clean = $this->settings->sanitize_settings(
			array(
				'ai_access'       => 'root',
				'ai_hourly_limit' => '7',
			)
		);

		$this->assertSame( 'authors', $clean['ai_access'] );
		$this->assertSame( 7, $clean['ai_hourly_limit'] );
		$this->assertSame( 20, $this->settings->sanitize_settings( array() )['ai_hourly_limit'] );
	}

	public function test_checkboxes_keep_a_stored_no() {
		$clean = $this->settings->sanitize_settings( array( 'is_page_thumbnail' => 'no' ) );

		$this->assertSame( 'no', $clean['is_page_thumbnail'] );
	}

	public function test_flash_notices_are_per_user() {
		artificial_image_generator()->flash_notice( 'Only for the admin' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		artificial_image_generator()->display_flash_notices();
		$this->assertStringNotContainsString( 'Only for the admin', ob_get_clean() );

		wp_set_current_user( $this->admin_id );
		ob_start();
		artificial_image_generator()->display_flash_notices();
		$this->assertStringContainsString( 'Only for the admin', ob_get_clean() );

		ob_start();
		artificial_image_generator()->display_flash_notices();
		$this->assertSame( '', ob_get_clean() );
	}
}
