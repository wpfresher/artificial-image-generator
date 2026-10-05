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

	public function test_default_request_body_is_unchanged_from_1_5() {
		$this->assertSame(
			array(
				'model'  => 'gpt-image-1',
				'prompt' => 'a cat',
				'n'      => 1,
				'size'   => '1024x1024',
			),
			$this->body( 'gpt-image-1' )
		);
		$this->assertSame( 'standard', $this->body( 'dall-e-3' )['quality'] );
	}

	public function test_sizes_and_quality_map_per_model() {
		$this->assertSame( '1536x1024', $this->body( 'gpt-image-1', array( 'size' => 'landscape' ) )['size'] );
		$this->assertSame( '1024x1792', $this->body( 'dall-e-3', array( 'size' => 'portrait' ) )['size'] );
		$this->assertSame( '1024x1024', $this->body( 'dall-e-2', array( 'size' => 'landscape' ) )['size'] );
		$this->assertSame( 'high', $this->body( 'gpt-image-1-mini', array( 'quality' => 'high' ) )['quality'] );
		$this->assertSame( 'hd', $this->body( 'dall-e-3', array( 'quality' => 'high' ) )['quality'] );
		$this->assertArrayNotHasKey( 'quality', $this->body( 'dall-e-2', array( 'quality' => 'high' ) ) );
	}

	public function test_limits_per_model() {
		$this->assertSame( 1, $this->body( 'dall-e-3', array( 'n' => 4 ) )['n'] );
		$this->assertSame( 4, $this->body( 'gpt-image-1', array( 'n' => 4 ) )['n'] );

		$body = ( new OpenAI() )->get_request_body(
			str_repeat( 'a', 1500 ),
			array(
				'model'   => 'dall-e-2',
				'size'    => 'square',
				'quality' => 'auto',
				'n'       => 1,
			)
		);
		$this->assertSame( 1000, strlen( $body['prompt'] ) );
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
