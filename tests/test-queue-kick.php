<?php
/**
 * Starting queued jobs right away.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Queue;

/**
 * @covers \ArtificialImageGenerator\Queue
 */
class Test_Queue_Kick extends AIMG_TestCase {

	/**
	 * Requests sent to admin-ajax.php.
	 *
	 * @var array
	 */
	private $kicks = array();

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();
		add_filter( 'aimg_use_action_scheduler', '__return_false' );
		$this->set_settings(
			array(
				'api_key'           => 'sk-test',
				'generation_method' => 'ai',
			)
		);
		$this->stub_openai();

		$this->kicks = array();
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) {
				if ( false !== strpos( $url, 'admin-ajax.php' ) ) {
					$this->kicks[] = $args;
					return array(
						'headers'  => array(),
						'body'     => '',
						'response' => array(
							'code'    => 200,
							'message' => 'OK',
						),
						'cookies'  => array(),
						'filename' => null,
					);
				}
				return $pre;
			},
			10,
			3
		);
	}

	/**
	 * A published post, which queues an AI job.
	 *
	 * @return int
	 */
	private function queued_post() {
		return wp_insert_post(
			array(
				'post_title'  => 'Kick me',
				'post_status' => 'publish',
			)
		);
	}

	public function test_enqueue_sends_a_non_blocking_request() {
		$post_id = $this->queued_post();

		$this->assertCount( 1, $this->kicks );
		$this->assertFalse( $this->kicks[0]['blocking'] );
		$this->assertSame( 'aimg_run_job', $this->kicks[0]['body']['action'] );
		$this->assertSame( $post_id, $this->kicks[0]['body']['post_id'] );
		$this->assertSame( get_transient( 'aimg_kick_' . $post_id ), $this->kicks[0]['body']['token'] );
	}

	public function test_kick_can_be_turned_off() {
		add_filter( 'aimg_kick_queue', '__return_false' );

		$this->queued_post();

		$this->assertCount( 0, $this->kicks );
	}

	public function test_a_wrong_token_runs_nothing() {
		$post_id = $this->queued_post();

		$this->assertFalse( Queue::run_kicked( $post_id, 'wrong' ) );
		$this->assertFalse( Queue::run_kicked( $post_id, '' ) );
		$this->assertSame( 'queued', Queue::get_status( $post_id )['status'] );
	}

	public function test_the_right_token_runs_the_job_once() {
		$post_id = $this->queued_post();
		$token   = $this->kicks[0]['body']['token'];

		$this->assertTrue( Queue::run_kicked( $post_id, $token ) );
		$this->assertSame( 'done', Queue::get_status( $post_id )['status'] );
		$this->assertTrue( has_post_thumbnail( $post_id ) );

		$this->assertFalse( Queue::run_kicked( $post_id, $token ), 'Tokens are single-use.' );
	}

	public function test_the_backup_action_does_nothing_after_a_kicked_run() {
		$post_id = $this->queued_post();
		Queue::run_kicked( $post_id, $this->kicks[0]['body']['token'] );

		Queue::run( $post_id, 'ai', false );

		$this->assertCount( 1, $this->openai_bodies );
	}

	public function test_a_job_claimed_by_another_process_is_not_run_again() {
		$post_id = $this->queued_post();
		update_post_meta( $post_id, Queue::STATUS_META, 'running' );

		Queue::run( $post_id, 'ai', false );

		$this->assertCount( 0, $this->openai_bodies );
		$this->assertSame( 'running', get_post_meta( $post_id, Queue::STATUS_META, true ) );
	}
}
