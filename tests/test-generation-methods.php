<?php
/**
 * Generation methods, the background queue and their REST endpoints.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Generator;
use ArtificialImageGenerator\Queue;

/**
 * @covers \ArtificialImageGenerator\Generator
 * @covers \ArtificialImageGenerator\Queue
 * @covers \ArtificialImageGenerator\GenerateImages
 * @covers \ArtificialImageGenerator\RestAPI
 */
class Test_Generation_Methods extends AIMG_TestCase {

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();
		add_filter( 'aimg_use_action_scheduler', '__return_false' );
		add_filter( 'aimg_kick_queue', '__return_false' );
		$this->set_settings( array( 'api_key' => 'sk-test' ) );
	}

	/**
	 * Create a post through wp_insert_post(), which fires wp_after_insert_post.
	 *
	 * @param string $status Post status.
	 *
	 * @return int Post ID.
	 */
	private function create_post( $status = 'publish' ) {
		return wp_insert_post(
			array(
				'post_title'  => 'A post about bees',
				'post_status' => $status,
			)
		);
	}

	/**
	 * Provenance source of a post's featured image.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private function featured_source( $post_id ) {
		$data = get_post_meta( get_post_thumbnail_id( $post_id ), '_aimg_generated_data', true );

		return is_array( $data ) ? $data['source'] : '';
	}

	public function test_template_is_the_default_method() {
		$this->create_template();

		$post_id = $this->create_post();

		$this->assertSame( 'template', Generator::get_method() );
		$this->assertSame( 'template', $this->featured_source( $post_id ) );
	}

	public function test_default_template_setting_is_used() {
		$this->create_template();
		$chosen = $this->create_template();
		$this->set_settings( array( 'default_template_id' => $chosen ) );

		for ( $i = 0; $i < 3; $i++ ) {
			$post_id = $this->create_post();
			$this->assertSame( $chosen, get_post_meta( get_post_thumbnail_id( $post_id ), '_aimg_generated_data', true )['template_id'] );
		}
	}

	public function test_ai_method_queues_on_publish_and_runs_in_the_background() {
		$this->set_settings( array( 'generation_method' => 'ai' ) );
		$this->stub_openai();

		$post_id = $this->create_post();

		$this->assertSame( 'queued', Queue::get_status( $post_id )['status'] );
		$this->assertFalse( has_post_thumbnail( $post_id ), 'Nothing is generated while saving.' );
		$this->assertNotFalse( wp_next_scheduled( Queue::HOOK, array( $post_id, 'ai', false ) ) );

		Queue::run( $post_id, 'ai', false );

		$this->assertSame( 'done', Queue::get_status( $post_id )['status'] );
		$this->assertSame( 'auto', $this->featured_source( $post_id ) );
		$this->assertSame( '1536x1024', $this->openai_bodies[0]['size'], 'Automatic AI images default to landscape.' );
		$this->assertStringContainsString( 'A post about bees', $this->openai_bodies[0]['prompt'] );
	}

	public function test_ai_method_waits_for_drafts_to_be_published() {
		$this->set_settings( array( 'generation_method' => 'ai' ) );

		$post_id = $this->create_post( 'draft' );

		$this->assertSame( '', Queue::get_status( $post_id )['status'] );
	}

	public function test_a_queued_job_runs_once() {
		$this->set_settings( array( 'generation_method' => 'ai' ) );
		$this->stub_openai();
		$post_id = $this->create_post();

		$this->assertFalse( Queue::enqueue( $post_id, 'ai' ), 'A second save does not queue again.' );

		Queue::run( $post_id, 'ai', false );
		Queue::run( $post_id, 'ai', false );

		$this->assertCount( 1, $this->openai_bodies );
	}

	public function test_an_image_chosen_by_the_author_meanwhile_is_kept() {
		$this->set_settings( array( 'generation_method' => 'ai' ) );
		$this->stub_openai();
		$post_id = $this->create_post();
		$chosen  = $this->create_png_attachment();
		set_post_thumbnail( $post_id, $chosen );

		Queue::run( $post_id, 'ai', false );

		$this->assertSame( $chosen, get_post_thumbnail_id( $post_id ) );
		$this->assertCount(
			0,
			get_posts(
				array(
					'post_type'   => 'attachment',
					'post_parent' => $post_id,
					'fields'      => 'ids',
				)
			),
			'The unused AI image is deleted.'
		);
	}

	public function test_failures_are_reported() {
		$this->set_settings( array( 'generation_method' => 'ai' ) );
		$this->stub_openai( 503 );
		$post_id = $this->create_post();

		Queue::run( $post_id, 'ai', false );

		$status = Queue::get_status( $post_id );
		$this->assertSame( 'failed', $status['status'] );
		$this->assertSame( 'Service unavailable', $status['error'] );
	}

	public function test_ai_falls_back_to_a_template() {
		$this->create_template();
		$this->set_settings( array( 'generation_method' => 'ai_template' ) );
		$this->stub_openai( 503 );
		$post_id = $this->create_post();

		Queue::run( $post_id, 'ai_template', false );

		$this->assertSame( 'template', $this->featured_source( $post_id ) );
	}

	public function test_template_falls_back_to_ai_when_there_is_no_template() {
		$this->set_settings( array( 'generation_method' => 'template_ai' ) );

		$post_id = $this->create_post();

		$this->assertSame( 'queued', Queue::get_status( $post_id )['status'] );
		$this->assertNotFalse( wp_next_scheduled( Queue::HOOK, array( $post_id, 'ai', false ) ) );
	}

	public function test_automatic_ai_counts_against_the_authors_limit() {
		$this->set_settings(
			array(
				'generation_method' => 'ai',
				'ai_hourly_limit'   => 1,
			)
		);
		$this->stub_openai();

		$first = $this->create_post();
		Queue::run( $first, 'ai', false );
		$second = $this->create_post();
		Queue::run( $second, 'ai', false );

		$this->assertSame( 'done', Queue::get_status( $first )['status'] );
		$this->assertSame( 'failed', Queue::get_status( $second )['status'] );
	}

	public function test_variations_are_returned_and_counted() {
		$this->set_settings( array( 'ai_hourly_limit' => 3 ) );
		$this->stub_openai();

		$response = $this->rest(
			'POST',
			'/aimg/v1/generate',
			array(
				'prompt'  => 'a cat',
				'n'       => 2,
				'size'    => 'portrait',
				'quality' => 'high',
				'style'   => 'watercolor',
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 2, $data['images'] );
		$this->assertSame( $data['images'][0]['id'], $data['id'] );
		$this->assertSame( '1024x1536', $this->openai_bodies[0]['size'] );
		$this->assertSame( 'high', $this->openai_bodies[0]['quality'] );
		$this->assertStringStartsWith( 'a cat Watercolor', $this->openai_bodies[0]['prompt'] );
		$this->assertSame( 'a cat', get_post_meta( $data['id'], '_wp_attachment_image_alt', true ), 'Alt text is the prompt without the style.' );

		$this->assertSame( 429, $this->rest( 'POST', '/aimg/v1/generate', array( 'prompt' => 'a dog', 'n' => 2 ) )->get_status(), 'Only 1 of 3 left.' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	}

	public function test_prompt_endpoint() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Saved' ) );

		$prompt = $this->rest(
			'POST',
			'/aimg/v1/prompt',
			array(
				'post_id' => $post_id,
				'title'   => 'Unsaved',
			)
		)->get_data()['prompt'];

		$this->assertStringContainsString( '"Unsaved"', $prompt );
	}

	public function test_template_preview_endpoint_creates_no_attachment() {
		$template = $this->create_template();

		$image = $this->rest( 'POST', '/aimg/v1/templates/' . $template . '/preview', array( 'title' => 'Hello' ) )->get_data()['image'];

		$this->assertStringStartsWith( 'data:image/png;base64,', $image );
		$this->assertCount(
			0,
			get_posts(
				array(
					'post_type' => 'attachment',
					'fields'    => 'ids',
				)
			)
		);
	}

	public function test_featured_endpoint_with_a_template() {
		$this->create_template();
		$post_id = self::factory()->post->create( array( 'post_title' => 'Regenerate me' ) );
		$old     = $this->create_png_attachment();
		set_post_thumbnail( $post_id, $old );

		$data = $this->rest( 'POST', '/aimg/v1/featured/' . $post_id )->get_data();

		$this->assertSame( 'done', $data['status'] );
		$this->assertNotSame( $old, $data['attachment_id'] );
		$this->assertSame( $data['attachment_id'], get_post_thumbnail_id( $post_id ) );
		$this->assertNotNull( get_post( $old ), 'The previous image stays in the Media Library.' );
	}

	public function test_featured_endpoint_with_ai_queues_a_replacement() {
		$this->set_settings( array( 'generation_method' => 'ai' ) );
		$this->stub_openai();
		$post_id = self::factory()->post->create( array( 'post_title' => 'Draft', 'post_status' => 'draft' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$old     = $this->create_png_attachment();
		set_post_thumbnail( $post_id, $old );

		$this->assertSame( 'queued', $this->rest( 'POST', '/aimg/v1/featured/' . $post_id )->get_data()['status'] );

		Queue::run( $post_id, 'ai', true );

		$this->assertNotSame( $old, get_post_thumbnail_id( $post_id ) );
		$this->assertSame( 'done', $this->rest( 'GET', '/aimg/v1/status/' . $post_id )->get_data()['status'] );
	}

	public function test_featured_endpoint_respects_ai_access() {
		$this->set_settings(
			array(
				'generation_method' => 'ai',
				'ai_access'         => 'admins',
			)
		);
		$author  = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Mine',
				'post_author' => $author,
			)
		);
		wp_set_current_user( $author );

		$this->assertSame( 403, $this->rest( 'POST', '/aimg/v1/featured/' . $post_id )->get_status() );
	}

	public function test_status_endpoint_requires_edit_access() {
		$post_id = self::factory()->post->create();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( 403, $this->rest( 'GET', '/aimg/v1/status/' . $post_id )->get_status() );
	}

	public function test_custom_methods_can_short_circuit() {
		add_filter(
			'aimg_pre_generate_for_post',
			function ( $pre, $post_id, $method ) {
				return 'custom' === $method ? new WP_Error( 'custom', 'Handled' ) : $pre;
			},
			10,
			3
		);

		$this->assertSame( 'Handled', Generator::generate_for_post( self::factory()->post->create(), 'custom' )->get_error_message() );
	}
}
