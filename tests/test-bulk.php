<?php
/**
 * Bulk generation for existing posts.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Bulk\Runner;

/**
 * @covers \ArtificialImageGenerator\Bulk\Runner
 * @covers \ArtificialImageGenerator\Bulk\RestController
 */
class Test_Bulk extends AIMG_TestCase {

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();
		add_filter( 'aimg_use_action_scheduler', '__return_false' );
		add_filter( 'aimg_kick_queue', '__return_false' );
		// Posts made here must not get an image on insert.
		$this->set_settings( array( 'is_post_thumbnail' => 'no' ) );
		delete_option( Runner::OPTION );
		delete_option( Runner::LOCK );
	}

	/**
	 * Tear down each test.
	 */
	public function tear_down() {
		wp_clear_scheduled_hook( Runner::HOOK );
		parent::tear_down();
	}

	/**
	 * A published post.
	 *
	 * @param array $args Post arguments.
	 *
	 * @return int
	 */
	private function post( $args = array() ) {
		return self::factory()->post->create(
			array_merge(
				array(
					'post_title'  => 'Bulk post',
					'post_status' => 'publish',
				),
				$args
			)
		);
	}

	public function test_preview_counts_posts_without_a_featured_image() {
		$this->create_template();
		$missing = $this->post();
		$with    = $this->post();
		set_post_thumbnail( $with, $this->create_png_attachment() );
		$removed = $this->post();
		update_post_meta( $removed, '_aimg_disable_auto', '1' );
		$this->post( array( 'post_status' => 'draft' ) );
		$this->post( array( 'post_type' => 'page' ) );

		$preview = Runner::preview( array( 'method' => 'template' ) );

		$this->assertSame( 1, $preview['count'] );
		$this->assertSame( $missing, $preview['posts'][0]['id'] );
		$this->assertSame( 0, $preview['ai_requests'] );
	}

	public function test_replace_includes_posts_with_an_image() {
		$this->create_template();
		$this->post();
		$with = $this->post();
		set_post_thumbnail( $with, $this->create_png_attachment() );

		$this->assertSame( 2, Runner::preview( array( 'replace' => true ) )['count'] );
	}

	public function test_filters_by_category_and_date() {
		$this->create_template();
		$cat = self::factory()->category->create();
		$in  = $this->post( array( 'post_date' => '2026-05-10 10:00:00' ) );
		wp_set_post_categories( $in, array( $cat ) );
		$this->post( array( 'post_date' => '2026-05-10 10:00:00' ) );
		$old = $this->post( array( 'post_date' => '2025-01-01 10:00:00' ) );
		wp_set_post_categories( $old, array( $cat ) );

		$preview = Runner::preview(
			array(
				'category' => $cat,
				'after'    => '2026-01-01',
			)
		);

		$this->assertSame( 1, $preview['count'] );
		$this->assertSame( $in, $preview['posts'][0]['id'] );
	}

	public function test_ai_method_without_a_key_is_refused() {
		$this->assertWPError( Runner::preview( array( 'method' => 'ai' ) ) );
		$this->assertWPError( Runner::start( array( 'method' => 'ai' ) ) );
	}

	public function test_a_run_generates_and_sets_featured_images() {
		$this->create_template();
		$first  = $this->post();
		$second = $this->post();

		$state = Runner::start( array( 'method' => 'template' ) );
		$this->assertSame( 'running', $state['status'] );
		$this->assertSame( 2, $state['total'] );
		$this->assertNotFalse( wp_next_scheduled( Runner::HOOK ) );

		Runner::run_batch();

		$state = Runner::state();
		$this->assertSame( 'done', $state['status'] );
		$this->assertSame( 2, $state['done'] );
		$this->assertSame( 0, $state['remaining'] );
		$this->assertTrue( has_post_thumbnail( $first ) );
		$this->assertTrue( has_post_thumbnail( $second ) );
		$this->assertSame( '1', get_post_meta( get_post_thumbnail_id( $first ), '_aimg_generated', true ) );
		$this->assertCount( 2, $state['recent'] );
	}

	public function test_posts_given_an_image_meanwhile_are_skipped() {
		$this->create_template();
		$post_id = $this->post();
		Runner::start( array() );
		$attachment = $this->create_png_attachment();
		set_post_thumbnail( $post_id, $attachment );

		Runner::run_batch();

		$this->assertSame( 1, Runner::state()['skipped'] );
		$this->assertSame( $attachment, get_post_thumbnail_id( $post_id ) );
	}

	public function test_a_second_run_is_refused_while_one_is_active() {
		$this->create_template();
		$this->post();
		Runner::start( array() );

		$error = Runner::start( array() );

		$this->assertWPError( $error );
		$this->assertSame( 'aimg_bulk_running', $error->get_error_code() );
	}

	public function test_pause_resume_and_cancel() {
		$this->create_template();
		$post_id = $this->post();
		Runner::start( array() );

		$this->assertSame( 'paused', Runner::control( 'pause' )['status'] );
		Runner::run_batch();
		$this->assertFalse( has_post_thumbnail( $post_id ) );

		$this->assertSame( 'running', Runner::control( 'resume' )['status'] );
		$state = Runner::control( 'cancel' );
		$this->assertSame( 'cancelled', $state['status'] );
		$this->assertSame( 0, $state['remaining'] );
		$this->assertFalse( wp_next_scheduled( Runner::HOOK ) );

		$this->assertSame( 'idle', Runner::control( 'clear' )['status'] );
	}

	public function test_a_run_waits_when_the_ai_limit_is_reached() {
		$this->set_settings(
			array(
				'is_post_thumbnail' => 'no',
				'api_key'           => 'sk-test',
				'ai_hourly_limit'   => 1,
			)
		);
		$this->stub_openai();
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->post( array( 'post_author' => $author ) );
		$this->post( array( 'post_author' => $author ) );

		Runner::start( array( 'method' => 'ai' ) );
		Runner::run_batch();

		$state = Runner::state();
		$this->assertSame( 'running', $state['status'] );
		$this->assertSame( 1, $state['done'] );
		$this->assertSame( 1, $state['remaining'] );
		$this->assertSame( 0, $state['failed'] );
		$this->assertTrue( $state['waiting'] );
		$this->assertNotSame( '', $state['message'] );
	}

	public function test_a_held_lock_stops_a_second_batch() {
		$this->create_template();
		$post_id = $this->post();
		Runner::start( array() );
		add_option( Runner::LOCK, time() );

		Runner::run_batch();

		$this->assertFalse( has_post_thumbnail( $post_id ) );
	}

	public function test_rest_routes_need_manage_options() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame( 403, $this->rest( 'GET', '/aimg/v1/bulk' )->get_status() );
		$this->assertSame( 403, $this->rest( 'POST', '/aimg/v1/bulk', array( 'args' => array() ) )->get_status() );
	}

	public function test_rest_preview_and_start() {
		$this->create_template();
		$this->post();

		$response = $this->rest( 'GET', '/aimg/v1/bulk' );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'idle', $response->get_data()['run']['status'] );
		$this->assertContains( 'post', wp_list_pluck( $response->get_data()['options']['post_types'], 'value' ) );

		$preview = $this->rest( 'POST', '/aimg/v1/bulk/preview', array( 'args' => array( 'method' => 'template' ) ) );
		$this->assertSame( 1, $preview->get_data()['count'] );

		$start = $this->rest( 'POST', '/aimg/v1/bulk', array( 'args' => array( 'method' => 'template' ) ) );
		$this->assertSame( 'running', $start->get_data()['status'] );
		$this->assertArrayNotHasKey( 'queue', $start->get_data() );
	}
}
