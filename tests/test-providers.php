<?php
/**
 * AI providers.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Providers\OpenAI;
use ArtificialImageGenerator\Providers\ProviderInterface;
use ArtificialImageGenerator\Providers\Registry;
use ArtificialImageGenerator\Providers\Result;

/**
 * @covers \ArtificialImageGenerator\Providers\OpenAI
 * @covers \ArtificialImageGenerator\Providers\Registry
 */
class Test_Providers extends AIMG_TestCase {

	/**
	 * Tear down each test.
	 */
	public function tear_down() {
		Registry::reset();
		parent::tear_down();
	}

	/**
	 * Body for a model with the given options.
	 *
	 * @param string $model   Model.
	 * @param array  $options Size, quality, n.
	 *
	 * @return array
	 */
	private function body( $model, $options = array() ) {
		return ( new OpenAI() )->get_request_body(
			'a cat',
			array_merge(
				array(
					'model'   => $model,
					'size'    => 'square',
					'quality' => 'auto',
					'n'       => 1,
				),
				$options
			)
		);
	}

	public function test_default_request_body_keeps_the_1_5_shape() {
		$this->assertSame(
			array(
				'model'  => 'gpt-image-2.5-flare',
				'prompt' => 'a cat',
				'n'      => 1,
				'size'   => '1024x1024',
			),
			$this->body( ( new OpenAI() )->get_default_model() )
		);
	}

	public function test_only_current_openai_models_are_offered() {
		$this->assertSame( array( 'gpt-image-2.5-flare', 'gpt-image-2.5-sunburst', 'gpt-image-2' ), array_keys( ( new OpenAI() )->get_models() ) );
		$this->assertSame( array_keys( ( new OpenAI() )->get_models() ), array_keys( ArtificialImageGenerator\Admin\Settings::get_models() ) );
	}

	public function test_retired_models_fall_back_to_the_default() {
		$this->set_settings(
			array(
				'api_key'   => 'sk-test',
				'api_model' => 'gpt-image-1',
			)
		);
		$this->stub_openai();

		ArtificialImageGenerator\Generator::generate_ai( 'a cat' );
		( new OpenAI() )->generate( 'a cat', array( 'model' => 'dall-e-3' ) );

		$this->assertSame( 'gpt-image-2.5-flare', $this->openai_bodies[0]['model'] );
		$this->assertSame( 'gpt-image-2.5-flare', $this->openai_bodies[1]['model'] );
	}

	public function test_sizes_and_quality() {
		$this->assertSame( '1536x1024', $this->body( 'gpt-image-2.5-sunburst', array( 'size' => 'landscape' ) )['size'] );
		$this->assertSame( '1024x1536', $this->body( 'gpt-image-2', array( 'size' => 'portrait' ) )['size'] );
		$this->assertSame( 'high', $this->body( 'gpt-image-2.5-flare', array( 'quality' => 'high' ) )['quality'] );
		$this->assertArrayNotHasKey( 'quality', $this->body( 'gpt-image-2.5-flare', array( 'quality' => 'auto' ) ) );
	}

	public function test_limits() {
		$this->assertSame( 4, $this->body( 'gpt-image-2', array( 'n' => 4 ) )['n'] );
		$this->assertSame( 10, $this->body( 'gpt-image-2', array( 'n' => 50 ) )['n'] );

		$body = ( new OpenAI() )->get_request_body(
			str_repeat( 'a', 33000 ),
			array(
				'model'   => 'gpt-image-2.5-flare',
				'size'    => 'square',
				'quality' => 'auto',
				'n'       => 1,
			)
		);
		$this->assertSame( 32000, strlen( $body['prompt'] ) );
	}

	public function test_request_body_filter_still_applies() {
		$this->set_settings( array( 'api_key' => 'sk-test' ) );
		$this->stub_openai();
		add_filter(
			'aimg_generate_request_body',
			function ( $body ) {
				$body['user'] = 'filtered';
				return $body;
			}
		);

		( new OpenAI() )->generate( 'a cat' );

		$this->assertSame( 'filtered', $this->openai_bodies[0]['user'] );
	}

	public function test_api_errors_are_returned() {
		$this->set_settings( array( 'api_key' => 'sk-test' ) );
		$this->stub_openai( 503 );

		$result = ( new OpenAI() )->generate( 'a cat' );

		$this->assertWPError( $result );
		$this->assertSame( 'Service unavailable', $result->get_error_message() );
	}

	public function test_timeouts_get_a_plain_message() {
		$this->set_settings( array( 'api_key' => 'sk-test' ) );
		add_filter(
			'pre_http_request',
			function () {
				return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 120000 milliseconds' );
			}
		);

		$result = ( new OpenAI() )->generate( 'a cat' );

		$this->assertSame( 'The AI service did not respond in time. Please try again.', $result->get_error_message() );
	}

	public function test_providers_can_be_added() {
		$fake = $this->createMock( ProviderInterface::class );
		$fake->method( 'get_id' )->willReturn( 'fake' );
		$fake->method( 'get_models' )->willReturn( array( 'fake-1' => 'Fake' ) );
		$fake->method( 'get_default_model' )->willReturn( 'fake-1' );
		$fake->method( 'is_configured' )->willReturn( true );
		$fake->method( 'generate' )->willReturn( array( new Result( array( 'data' => base64_decode( self::PNG ) ) ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		add_filter(
			'aimg_providers',
			function ( $providers ) use ( $fake ) {
				$providers['fake'] = $fake;
				return $providers;
			}
		);
		Registry::reset();
		$this->set_settings( array( 'ai_provider' => 'fake' ) );

		$ids = ArtificialImageGenerator\Generator::generate_ai( 'a cat' );

		$this->assertCount( 1, $ids );
		$this->assertSame( 'fake', get_post_meta( $ids[0], '_aimg_generated_data', true )['provider'] );
	}
}
