<?php

namespace ArtificialImageGenerator;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * The main plugin class.
 *
 * @since 1.0.0
 * @package ArtificialImageGenerator
 */
class Plugin {

	/**
	 * Plugin file path.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $file;

	/**
	 * Plugin version.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $version = '1.0.0';

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since 1.0.0
	 */
	public static $instance;

	/**
	 * Gets the single instance of the class.
	 * This method is used to create a new instance of the class.
	 *
	 * @param string $file The plugin file path.
	 * @param string $version The plugin version.
	 *
	 * @since 1.0.0
	 * @return static
	 */
	final public static function create( $file, $version = '1.0.0' ) {
		if ( null === self::$instance ) {
			self::$instance = new static( $file, $version );
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @param string $file The plugin file path.
	 * @param string $version The plugin version.
	 *
	 * @since 1.0.0
	 */
	protected function __construct( $file, $version ) {
		$this->file    = $file;
		$this->version = $version;
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Define plugin constants.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function define_constants() {
		// Define the plugin version.
		if ( ! defined( 'AIMG_VERSION' ) ) {
			define( 'AIMG_VERSION', $this->version );
		}

		// Define the plugin file.
		if ( ! defined( 'AIMG_FILE' ) ) {
			define( 'AIMG_FILE', $this->file );
		}

		// Define the plugin path.
		if ( ! defined( 'AIMG_PATH' ) ) {
			define( 'AIMG_PATH', plugin_dir_path( AIMG_FILE ) );
		}

		// Define the plugin URL.
		if ( ! defined( 'AIMG_URL' ) ) {
			define( 'AIMG_URL', plugin_dir_url( AIMG_FILE ) );
		}

		// Define the plugin assets path.
		if ( ! defined( 'AIMG_ASSETS_PATH' ) ) {
			define( 'AIMG_ASSETS_PATH', AIMG_PATH . 'assets/' );
		}

		// Define the plugin assets URL.
		if ( ! defined( 'AIMG_ASSETS_URL' ) ) {
			define( 'AIMG_ASSETS_URL', AIMG_URL . 'assets/' );
		}
	}

	/**
	 * Include the required files.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function includes() {
		require_once __DIR__ . '/functions.php';
	}

	/**
	 * Initialize the plugin hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_hooks() {
		register_activation_hook( AIMG_FILE, array( $this, 'activate' ) );
		add_action( 'admin_notices', array( $this, 'display_flash_notices' ), 12 );
		add_action( 'init', array( $this, 'init' ), 0 );
	}

	/**
	 * Activate the plugin.
	 * This method is called when the plugin is activated.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function activate() {
		update_option( 'aimg_version', AIMG_VERSION );
	}

	/**
	 * Add a flash notice.
	 *
	 * @param string  $notice Notice message.
	 * @param string  $type This can be "info", "warning", "error" or "success", "success" as default.
	 * @param boolean $dismissible Whether the notice is-dismissible or not.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function flash_notice( $notice = '', $type = 'success', $dismissible = true ) {
		$notices          = get_option( 'aimg_flash_notices', array() );
		$dismissible_text = ( $dismissible ) ? 'is-dismissible' : '';

		// Add new notice.
		$notices[] = array(
			'notice'      => wp_kses_post( $notice ),
			'type'        => sanitize_key( $type ),
			'dismissible' => $dismissible_text,
		);

		// Update the notices array.
		update_option( 'aimg_flash_notices', $notices );
	}

	/**
	 * Display flash notices after that, remove the option to prevent notices being displayed forever.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_flash_notices() {
		$notices = get_option( 'aimg_flash_notices', array() );

		foreach ( $notices as $notice ) {
			echo wp_kses_post(
				sprintf(
					'<div class="notice notice-%1$s %2$s"><p>%3$s</p></div>',
					esc_attr( $notice['type'] ),
					esc_attr( $notice['dismissible'] ),
					esc_html( $notice['notice'] ),
				)
			);
		}

		// Reset options to prevent notices being displayed forever.
		if ( ! empty( $notices ) ) {
			delete_option( 'aimg_flash_notices', array() );
		}
	}

	/**
	 * Initialize the plugin.
	 * This method is used to initialize the plugin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init() {
		// Load common classes.
		new PostTypes();
		new GenerateImages();
		new Admin\RestAPI();

		// Load the admin classes if it's an admin area.
		if ( is_admin() ) {
			new Admin\Admin();
			new Admin\Settings();
			new Admin\Actions();
			new Admin\Editor();
		}
	}
}
