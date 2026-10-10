<?php
/**
 * Stock photos: providers, REST endpoints, imports and the automatic method.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Generator;
use ArtificialImageGenerator\Stock\Importer;
use ArtificialImageGenerator\Stock\Keywords;
use ArtificialImageGenerator\Stock\Registry;

/**
 * @covers \ArtificialImageGenerator\Stock\Unsplash
 * @covers \ArtificialImageGenerator\Stock\Pexels
 * @covers \ArtificialImageGenerator\Stock\Pixabay
 * @covers \ArtificialImageGenerator\Stock\Importer
 * @covers \ArtificialImageGenerator\Stock\RestController
 */
class Test_Stock extends AIMG_TestCase {

	/**
	 * Requested URLs and their headers.
	 *
	 * @var array[]
	 */
	private $requests = array();

	/**
	 * Image bytes the stub serves for downloads.
	 *
	 * @var string
	 */
	private $download = '';

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();

		Registry::reset();
		$this->requests = array();
		$this->download = base64_decode( self::PNG ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$this->set_settings(
			array(
				'unsplash_key' => 'unsplash-key',
				'pexels_key'   => 'pexels-key',
				'pixabay_key'  => 'pixabay-key',
			)
		);

		add_filter( 'pre_http_request', array( $this, 'stub' ), 10, 3 );
	}

	/**
	 * Tear down each test.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'stub' ) );
		Registry::reset();

		parent::tear_down();
	}

	/**
	 * Answer the stock APIs and image hosts.
	 *
	 * @param mixed  $pre  Short-circuit value.
	 * @param array  $args Request arguments.
	 * @param string $url  URL.
	 *
	 * @return mixed
	 */
	public function stub( $pre, $args, $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		$this->requests[] = array(
			'url'     => $url,
			'headers' => isset( $args['headers'] ) ? $args['headers'] : array(),
		);

		if ( in_array( $host, array( 'images.unsplash.com', 'images.pexels.com', 'pixabay.com', 'cdn.pixabay.com' ), true ) && 0 !== strpos( $path, '/api/' ) ) {
			if ( ! empty( $args['filename'] ) ) {
				file_put_contents( $args['filename'], $this->download ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}

			return $this->response( 200, '' );
		}

		$unsplash = array(
			'id'              => 'abc123',
			'width'           => 4000,
			'height'          => 3000,
			'color'           => '#264040',
			'alt_description' => 'a cup of <b>coffee</b> on a table',
			'urls'            => array(
				'raw'     => 'https://images.unsplash.com/photo-1?ixid=x',
				'full'    => 'https://images.unsplash.com/photo-1?ixid=x&q=85',
				'regular' => 'https://images.unsplash.com/photo-1?ixid=x&w=1080',
				'small'   => 'https://images.unsplash.com/photo-1?ixid=x&w=400',
			),
			'links'           => array(
				'html'              => 'https://unsplash.com/photos/abc123',
				'download_location' => 'https://api.unsplash.com/photos/abc123/download?ixid=x',
			),
			'user'            => array(
				'name'  => 'Ada Lovelace',
				'links' => array( 'html' => 'https://unsplash.com/@ada' ),
			),
		);

		if ( 'api.unsplash.com' === $host ) {
			if ( false !== strpos( $path, '/download' ) ) {
				return $this->response( 200, '{"url":"x"}' );
			}

			if ( 0 === strpos( $path, '/search/photos' ) ) {
				$premium = array_merge(
					$unsplash,
					array(
						'id'      => 'plus1',
						'premium' => true,
					)
				);

				return $this->response(
					200,
					wp_json_encode(
						array(
							'total'       => 2,
							'total_pages' => 1,
							'results'     => array( $unsplash, $premium ),
						)
					)
				);
			}

			return $this->response( 200, wp_json_encode( $unsplash ) );
		}

		if ( 'api.pexels.com' === $host ) {
			$photo = array(
				'id'               => 42,
				'width'            => 3000,
				'height'           => 2000,
				'url'              => 'https://www.pexels.com/photo/42/',
				'photographer'     => 'Grace Hopper',
				'photographer_url' => 'https://www.pexels.com/@grace',
				'avg_color'        => '#112233',
				'alt'              => 'Mountain lake',
				'src'              => array(
					'original' => 'https://images.pexels.com/photos/42/pexels-photo-42.jpeg',
					'large2x'  => 'https://images.pexels.com/photos/42/pexels-photo-42.jpeg?w=1880',
					'medium'   => 'https://images.pexels.com/photos/42/pexels-photo-42.jpeg?h=350',
				),
			);

			if ( 'unauthorized' === ( isset( $args['headers']['Authorization'] ) ? $args['headers']['Authorization'] : '' ) ) {
				return $this->response( 401, '{"error":"no"}' );
			}

			return $this->response(
				200,
				wp_json_encode(
					0 === strpos( $path, '/v1/search' ) ? array(
						'total_results' => 100,
						'photos'        => array( $photo ),
					) : $photo
				)
			);
		}

		if ( 'pixabay.com' === $host ) {
			return $this->response(
				200,
				wp_json_encode(
					array(
						'total'     => 9000,
						'totalHits' => 9000,
						'hits'      => array(
							array(
								'id'            => 7,
								'pageURL'       => 'https://pixabay.com/photos/7/',
								'tags'          => 'forest, trees, fog',
								'webformatURL'  => 'https://pixabay.com/get/7_640.jpg',
								'largeImageURL' => 'https://pixabay.com/get/7_1280.jpg',
								'imageWidth'    => 5000,
								'imageHeight'   => 3000,
								'user'          => 'Linus',
								'user_id'       => 99,
							),
						),
					)
				)
			);
		}

		return $pre;
	}

	/**
	 * A stub response.
	 *
	 * @param int    $code HTTP status.
	 * @param string $body Body.
	 *
	 * @return array
	 */
	private function response( $code, $body ) {
		return array(
			'headers'  => array(),
			'body'     => $body,
			'response' => array(
				'code'    => $code,
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * GET a route with query parameters.
	 *
	 * @param string $route  Route.
	 * @param array  $params Query parameters.
	 *
	 * @return WP_REST_Response
	 */
	private function get( $route, $params = array() ) {
		$request = new WP_REST_Request( 'GET', $route );
		$request->set_query_params( $params );

		return rest_do_request( $request );
	}

	public function test_providers_normalize_search_results() {
		$unsplash = Registry::get( 'unsplash' )->search( 'coffee', array( 'orientation' => 'square' ) );
		$this->assertCount( 1, $unsplash['photos'], 'Unsplash+ photos are left out.' );
		$this->assertSame( 'abc123', $unsplash['photos'][0]->id );
		$this->assertSame( 'a cup of coffee on a table', $unsplash['photos'][0]->description );
		$this->assertStringContainsString( 'orientation=squarish', $this->requests[0]['url'] );
		$this->assertSame( 'Client-ID unsplash-key', $this->requests[0]['headers']['Authorization'] );

		$pexels = Registry::get( 'pexels' )->search( 'lake', array( 'per_page' => 10 ) );
		$this->assertSame( '42', $pexels['photos'][0]->id );
		$this->assertSame( 10, $pexels['pages'] );
		$this->assertSame( 'pexels-key', end( $this->requests )['headers']['Authorization'] );

		$pixabay = Registry::get( 'pixabay' )->search( 'forest', array( 'orientation' => 'portrait' ) );
		$this->assertSame( 500, $pixabay['total'], 'Pixabay returns at most 500 results.' );
		$this->assertSame( 'https://pixabay.com/users/Linus-99/', $pixabay['photos'][0]->photographer_url );
		$this->assertStringContainsString( 'orientation=vertical', end( $this->requests )['url'] );
	}

	public function test_searches_are_cached_without_the_key_in_the_cache_key() {
		Registry::get( 'pixabay' )->search( 'forest' );
		Registry::get( 'pixabay' )->search( 'forest' );

		$this->assertCount( 1, $this->requests );
	}

	public function test_errors_are_reported() {
		$this->set_settings( array( 'pexels_key' => 'unauthorized' ) );
		Registry::reset();

		$this->assertSame( 'aimg_stock_auth', Registry::get( 'pexels' )->search( 'x' )->get_error_code() );

		$this->set_settings( array( 'pexels_key' => '' ) );
		Registry::reset();

		$response = $this->get( '/aimg/v1/stock/pexels/search', array( 'query' => 'x' ) );
		$this->assertSame( 'aimg_stock_no_key', $response->get_data()['code'] );
	}

	public function test_rest_search_hides_keys_and_adds_credits() {
		$response = $this->get( '/aimg/v1/stock/unsplash/search', array( 'query' => 'coffee' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'abc123', $data['photos'][0]['id'] );
		$this->assertStringContainsString( 'utm_source=image_generator', $data['photos'][0]['attribution'] );
		$this->assertStringNotContainsString( 'unsplash-key', wp_json_encode( $data ) );

		$list = $this->get( '/aimg/v1/stock' )->get_data();
		$this->assertSame( array( 'unsplash', 'pexels', 'pixabay' ), wp_list_pluck( $list, 'id' ) );
		$this->assertStringNotContainsString( 'key', implode( ',', array_keys( $list[0] ) ) . wp_json_encode( wp_list_pluck( $list, 'label' ) ) );
	}

	public function test_users_who_cannot_upload_cannot_search() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );

		$this->assertSame( 403, $this->get( '/aimg/v1/stock/unsplash/search', array( 'query' => 'x' ) )->get_status() );
	}

	public function test_import_credits_tracks_and_reuses() {
		$post_id  = self::factory()->post->create();
		$response = $this->rest(
			'POST',
			'/aimg/v1/stock/unsplash/import',
			array(
				'id'           => 'abc123',
				'post_id'      => $post_id,
				'set_featured' => true,
			)
		);
		$id       = $response->get_data()['id'];

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $id, (int) get_post_thumbnail_id( $post_id ) );
		$this->assertSame( 'a cup of coffee on a table', get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		$this->assertStringContainsString( 'Ada Lovelace', get_post_field( 'post_excerpt', $id ) );
		$this->assertSame( 'unsplash:abc123', get_post_meta( $id, Importer::KEY_META, true ) );
		$this->assertSame( 'unsplash', get_post_meta( $id, Generator::GENERATED_DATA_META, true )['source'] );

		$urls = wp_list_pluck( $this->requests, 'url' );
		$this->assertContains( 'https://api.unsplash.com/photos/abc123/download?ixid=x', $urls, 'The download is tracked.' );
		$this->assertContains( 'https://images.unsplash.com/photo-1?ixid=x&w=2400&q=85&fm=jpg&fit=max', $urls, 'The large size is downloaded by default.' );

		$this->assertSame( $id, Importer::import( 'unsplash', 'abc123' ), 'The same photo is imported once.' );
	}

	public function test_import_rejects_other_hosts_and_non_images() {
		$this->download = 'not an image';

		$this->assertSame( 'aimg_stock_bad_type', Importer::import( 'pixabay', '7' )->get_error_code() );
		$this->assertFalse( Importer::allowed_url( 'https://evil.example/x.jpg', array( 'pixabay.com' ) ) );
		$this->assertFalse( Importer::allowed_url( 'http://pixabay.com/x.jpg', array( 'pixabay.com' ) ) );
	}

	public function test_attribution_caption_can_be_turned_off() {
		$this->set_settings( array( 'stock_attribution' => 'no' ) );

		$id = Importer::import( 'pexels', '42' );

		$this->assertSame( '', get_post_field( 'post_excerpt', $id ) );
		$this->assertStringContainsString( 'Grace Hopper', get_post_meta( $id, Importer::DATA_META, true )['attribution'] );
	}

	public function test_keywords_from_titles() {
		$this->assertSame( 'coffee brewing mistakes', Keywords::from_text( '10 Coffee Brewing Mistakes (and How to Fix Them)' ) );
		$this->assertSame( 'café déjà', Keywords::from_text( 'Café & déjà: the best' ) );
		$this->assertSame( '', Keywords::from_text( 'How to do it' ) );
	}

	public function test_stock_method_imports_an_unused_photo_in_the_background() {
		$this->set_settings( array( 'generation_method' => Generator::METHOD_STOCK ) );
		$post_id = self::factory()->post->create( array( 'post_title' => 'Brewing better coffee' ) );

		$this->assertSame( Generator::METHOD_STOCK, Generator::get_runnable_method() );
		$this->assertTrue( Generator::method_runs_in_background( Generator::METHOD_STOCK ) );

		$id = Generator::generate_for_post( $post_id );

		$this->assertIsInt( $id );
		$this->assertSame( 'unsplash:abc123', get_post_meta( $id, Importer::KEY_META, true ) );
		$this->assertSame( $post_id, wp_get_post_parent_id( $id ) );
	}

	public function test_stock_methods_fall_back_without_keys() {
		update_option( 'aimg_settings', array( 'generation_method' => Generator::METHOD_STOCK_TEMPLATE ) );
		Registry::reset();

		$this->assertSame( Generator::METHOD_TEMPLATE, Generator::get_runnable_method() );
		$this->assertSame( '', Generator::get_runnable_method( Generator::METHOD_STOCK ) );
	}

	public function test_test_endpoint_checks_an_unsaved_key() {
		$response = $this->rest( 'POST', '/aimg/v1/stock/pexels/test', array( 'key' => 'unauthorized' ) );
		$this->assertSame( 'aimg_stock_auth', $response->get_data()['code'] );

		$response = $this->rest( 'POST', '/aimg/v1/stock/pexels/test' );
		$this->assertSame( 'Connected to Pexels.', $response->get_data()['message'] );
	}

	/**
	 * Requests made to the stock APIs.
	 *
	 * @return array[]
	 */
	private function api_requests() {
		return array_values(
			array_filter(
				$this->requests,
				function ( $request ) {
					return (bool) preg_match( '#^https://(api\.unsplash\.com|api\.pexels\.com|pixabay\.com/api)#', $request['url'] );
				}
			)
		);
	}

	/**
	 * A published template whose background is a stock photo.
	 *
	 * @return int Template ID.
	 */
	private function hybrid_template() {
		$id = $this->create_template();
		ArtificialImageGenerator\Templates\Repository::save_document( $id, ArtificialImageGenerator\Templates\Starters::all()['photo-headline'][1] );

		return $id;
	}

	public function test_remote_sources_keep_their_fields() {
		$document = ArtificialImageGenerator\Templates\Starters::all()['photo-headline'][1];

		$this->assertSame( 'stock', $document['layers'][0]['fill']['source'] );
		$this->assertSame( '{title}', $document['layers'][0]['fill']['query'] );
		$this->assertTrue( ArtificialImageGenerator\Rendering\Hybrid::document_is_remote( $document ) );
		$this->assertArrayNotHasKey( 'query', ArtificialImageGenerator\Rendering\Layers\Image::sanitize( array( 'source' => 'media' ), $document['canvas'] ) );
	}

	public function test_previews_never_call_remote_services() {
		$template = $this->hybrid_template();
		$post_id  = self::factory()->post->create( array( 'post_title' => 'Brewing better coffee' ) );

		$this->assertNotFalse( Generator::render( $template, 'Preview' ) );
		$this->assertNotFalse( Generator::render( $template, 'Preview', $post_id ) );
		$this->assertSame( array(), $this->api_requests() );
	}

	public function test_hybrid_templates_fetch_once_per_post_while_generating() {
		$template = $this->hybrid_template();
		$this->set_settings( array( 'default_template_id' => $template ) );
		$post_id = self::factory()->post->create( array( 'post_title' => 'Brewing better coffee' ) );

		$this->assertTrue( Generator::runs_in_background_for_post( Generator::METHOD_TEMPLATE, $post_id ) );

		$first = Generator::generate_template_for_post( $post_id );
		$this->assertIsInt( $first );
		$searches = count( $this->api_requests() );
		$this->assertGreaterThan( 0, $searches );

		$photo = get_post_meta( $post_id, ArtificialImageGenerator\Rendering\Hybrid::META, true );
		$this->assertSame( 'unsplash:abc123', get_post_meta( reset( $photo ), Importer::KEY_META, true ) );

		Generator::generate_template_for_post( $post_id );
		$this->assertCount( $searches, $this->api_requests(), 'Generating again reuses the photo.' );
	}

	public function test_hybrid_templates_fall_back_without_a_library() {
		update_option( 'aimg_settings', array() );
		Registry::reset();
		$template = $this->hybrid_template();
		$post_id  = self::factory()->post->create( array( 'post_title' => 'Brewing better coffee' ) );

		$this->assertIsInt( Generator::generate_template_for_post( $post_id ), 'The background color is drawn instead.' );
		$this->assertSame( '', get_post_meta( $post_id, ArtificialImageGenerator\Rendering\Hybrid::META, true ) );
	}

	public function test_saving_a_post_queues_a_hybrid_template() {
		$this->set_settings( array( 'default_template_id' => $this->hybrid_template() ) );

		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Brewing better coffee',
				'post_status' => 'publish',
			)
		);

		$this->assertSame( 'queued', ArtificialImageGenerator\Queue::get_status( $post_id )['status'] );
		$this->assertFalse( has_post_thumbnail( $post_id ) );
		$this->assertSame( array(), $this->api_requests(), 'Nothing is fetched while saving.' );
	}
}
