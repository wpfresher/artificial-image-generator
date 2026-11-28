<?php

namespace ArtificialImageGenerator\Admin;

use ArtificialImageGenerator\Generator;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * The first-run setup screen: image source, keys, a design and a preview.
 *
 * A fresh install shows a notice that links to it until setup is finished or dismissed.
 * It is never forced on anyone and can be opened again from Settings.
 *
 * @since 1.9.1
 * @package ArtificialImageGenerator\Admin
 */
class Setup {

	/**
	 * Screen ID of the page.
	 *
	 * @var string
	 */
	const HOOK = 'admin_page_aimg-setup';

	/**
	 * Option set on a first activation while setup is not finished or dismissed.
	 *
	 * @var string
	 */
	const OPTION = 'aimg_setup_pending';

	/**
	 * Methods offered on the setup screen. Settings has the rest.
	 *
	 * @var string[]
	 */
	const METHODS = array( Generator::METHOD_TEMPLATE, Generator::METHOD_AI_TEMPLATE, Generator::METHOD_STOCK_TEMPLATE );

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'admin_post_aimg_dismiss_setup', array( $this, 'dismiss' ) );
		add_filter( 'admin_title', array( $this, 'admin_title' ), 10, 2 );
	}

	/**
	 * Give the menu-less page a browser title.
	 *
	 * @param string $admin_title Full title.
	 * @param string $title       Page title.
	 *
	 * @return string
	 */
	public function admin_title( $admin_title, $title ) {
		$screen = get_current_screen();

		if ( ! $screen || self::HOOK !== $screen->id || '' !== trim( (string) $title ) ) {
			return $admin_title;
		}

		return __( 'Set up Image Generator', 'artificial-image-generator' ) . $admin_title;
	}

	/**
	 * Mark a first activation.
	 *
	 * @param bool $fresh Whether this is the first activation on the site.
	 *
	 * @return void
	 */
	public static function activated( $fresh ) {
		if ( $fresh ) {
			add_option( self::OPTION, 1 );
		}
	}

	/**
	 * Page URL.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'admin.php?page=aimg-setup' );
	}

	/**
	 * Register the page without a menu item.
	 *
	 * @return void
	 */
	public function add_page() {
		add_submenu_page(
			'',
			__( 'Set up Image Generator', 'artificial-image-generator' ),
			__( 'Setup', 'artificial-image-generator' ),
			'manage_options',
			'aimg-setup',
			array( $this, 'render' )
		);
	}

	/**
	 * Point a fresh install to setup on the dashboard, the plugins screen and plugin pages.
	 *
	 * @return void
	 */
	public function notice() {
		$screen = get_current_screen();

		if ( ! get_option( self::OPTION ) || ! current_user_can( 'manage_options' ) || ! $screen || self::HOOK === $screen->id ) {
			return;
		}

		if ( ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) && ! Admin::current_page() ) {
			return;
		}

		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=aimg_dismiss_setup' ), 'aimg_dismiss_setup' );
		?>
		<div class="notice notice-info aimg-setup-notice">
			<p>
				<strong><?php esc_html_e( 'Image Generator is ready.', 'artificial-image-generator' ); ?></strong>
				<?php esc_html_e( 'New posts already get a featured image from a starter template. Take two minutes to pick where images come from and how they look.', 'artificial-image-generator' ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Start setup', 'artificial-image-generator' ); ?></a>
				<a class="button-link" href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'Dismiss', 'artificial-image-generator' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Dismiss the setup notice.
	 *
	 * @return void
	 */
	public function dismiss() {
		check_admin_referer( 'aimg_dismiss_setup' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'artificial-image-generator' ), 403 );
		}

		delete_option( self::OPTION );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * Register the REST route that finishes setup.
	 *
	 * @param string $namespace_name REST namespace.
	 *
	 * @return void
	 */
	public static function register_routes( $namespace_name ) {
		register_rest_route(
			$namespace_name,
			'/setup/done',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => function () {
					delete_option( self::OPTION );
					return rest_ensure_response( array( 'done' => true ) );
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Enqueue the app on the page.
	 *
	 * @param string $hook Page hook.
	 *
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( self::HOOK !== $hook || ! file_exists( AIMG_ASSETS_PATH . 'js/setup.asset.php' ) ) {
			return;
		}

		$asset = include AIMG_ASSETS_PATH . 'js/setup.asset.php';

		wp_enqueue_script( 'aimg-setup', AIMG_ASSETS_URL . 'js/setup.js', array_merge( $asset['dependencies'], array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-dom-ready' ) ), $asset['version'], true );
		wp_set_script_translations( 'aimg-setup', 'artificial-image-generator', AIMG_PATH . 'languages' );
		wp_enqueue_style( 'aimg-setup', AIMG_ASSETS_URL . 'css/setup.css', array( 'wp-components' ), $asset['version'] );
		wp_enqueue_style( 'aimg-admin', AIMG_ASSETS_URL . 'css/admin.css', array(), AIMG_VERSION );

		wp_add_inline_script( 'aimg-setup', 'window.aimgSetup = ' . wp_json_encode( self::data() ) . ';', 'before' );
	}

	/**
	 * Data for the app.
	 *
	 * @return array
	 */
	public static function data() {
		$values  = Settings::values();
		$schema  = Settings::schema();
		$methods = Generator::get_methods();
		$keys    = array();

		foreach ( $schema['fields'] as $field ) {
			if ( 'secret' === $field['type'] ) {
				$keys[ $field['id'] ] = array(
					'label'     => $field['label'],
					'service'   => $field['service'],
					'signupUrl' => $field['signupUrl'],
					'test'      => $field['test'],
				);
			}
		}

		$templates = array();
		foreach ( (array) aimg_get_templates( array( 'post_status' => 'publish' ) ) as $template ) {
			if ( $template ) {
				$templates[] = array(
					'id'      => (string) $template->ID,
					'title'   => aimg_plain_text( $template->post_title ),
					'preview' => (string) get_post_meta( $template->ID, '_aimg_preview_image_url', true ),
				);
			}
		}

		$latest = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
			)
		);

		$stock = array();
		foreach ( \ArtificialImageGenerator\Stock\Registry::all() as $provider ) {
			$stock[] = array(
				'id'    => $provider->get_id(),
				'label' => $provider->get_label(),
			);
		}

		return array(
			'methods'     => array_values(
				array_map(
					function ( $id ) use ( $methods ) {
						return array(
							'id'    => $id,
							'label' => $methods[ $id ],
						);
					},
					array_filter(
						self::METHODS,
						function ( $id ) use ( $methods ) {
							return isset( $methods[ $id ] );
						}
					)
				)
			),
			'values'      => array(
				'generation_method'   => in_array( $values['generation_method'], self::METHODS, true ) ? $values['generation_method'] : Generator::METHOD_TEMPLATE,
				'default_template_id' => (string) $values['default_template_id'],
				'stock_provider'      => (string) $values['stock_provider'],
				'auto_post_types'     => $values['auto_post_types'],
			),
			'postTypes'   => Generator::get_post_types(),
			'templates'   => $templates,
			'keys'        => $keys,
			'secrets'     => Settings::secrets(),
			'stock'       => $stock,
			'sampleTitle' => $latest ? aimg_get_plain_title( $latest[0]->ID ) : __( 'How to brew the perfect cup of coffee', 'artificial-image-generator' ),
			'urls'        => array(
				'settings'    => admin_url( 'admin.php?page=aimg-settings' ),
				'templates'   => admin_url( 'admin.php?page=image-generator' ),
				'newTemplate' => admin_url( 'admin.php?page=image-generator&add' ),
				'bulk'        => admin_url( 'admin.php?page=aimg-bulk' ),
			),
		);
	}

	/**
	 * Render the app root.
	 *
	 * @return void
	 */
	public function render() {
		?>
		<div class="wrap aimg-page">
			<div id="aimg-setup">
				<h1><?php esc_html_e( 'Set up Image Generator', 'artificial-image-generator' ); ?></h1>
				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'Loading… If this message does not go away, make sure JavaScript is enabled and reload the page.', 'artificial-image-generator' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}
}
