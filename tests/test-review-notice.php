<?php
/**
 * Review request notice.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Admin\ReviewNotice;

/**
 * @covers \ArtificialImageGenerator\Admin\ReviewNotice
 */
class Test_Review_Notice extends AIMG_TestCase {

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();
		set_current_screen( 'toplevel_page_image-generator' );
	}

	/**
	 * Tear down each test.
	 */
	public function tear_down() {
		unset( $_GET['add'], $_GET['answer'], $_REQUEST['_wpnonce'] );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * Create generated images.
	 *
	 * @param int $count How many.
	 */
	private function generate( $count ) {
		for ( $i = 0; $i < $count; $i++ ) {
			update_post_meta( $this->create_png_attachment( "aimg-review-$i.png" ), '_aimg_generated', '1' );
		}
	}

	/**
	 * Run the answer handler and return the redirect location.
	 *
	 * @param string $answer Answer.
	 *
	 * @return string
	 */
	private function answer( $answer ) {
		$_GET['answer']       = $answer;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'aimg_review_notice' );

		$location = '';
		$stop     = function ( $url ) use ( &$location ) {
			$location = $url;
			throw new Exception( 'redirect' );
		};

		add_filter( 'wp_redirect', $stop );

		try {
			ReviewNotice::handle();
		} catch ( Exception $e ) {
			unset( $e );
		}

		remove_filter( 'wp_redirect', $stop );

		return $location;
	}

	public function test_shows_after_threshold() {
		$this->generate( ReviewNotice::THRESHOLD - 1 );
		$this->assertFalse( ReviewNotice::should_show() );

		$this->generate( 1 );
		$this->assertTrue( ReviewNotice::should_show() );

		ob_start();
		ReviewNotice::render();
		$this->assertStringContainsString( 'aimg-review-notice', ob_get_clean() );
	}

	public function test_only_on_plugin_screens_for_admins() {
		$this->generate( ReviewNotice::THRESHOLD );

		set_current_screen( 'image-generator_page_aimg-settings' );
		$this->assertTrue( ReviewNotice::should_show() );

		set_current_screen( 'dashboard' );
		$this->assertFalse( ReviewNotice::should_show() );

		set_current_screen( 'toplevel_page_image-generator' );
		$_GET['add'] = '1';
		$this->assertFalse( ReviewNotice::should_show(), 'Not above the Studio.' );
		unset( $_GET['add'] );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertFalse( ReviewNotice::should_show() );
	}

	public function test_later_snoozes() {
		$this->generate( ReviewNotice::THRESHOLD );

		$this->answer( 'later' );
		$this->assertFalse( ReviewNotice::should_show() );

		update_user_meta( $this->admin_id, ReviewNotice::META_KEY, time() - 1 );
		$this->assertTrue( ReviewNotice::should_show(), 'Shows again when the snooze ends.' );
	}

	public function test_review_and_never_hide_for_good() {
		$this->generate( ReviewNotice::THRESHOLD );

		$this->assertSame( ReviewNotice::REVIEW_URL, $this->answer( 'review' ) );
		$this->assertSame( 'never', get_user_meta( $this->admin_id, ReviewNotice::META_KEY, true ) );
		$this->assertFalse( ReviewNotice::should_show() );

		delete_user_meta( $this->admin_id, ReviewNotice::META_KEY );
		$this->answer( 'never' );
		$this->assertFalse( ReviewNotice::should_show() );
	}
}
