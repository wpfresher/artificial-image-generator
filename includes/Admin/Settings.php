<?php

namespace ArtificialImageGenerator\Admin;

use ArtificialImageGenerator\Generator;
use ArtificialImageGenerator\PromptBuilder;
use ArtificialImageGenerator\Providers\OpenAI;
use ArtificialImageGenerator\Stock\Registry as StockRegistry;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Class Settings
 *
 * The settings page (a React app fed by `SettingsController`) and the option sanitizer.
 *
 * @since 1.0.0
 * @package ArtificialImageGenerator/Admin
 */
class Settings {

	/**
	 * Admin page hook of the settings screen.
	 *
	 * @var string
	 */
	const HOOK = 'image-generator_page_aimg-settings';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue the settings app.
	 *
	 * @param string $hook Admin page hook.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( self::HOOK !== $hook || ! file_exists( AIMG_ASSETS_PATH . 'js/settings.asset.php' ) ) {
			return;
		}

		$asset = include AIMG_ASSETS_PATH . 'js/settings.asset.php';

		wp_enqueue_script( 'aimg-settings', AIMG_ASSETS_URL . 'js/settings.js', array_merge( $asset['dependencies'], array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-hooks', 'wp-url', 'wp-dom-ready' ) ), $asset['version'], true );
		wp_set_script_translations( 'aimg-settings', 'artificial-image-generator', AIMG_PATH . 'languages' );
		wp_enqueue_style( 'aimg-settings', AIMG_ASSETS_URL . 'css/settings.css', array( 'wp-components' ), $asset['version'] );
		wp_enqueue_style( 'aimg-admin', AIMG_ASSETS_URL . 'css/admin.css', array(), AIMG_VERSION );

		wp_add_inline_script(
			'aimg-settings',
			'window.aimgSettings = ' . wp_json_encode(
				array(
					'proActive' => defined( 'AIMG_PRO_VERSION' ),
					'links'     => Admin::links( 'sidebar' ),
				)
			) . ';',
			'before'
		);

		/**
		 * Fires after the settings app is enqueued, to enqueue scripts that use its JS filters
		 * (`aimg.settings.tab`). Make them depend on `aimg-settings`.
		 *
		 * @since 1.9.0
		 */
		do_action( 'aimg_enqueue_settings' );
	}

	/**
	 * Add the settings page under the plugin menu.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_settings_page() {
		add_submenu_page(
			'image-generator',
			__( 'Settings', 'artificial-image-generator' ),
			__( 'Settings', 'artificial-image-generator' ),
			'manage_options',
			'aimg-settings',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Render the settings app root.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function settings_page() {
		?>
		<div class="wrap aimg-page">
			<div id="aimg-settings">
				<h1><?php esc_html_e( 'Settings', 'artificial-image-generator' ); ?></h1>
				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'Loading the settings… If this message does not go away, make sure JavaScript is enabled and reload the page.', 'artificial-image-generator' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Register the option with its sanitizer, so saves from wp-admin are sanitized too.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings() {
		register_setting( 'aimg', 'aimg_settings', array( __CLASS__, 'sanitize_settings' ) );
	}

	/**
	 * Tabs and fields of the settings app.
	 *
	 * Field types: toggle, select, text, textarea, number, color and secret (an API key that is
	 * never sent to the browser). `show_if` ({ setting: [ values ] }) shows a field only while
	 * every listed setting has one of the values.
	 *
	 * @since 1.9.0
	 * @return array { tabs: array[], fields: array[] }
	 */
	public static function schema() {
		$template_methods = array( Generator::METHOD_TEMPLATE, Generator::METHOD_TEMPLATE_AI, Generator::METHOD_AI_TEMPLATE, Generator::METHOD_STOCK_TEMPLATE );
		$ai_methods       = array( Generator::METHOD_AI, Generator::METHOD_TEMPLATE_AI, Generator::METHOD_AI_TEMPLATE );
		$templates        = array( 0 => __( 'A random template', 'artificial-image-generator' ) );

		foreach ( (array) aimg_get_templates( array( 'post_status' => 'publish' ) ) as $template ) {
			if ( $template ) {
				$templates[ $template->ID ] = aimg_plain_text( $template->post_title );
			}
		}

		$stock_libraries = array( '' => __( 'The first library with a key', 'artificial-image-generator' ) );
		foreach ( StockRegistry::all() as $provider ) {
			$stock_libraries[ $provider->get_id() ] = $provider->get_label();
		}

		$tabs = array(
			array(
				'id'    => 'general',
				'title' => __( 'General', 'artificial-image-generator' ),
			),
			array(
				'id'    => 'automatic',
				'title' => __( 'Automatic Images', 'artificial-image-generator' ),
			),
			array(
				'id'    => 'ai',
				'title' => __( 'AI Service', 'artificial-image-generator' ),
			),
			array(
				'id'    => 'stock',
				'title' => __( 'Stock Photos', 'artificial-image-generator' ),
			),
		);

		$fields = array(
			array(
				'id'          => 'default_bg_color',
				'tab'         => 'general',
				'type'        => 'color',
				'label'       => __( 'Default background color', 'artificial-image-generator' ),
				'placeholder' => '#008000',
				'help'        => __( 'Background color for new templates. Templates keep the colors they were saved with; change those in the Template Studio.', 'artificial-image-generator' ),
			),
			array(
				'id'          => 'default_text_color',
				'tab'         => 'general',
				'type'        => 'color',
				'label'       => __( 'Default text color', 'artificial-image-generator' ),
				'placeholder' => '#ffffff',
				'help'        => __( 'Title color for new templates. Templates keep the colors they were saved with; change those in the Template Studio.', 'artificial-image-generator' ),
			),
			array(
				'id'    => 'remove_data',
				'tab'   => 'general',
				'type'  => 'toggle',
				'label' => __( 'Delete templates and settings when the plugin is deleted', 'artificial-image-generator' ),
				'help'  => __( 'Leave this off to keep your image templates if you delete and later reinstall the plugin. Images already added to the Media Library are never removed.', 'artificial-image-generator' ),
			),
			array(
				'id'    => 'is_post_thumbnail',
				'tab'   => 'automatic',
				'type'  => 'toggle',
				'label' => __( 'Generate featured images for posts', 'artificial-image-generator' ),
				'help'  => __( 'When a post is saved without a featured image, one is generated for it.', 'artificial-image-generator' ),
			),
			array(
				'id'    => 'is_page_thumbnail',
				'tab'   => 'automatic',
				'type'  => 'toggle',
				'label' => __( 'Generate featured images for pages', 'artificial-image-generator' ),
				'help'  => __( 'When a page is saved without a featured image, one is generated for it.', 'artificial-image-generator' ),
			),
			array(
				'id'      => 'generation_method',
				'tab'     => 'automatic',
				'type'    => 'select',
				'label'   => __( 'Generate with', 'artificial-image-generator' ),
				'options' => Generator::get_methods(),
				'help'    => __( 'AI images are created in the background once a post is published or scheduled, and use your API key. Templates are free and instant.', 'artificial-image-generator' ),
			),
			array(
				'id'      => 'default_template_id',
				'tab'     => 'automatic',
				'type'    => 'select',
				'label'   => __( 'Image template', 'artificial-image-generator' ),
				'options' => $templates,
				'show_if' => array( 'generation_method' => $template_methods ),
			),
			array(
				'id'      => 'auto_ai_size',
				'tab'     => 'automatic',
				'type'    => 'select',
				'label'   => __( 'AI image shape', 'artificial-image-generator' ),
				'options' => self::get_sizes(),
				'show_if' => array( 'generation_method' => $ai_methods ),
			),
			array(
				'id'          => 'ai_prompt_template',
				'tab'         => 'automatic',
				'type'        => 'textarea',
				'label'       => __( 'AI prompt', 'artificial-image-generator' ),
				'placeholder' => PromptBuilder::get_default_template(),
				'help'        => sprintf(
					/* translators: %s: list of merge tags */
					__( 'Leave empty to use the default shown. Available tags: %s', 'artificial-image-generator' ),
					'{title} {excerpt} {category} {tags} {site_name} {custom_field:key}'
				),
				'show_if'     => array( 'generation_method' => $ai_methods ),
			),
			array(
				'id'      => 'ai_style',
				'tab'     => 'automatic',
				'type'    => 'select',
				'label'   => __( 'AI style', 'artificial-image-generator' ),
				'options' => wp_list_pluck( PromptBuilder::get_styles(), 0 ),
				'help'    => __( 'Added to the prompt of automatic AI images.', 'artificial-image-generator' ),
				'show_if' => array( 'generation_method' => $ai_methods ),
			),
			array(
				'id'      => 'ai_negative_prompt',
				'tab'     => 'automatic',
				'type'    => 'textarea',
				'label'   => __( 'AI instructions', 'artificial-image-generator' ),
				'help'    => __( 'Added to the end of every automatic AI prompt. AI models often draw garbled text, so the default asks for none.', 'artificial-image-generator' ),
				'show_if' => array( 'generation_method' => $ai_methods ),
			),
			array(
				'id'      => 'stock_provider',
				'tab'     => 'automatic',
				'type'    => 'select',
				'label'   => __( 'Stock photo library', 'artificial-image-generator' ),
				'options' => $stock_libraries,
				'help'    => __( 'The photo is found with keywords from the post title. Add API keys under Stock Photos.', 'artificial-image-generator' ),
				'show_if' => array( 'generation_method' => array( Generator::METHOD_STOCK, Generator::METHOD_STOCK_TEMPLATE ) ),
			),
			self::secret_field( 'api_key', 'ai', __( 'OpenAI API key', 'artificial-image-generator' ), 'AIMG_API_KEY', 'https://platform.openai.com/api-keys', 'OpenAI', 'sk-...' ),
			array(
				'id'      => 'api_model',
				'tab'     => 'ai',
				'type'    => 'select',
				'label'   => __( 'Image model', 'artificial-image-generator' ),
				'options' => self::get_models(),
				'help'    => __( 'GPT Image 2.5 Flare is fast and suited to everyday images; Sunburst is OpenAI\'s most capable model. Retired models (GPT Image 1 and DALL·E) are switched to GPT Image 2.5 Flare automatically.', 'artificial-image-generator' ),
			),
			array(
				'id'      => 'ai_size',
				'tab'     => 'ai',
				'type'    => 'select',
				'label'   => __( 'Default image shape', 'artificial-image-generator' ),
				'options' => self::get_sizes(),
				'help'    => __( 'Used for prompts in the editor and the Media Library; it can be changed per image there.', 'artificial-image-generator' ),
			),
			array(
				'id'      => 'ai_quality',
				'tab'     => 'ai',
				'type'    => 'select',
				'label'   => __( 'Image quality', 'artificial-image-generator' ),
				'options' => self::get_qualities(),
				'help'    => __( 'Higher quality costs more. Extra high and Maximum need GPT Image 2.5; GPT Image 2.6 uses High instead.', 'artificial-image-generator' ),
			),
			array(
				'id'      => 'ai_access',
				'tab'     => 'ai',
				'type'    => 'select',
				'label'   => __( 'Who can generate AI images', 'artificial-image-generator' ),
				'options' => array(
					'authors' => __( 'Authors and above', 'artificial-image-generator' ),
					'editors' => __( 'Editors and above', 'artificial-image-generator' ),
					'admins'  => __( 'Administrators only', 'artificial-image-generator' ),
				),
				'help'    => __( 'AI images are paid for with your API key. Templates stay available to everyone who can upload files.', 'artificial-image-generator' ),
			),
			array(
				'id'    => 'ai_hourly_limit',
				'tab'   => 'ai',
				'type'  => 'number',
				'min'   => 0,
				'label' => __( 'Hourly limit per user', 'artificial-image-generator' ),
				'help'  => __( 'Maximum AI images each user can generate per hour. Set to 0 for no limit.', 'artificial-image-generator' ),
			),
		);

		$stock = StockRegistry::all();
		$stock = array_merge( array_intersect_key( array_flip( array( 'pixabay', 'pexels', 'unsplash' ) ), $stock ), $stock );

		foreach ( $stock as $provider ) {
			$fields[] = self::secret_field(
				$provider->get_id() . '_key',
				'stock',
				/* translators: %s: provider name, e.g. Unsplash */
				sprintf( __( '%s API key', 'artificial-image-generator' ), $provider->get_label() ),
				'AIMG_' . strtoupper( $provider->get_id() ) . '_KEY',
				$provider->get_signup_url(),
				$provider->get_label(),
				'',
				$provider->get_id()
			);
		}

		$fields = array_merge(
			$fields,
			array(
				array(
					'id'      => 'stock_orientation',
					'tab'     => 'stock',
					'type'    => 'select',
					'label'   => __( 'Photo orientation', 'artificial-image-generator' ),
					'options' => StockRegistry::orientations(),
					'help'    => __( 'Used for automatic featured images.', 'artificial-image-generator' ),
				),
				array(
					'id'      => 'stock_size',
					'tab'     => 'stock',
					'type'    => 'select',
					'label'   => __( 'Import size', 'artificial-image-generator' ),
					'options' => StockRegistry::sizes(),
				),
				array(
					'id'    => 'stock_attribution',
					'tab'   => 'stock',
					'type'  => 'toggle',
					'label' => __( 'Add the photographer credit to the image caption', 'artificial-image-generator' ),
					'help'  => __( 'Unsplash, Pexels and Pixabay ask you to credit photographers where you can. The credit is always saved with the image.', 'artificial-image-generator' ),
				),
			)
		);

		foreach ( $fields as &$field ) {
			if ( isset( $field['options'] ) ) {
				$field['options'] = self::options( $field['options'] );
			}
		}
		unset( $field );

		$schema = array(
			'tabs'   => $tabs,
			'fields' => $fields,
		);

		/**
		 * Filter the tabs and fields of the settings app. Keep values an added field saves
		 * with the `aimg_sanitize_settings` filter.
		 *
		 * @param array $schema { tabs: array[], fields: array[] }.
		 *
		 * @since 1.9.0
		 */
		return (array) apply_filters( 'aimg_settings_schema', $schema );
	}

	/**
	 * Current settings in the shape the settings app edits. API keys are never included.
	 *
	 * @since 1.9.0
	 * @return array
	 */
	public static function values() {
		$saved    = (array) get_option( 'aimg_settings', array() );
		$defaults = array(
			'default_bg_color'    => '',
			'default_text_color'  => '',
			'remove_data'         => 'no',
			'is_post_thumbnail'   => 'yes',
			'is_page_thumbnail'   => 'no',
			'generation_method'   => Generator::METHOD_TEMPLATE,
			'default_template_id' => 0,
			'auto_ai_size'        => 'landscape',
			'ai_prompt_template'  => '',
			'ai_style'            => 'photo',
			'ai_negative_prompt'  => PromptBuilder::get_default_negative(),
			'api_model'           => ( new OpenAI() )->get_default_model(),
			'ai_size'             => 'square',
			'ai_quality'          => 'auto',
			'ai_access'           => 'authors',
			'ai_hourly_limit'     => 20,
			'stock_provider'      => '',
			'stock_orientation'   => 'landscape',
			'stock_size'          => 'large',
			'stock_attribution'   => 'yes',
		);
		$values   = array_merge( $defaults, $saved );

		foreach ( self::schema()['fields'] as $field ) {
			$id = $field['id'];

			if ( 'secret' === $field['type'] ) {
				$values[ $id ] = '';
			} elseif ( 'toggle' === $field['type'] ) {
				$values[ $id ] = 'yes' === aimg_sanitize_checkbox( isset( $values[ $id ] ) ? $values[ $id ] : '' );
			} elseif ( 'select' === $field['type'] ) {
				$values[ $id ] = isset( $values[ $id ] ) ? (string) $values[ $id ] : '';
			}
		}

		return $values;
	}

	/**
	 * Save settings sent by the settings app.
	 *
	 * Empty API key fields keep the saved key; `remove_{id}` clears it. Settings the app
	 * does not show are kept.
	 *
	 * @param array $values Values from the settings app.
	 *
	 * @since 1.9.0
	 * @return array The saved settings.
	 */
	public static function save( $values ) {
		$input = array_merge( (array) get_option( 'aimg_settings', array() ), (array) $values );

		foreach ( self::secret_ids() as $id ) {
			$input[ $id . '_form' ] = '1';
			$input[ $id ]           = isset( $values[ $id ] ) ? (string) $values[ $id ] : '';
		}

		foreach ( self::schema()['fields'] as $field ) {
			if ( 'toggle' === $field['type'] ) {
				$input[ $field['id'] ] = empty( $input[ $field['id'] ] ) || 'no' === $input[ $field['id'] ] ? 'no' : 'yes';
			}
		}

		$clean = self::sanitize_settings( $input );
		update_option( 'aimg_settings', $clean );

		return $clean;
	}

	/**
	 * State of each API key field: saved or not, its last characters, defined as a constant.
	 *
	 * @since 1.9.0
	 * @return array
	 */
	public static function secrets() {
		$secrets = array();

		foreach ( self::schema()['fields'] as $field ) {
			if ( 'secret' !== $field['type'] ) {
				continue;
			}

			$constant    = $field['constant'];
			$is_constant = $constant && defined( $constant ) && constant( $constant );
			$value       = $is_constant ? (string) constant( $constant ) : (string) aimg_get_settings( $field['id'], '' );

			$secrets[ $field['id'] ] = array(
				'saved'    => '' !== $value,
				'masked'   => '' !== $value ? str_repeat( '•', 8 ) . substr( $value, -4 ) : '',
				'constant' => (bool) $is_constant,
			);
		}

		return $secrets;
	}

	/**
	 * IDs of the API key fields.
	 *
	 * @since 1.9.0
	 * @return string[]
	 */
	private static function secret_ids() {
		return wp_list_pluck(
			array_filter(
				self::schema()['fields'],
				function ( $field ) {
					return 'secret' === $field['type'];
				}
			),
			'id'
		);
	}

	/**
	 * An API key field.
	 *
	 * @param string $id          Setting key.
	 * @param string $tab         Tab ID.
	 * @param string $label       Label.
	 * @param string $constant    PHP constant that can hold the key instead.
	 * @param string $signup_url  Where to get a key.
	 * @param string $service     Service name.
	 * @param string $placeholder Placeholder while no key is saved.
	 * @param string $test        Stock library to test the key against, if any.
	 *
	 * @return array
	 */
	private static function secret_field( $id, $tab, $label, $constant, $signup_url, $service, $placeholder = '', $test = '' ) {
		return array(
			'id'          => $id,
			'tab'         => $tab,
			'type'        => 'secret',
			'label'       => $label,
			'constant'    => $constant,
			'signupUrl'   => esc_url_raw( $signup_url ),
			'service'     => $service,
			'placeholder' => $placeholder,
			'test'        => $test,
		);
	}

	/**
	 * Options as a list of { value, label }.
	 *
	 * @param array $options Options as value => label.
	 *
	 * @return array[]
	 */
	private static function options( $options ) {
		$list = array();

		foreach ( $options as $value => $label ) {
			$list[] = array(
				'value' => (string) $value,
				'label' => (string) $label,
			);
		}

		return $list;
	}

	/**
	 * Supported image generation models.
	 *
	 * @since 1.4.3
	 * @since 1.6.0 Lists the OpenAI provider's models.
	 * @return array
	 */
	public static function get_models() {
		return ( new OpenAI() )->get_models();
	}

	/**
	 * Image shapes, as key => label.
	 *
	 * @since 1.6.0
	 * @return array
	 */
	public static function get_sizes() {
		return array(
			'square'    => __( 'Square', 'artificial-image-generator' ),
			'landscape' => __( 'Landscape', 'artificial-image-generator' ),
			'portrait'  => __( 'Portrait', 'artificial-image-generator' ),
		);
	}

	/**
	 * Image qualities, as key => label.
	 *
	 * @since 1.6.0
	 * @return array
	 */
	public static function get_qualities() {
		return array(
			'auto'   => __( 'Automatic', 'artificial-image-generator' ),
			'low'    => __( 'Low (cheapest)', 'artificial-image-generator' ),
			'medium' => __( 'Medium', 'artificial-image-generator' ),
			'high'   => __( 'High', 'artificial-image-generator' ),
			'xhigh'  => __( 'Extra high', 'artificial-image-generator' ),
			'max'    => __( 'Maximum (most expensive)', 'artificial-image-generator' ),
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $settings Settings to sanitize.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public static function sanitize_settings( $settings ) {
		$sanitized_settings = array();

		$sanitized_settings['default_bg_color']   = isset( $settings['default_bg_color'] ) ? sanitize_text_field( $settings['default_bg_color'] ) : '';
		$sanitized_settings['default_text_color'] = isset( $settings['default_text_color'] ) ? sanitize_text_field( $settings['default_text_color'] ) : '';

		// The value decides, not merely its presence, so saving the option back programmatically cannot flip a 'no' to 'yes'.
		$sanitized_settings['is_post_thumbnail'] = aimg_sanitize_checkbox( isset( $settings['is_post_thumbnail'] ) ? $settings['is_post_thumbnail'] : '' );
		$sanitized_settings['is_page_thumbnail'] = aimg_sanitize_checkbox( isset( $settings['is_page_thumbnail'] ) ? $settings['is_page_thumbnail'] : '' );
		$sanitized_settings['remove_data']       = aimg_sanitize_checkbox( isset( $settings['remove_data'] ) ? $settings['remove_data'] : '' );

		// If the constant is defined, never persist a key here.
		if ( defined( 'AIMG_API_KEY' ) && AIMG_API_KEY ) {
			$sanitized_settings['api_key'] = '';
		} else {
			$sanitized_settings['api_key'] = isset( $settings['api_key'] ) ? trim( sanitize_text_field( $settings['api_key'] ) ) : '';

			// The form never shows the saved key, so an empty field there means "keep it".
			if ( ! empty( $settings['remove_api_key'] ) ) {
				$sanitized_settings['api_key'] = '';
			} elseif ( ! empty( $settings['api_key_form'] ) && '' === $sanitized_settings['api_key'] ) {
				$sanitized_settings['api_key'] = (string) aimg_get_settings( 'api_key', '' );
			}
		}

		$model                           = isset( $settings['api_model'] ) ? sanitize_text_field( $settings['api_model'] ) : '';
		$sanitized_settings['api_model'] = array_key_exists( $model, self::get_models() ) ? $model : ( new OpenAI() )->get_default_model();

		$access                          = isset( $settings['ai_access'] ) ? sanitize_key( $settings['ai_access'] ) : '';
		$sanitized_settings['ai_access'] = array_key_exists( $access, aimg_get_ai_access_levels() ) ? $access : 'authors';

		$sanitized_settings['ai_hourly_limit'] = isset( $settings['ai_hourly_limit'] ) && '' !== $settings['ai_hourly_limit'] ? absint( $settings['ai_hourly_limit'] ) : 20;

		$choices = array(
			'generation_method' => array( array_keys( Generator::get_methods() ), Generator::METHOD_TEMPLATE ),
			'auto_ai_size'      => array( array_keys( self::get_sizes() ), 'landscape' ),
			'ai_size'           => array( array_keys( self::get_sizes() ), 'square' ),
			'ai_quality'        => array( array_keys( self::get_qualities() ), 'auto' ),
			'ai_style'          => array( array_keys( PromptBuilder::get_styles() ), 'photo' ),
		);

		foreach ( $choices as $key => $choice ) {
			$value                      = isset( $settings[ $key ] ) ? sanitize_key( $settings[ $key ] ) : '';
			$sanitized_settings[ $key ] = in_array( $value, $choice[0], true ) ? $value : $choice[1];
		}

		$template_id                               = isset( $settings['default_template_id'] ) ? absint( $settings['default_template_id'] ) : 0;
		$sanitized_settings['default_template_id'] = $template_id && aimg_get_template( $template_id ) ? $template_id : 0;

		$sanitized_settings['ai_prompt_template'] = isset( $settings['ai_prompt_template'] ) ? sanitize_textarea_field( $settings['ai_prompt_template'] ) : '';
		$sanitized_settings['ai_negative_prompt'] = isset( $settings['ai_negative_prompt'] ) ? sanitize_textarea_field( $settings['ai_negative_prompt'] ) : PromptBuilder::get_default_negative();

		foreach ( array_keys( StockRegistry::all() ) as $id ) {
			$key = $id . '_key';

			if ( defined( 'AIMG_' . strtoupper( $key ) ) && constant( 'AIMG_' . strtoupper( $key ) ) ) {
				$sanitized_settings[ $key ] = '';
				continue;
			}

			$sanitized_settings[ $key ] = isset( $settings[ $key ] ) ? trim( sanitize_text_field( $settings[ $key ] ) ) : '';

			if ( ! empty( $settings[ 'remove_' . $key ] ) ) {
				$sanitized_settings[ $key ] = '';
			} elseif ( '' === $sanitized_settings[ $key ] && ( ! empty( $settings[ $key . '_form' ] ) || ! isset( $settings[ $key ] ) ) ) {
				$sanitized_settings[ $key ] = (string) aimg_get_settings( $key, '' );
			}
		}

		$stock_choices = array(
			'stock_provider'    => array( array_merge( array( '' ), array_keys( StockRegistry::all() ) ), '' ),
			'stock_orientation' => array( array_keys( StockRegistry::orientations() ), 'landscape' ),
			'stock_size'        => array( array_keys( StockRegistry::sizes() ), 'large' ),
		);

		foreach ( $stock_choices as $key => $choice ) {
			$value                      = isset( $settings[ $key ] ) ? sanitize_key( $settings[ $key ] ) : '';
			$sanitized_settings[ $key ] = in_array( $value, $choice[0], true ) ? $value : $choice[1];
		}

		// On by default: only a full settings save (which always sends stock_size) can turn it off by leaving it out.
		if ( isset( $settings['stock_attribution'] ) ) {
			$sanitized_settings['stock_attribution'] = aimg_sanitize_checkbox( $settings['stock_attribution'] );
		} else {
			$sanitized_settings['stock_attribution'] = isset( $settings['stock_size'] ) ? 'no' : 'yes';
		}

		/**
		 * Filter the sanitized plugin settings, e.g. to keep settings an add-on adds to the form.
		 *
		 * @param array $sanitized_settings Sanitized settings.
		 * @param array $settings           Submitted settings.
		 *
		 * @since 1.8.0
		 */
		return (array) apply_filters( 'aimg_sanitize_settings', $sanitized_settings, (array) $settings );
	}
}
