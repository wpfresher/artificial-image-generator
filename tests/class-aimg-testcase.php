<?php
/**
 * Base test case.
 *
 * @package ArtificialImageGenerator
 */

/**
 * Shared helpers for the plugin tests.
 */
abstract class AIMG_TestCase extends WP_UnitTestCase {

	/**
	 * A 1×1 transparent PNG.
	 */
	const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected $admin_id;

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
		delete_option( 'aimg_settings' );
	}

	/**
	 * Create a published template.
	 *
	 * @param array $meta Template meta, without the `_aimg_` prefix.
	 *
	 * @return int Template ID.
	 */
	protected function create_template( $meta = array() ) {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'aimg_template',
				'post_title'  => 'Test template',
				'post_status' => 'publish',
			)
		);

		$meta = wp_parse_args(
			$meta,
			array(
				'bg_colors'       => '#224466',
				'width'           => 400,
				'height'          => 200,
				'title_font_size' => 24,
			)
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, '_aimg_' . $key, $value );
		}

		return $id;
	}

	/**
	 * Create an image attachment backed by a real PNG file.
	 *
	 * @param string $name File name.
	 *
	 * @return int Attachment ID.
	 */
	protected function create_png_attachment( $name = 'aimg-test.png' ) {
		$upload = wp_upload_bits( $name, null, base64_decode( self::PNG ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		return self::factory()->attachment->create_object(
			$upload['file'],
			0,
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => $name,
			)
		);
	}

	/**
	 * Request bodies sent to OpenAI by `stub_openai()`.
	 *
	 * @var array
	 */
	protected $openai_bodies = array();

	/**
	 * Answer OpenAI requests with tiny images, or with an error status.
	 *
	 * @param int $status HTTP status to answer with.
	 */
	protected function stub_openai( $status = 200 ) {
		$this->openai_bodies = array();

		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $status ) {
				if ( false === strpos( $url, 'api.openai.com' ) ) {
					return $pre;
				}

				$body                  = json_decode( $args['body'], true );
				$this->openai_bodies[] = $body;
				$data                  = 200 === $status
					? array( 'data' => array_fill( 0, (int) $body['n'], array( 'b64_json' => self::PNG ) ) )
					: array( 'error' => array( 'message' => 'Service unavailable' ) );

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( $data ),
					'response' => array(
						'code'    => $status,
						'message' => '',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);
	}

	/**
	 * Merge values into the plugin settings.
	 *
	 * @param array $settings Settings.
	 */
	protected function set_settings( $settings ) {
		update_option( 'aimg_settings', array_merge( (array) get_option( 'aimg_settings', array() ), $settings ) );
	}

	/**
	 * Run a request through the REST server.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route.
	 * @param array  $params Body parameters.
	 *
	 * @return WP_REST_Response
	 */
	protected function rest( $method, $route, $params = array() ) {
		$request = new WP_REST_Request( $method, $route );
		$request->set_body_params( $params );

		return rest_do_request( $request );
	}

	/**
	 * Submit the template form handler and return the redirect location.
	 *
	 * @param array $post Submitted fields.
	 *
	 * @return string Redirect location.
	 */
	protected function submit_template_form( $post ) {
		$_POST    = array_merge( array( '_wpnonce' => wp_create_nonce( 'aimg_update_template' ) ), $post );
		$_REQUEST = $_POST;

		$_SERVER['HTTP_REFERER'] = admin_url( 'admin.php?page=image-generator&add=1' );

		$location = '';
		$stop     = function ( $url ) use ( &$location ) {
			$location = $url;
			throw new Exception( 'redirect' );
		};

		add_filter( 'wp_redirect', $stop );

		try {
			ArtificialImageGenerator\Admin\Actions::update_template();
		} catch ( Exception $e ) {
			unset( $e );
		}

		remove_filter( 'wp_redirect', $stop );

		return $location;
	}
}
