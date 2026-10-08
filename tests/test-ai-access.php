<?php
/**
 * AI access, limits and the prompt request.
 *
 * @package ArtificialImageGenerator
 */

/**
 * @covers ::aimg_user_can_use_ai
 * @covers ::aimg_consume_ai_quota
 * @covers \ArtificialImageGenerator\RestAPI
 */
class Test_AI_Access extends AIMG_TestCase {

	/**
	 * Requests sent to OpenAI.
	 *
	 * @var array
	 */
	private $requests = array();

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->requests = array();
		add_filter( 'pre_http_request', array( $this, 'fake_openai' ), 10, 3 );
	}

	/**
	 * Answer OpenAI requests with a tiny image.
	 *
	 * @param mixed  $pre  Short-circuit value.
	 * @param array  $args Request arguments.
	 * @param string $url  Request URL.
	 *
	 * @return mixed
	 */
	public function fake_openai( $pre, $args, $url ) {
		if ( false === strpos( $url, 'api.openai.com' ) ) {
			return $pre;
		}

		$this->requests[] = $args;

		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( array( 'data' => array( array( 'b64_json' => self::PNG ) ) ) ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Save plugin settings directly.
	 *
	 * @param array $settings Settings.
	 */
	private function settings( $settings ) {
		update_option( 'aimg_settings', array_merge( array( 'api_key' => 'sk-test' ), $settings ) );
	}

	public function test_authors_can_use_ai_by_default() {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );

		$this->assertTrue( aimg_user_can_use_ai( $author ) );
	}

	public function test_admins_only_blocks_authors() {
		$this->settings( array( 'ai_access' => 'admins' ) );
		$author = self::factory()->user->create( array( 'role' => 'author' ) );

		$this->assertFalse( aimg_user_can_use_ai( $author ) );
		$this->assertTrue( aimg_user_can_use_ai( $this->admin_id ) );

		wp_set_current_user( $author );
		$this->assertSame( 403, $this->rest( 'POST', '/aimg/v1/generate', array( 'prompt' => 'a cat' ) )->get_status() );
		$this->assertCount( 0, $this->requests );
	}

	public function test_templates_stay_available_when_ai_is_restricted() {
		$this->settings( array( 'ai_access' => 'admins' ) );
		$template = $this->create_template();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );

		$this->assertSame( 200, $this->rest( 'POST', '/aimg/v1/generate', array( 'template_id' => $template ) )->get_status() );
	}

	public function test_hourly_limit() {
		$this->settings( array( 'ai_hourly_limit' => 2 ) );

		$statuses = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$statuses[] = $this->rest( 'POST', '/aimg/v1/generate', array( 'prompt' => 'a cat ' . $i ) )->get_status();
		}

		$this->assertSame( array( 200, 200, 429 ), $statuses );
		$this->assertCount( 2, $this->requests );
	}

	public function test_zero_means_no_limit() {
		$this->settings( array( 'ai_hourly_limit' => 0 ) );

		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertTrue( aimg_consume_ai_quota() );
		}
	}

	public function test_request_timeout_is_180_seconds_and_filterable_per_quality() {
		$this->settings( array() );
		$this->rest( 'POST', '/aimg/v1/generate', array( 'prompt' => 'a cat' ) );

		$this->assertSame( 180, $this->requests[0]['timeout'] );

		add_filter(
			'aimg_generate_timeout',
			function ( $timeout, $model, $quality ) {
				return 'max' === $quality ? 300 : $timeout;
			},
			10,
			3
		);
		$this->rest(
			'POST',
			'/aimg/v1/generate',
			array(
				'prompt'  => 'a cat',
				'quality' => 'max',
			)
		);

		$this->assertSame( 300, $this->requests[1]['timeout'] );
	}

	public function test_images_cannot_be_attached_to_posts_the_user_cannot_edit() {
		$template = $this->create_template();
		$other    = self::factory()->post->create( array( 'post_author' => $this->admin_id ) );
		$author   = self::factory()->user->create( array( 'role' => 'author' ) );
		$own      = self::factory()->post->create( array( 'post_author' => $author ) );
		wp_set_current_user( $author );

		$this->assertSame(
			403,
			$this->rest(
				'POST',
				'/aimg/v1/generate',
				array(
					'template_id' => $template,
					'post_id'     => $other,
				)
			)->get_status()
		);
		$this->assertSame(
			200,
			$this->rest(
				'POST',
				'/aimg/v1/generate',
				array(
					'template_id' => $template,
					'post_id'     => $own,
				)
			)->get_status()
		);
	}
}
