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
 * This class handles the settings for the Image Generator plugin.
 *
 * @since 1.0.0
 * @package ArtificialImageGenerator/Admin
 */
class Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Create admin settings page under the primary menu.
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );

		// Register settings.
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue the settings page script.
	 *
	 * @param string $hook Admin page hook.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'image-generator_page_aimg-settings' !== $hook || ! file_exists( AIMG_ASSETS_PATH . 'js/settings.asset.php' ) ) {
			return;
		}

		$asset = include AIMG_ASSETS_PATH . 'js/settings.asset.php';

		wp_enqueue_script( 'aimg-settings', AIMG_ASSETS_URL . 'js/settings.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'aimg-settings', 'artificial-image-generator', AIMG_PATH . 'languages' );
		wp_enqueue_style( 'aimg-admin', AIMG_ASSETS_URL . 'css/admin.css', array(), AIMG_VERSION );
	}

	/**
	 * Add settings page under WordPress settings menu.
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
	 * Render settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function settings_page() {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			artificial_image_generator()->flash_notice( __( 'You do not have sufficient permissions to access this page.', 'artificial-image-generator' ), 'error' );
			return;
		}
		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'Settings', 'artificial-image-generator' ); ?>
				<abbr title="<?php esc_attr_e( 'Image Generator', 'artificial-image-generator' ); ?>" class="dashicons dashicons-format-image"></abbr>
			</h1>
			<p><?php esc_html_e( 'Configure the settings for the Image Generator plugin.', 'artificial-image-generator' ); ?></p>
			<?php settings_errors(); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php
				settings_fields( 'aimg' );
				do_settings_sections( 'aimg-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings() {
		register_setting( 'aimg', 'aimg_settings', array( $this, 'sanitize_settings' ) );

		// Add settings section.
		add_settings_section(
			'aimg_general_settings',
			__( 'General Settings', 'artificial-image-generator' ),
			array( $this, 'general_settings' ),
			'aimg-settings'
		);

		// Fallback default bg color for thumbnails.
		add_settings_field(
			'aimg_default_bg_color',
			__( 'Default Background Color', 'artificial-image-generator' ),
			array( $this, 'default_bg_color' ),
			'aimg-settings',
			'aimg_general_settings'
		);

		// Fallback default text color for thumbnails.
		add_settings_field(
			'aimg_default_text_color',
			__( 'Default Text Color', 'artificial-image-generator' ),
			array( $this, 'default_text_color' ),
			'aimg-settings',
			'aimg_general_settings'
		);

		// Remove plugin data when the plugin is deleted.
		add_settings_field(
			'aimg_remove_data',
			__( 'Remove Data on Uninstall', 'artificial-image-generator' ),
			array( $this, 'remove_data_field' ),
			'aimg-settings',
			'aimg_general_settings'
		);

		add_settings_section(
			'aimg_auto_settings',
			__( 'Automatic Featured Images', 'artificial-image-generator' ),
			array( $this, 'auto_settings' ),
			'aimg-settings'
		);

		// Generate Thumbnails for Posts.
		add_settings_field(
			'aimg_is_post_thumbnail',
			__( 'Enable Post Thumbnails', 'artificial-image-generator' ),
			array( $this, 'is_post_thumbnail' ),
			'aimg-settings',
			'aimg_auto_settings'
		);

		// Generate Thumbnails for Pages.
		add_settings_field(
			'aimg_is_page_thumbnail',
			__( 'Enable Page Thumbnails', 'artificial-image-generator' ),
			array( $this, 'is_page_thumbnail' ),
			'aimg-settings',
			'aimg_auto_settings'
		);

		$auto_fields = array(
			'generation_method'   => __( 'Generate With', 'artificial-image-generator' ),
			'default_template_id' => __( 'Image Template', 'artificial-image-generator' ),
			'auto_ai_size'        => __( 'AI Image Shape', 'artificial-image-generator' ),
			'ai_prompt_template'  => __( 'AI Prompt', 'artificial-image-generator' ),
			'ai_style'            => __( 'AI Style', 'artificial-image-generator' ),
			'ai_negative_prompt'  => __( 'AI Instructions', 'artificial-image-generator' ),
		);

		foreach ( $auto_fields as $key => $label ) {
			add_settings_field( 'aimg_' . $key, $label, array( $this, $key . '_field' ), 'aimg-settings', 'aimg_auto_settings' );
		}

		// AI service section.
		add_settings_section(
			'aimg_ai_service_settings',
			__( 'AI Service', 'artificial-image-generator' ),
			array( $this, 'ai_service_settings' ),
			'aimg-settings'
		);

		// API key field for AI service.
		add_settings_field(
			'aimg_api_key',
			__( 'API Key', 'artificial-image-generator' ),
			array( $this, 'api_key_field' ),
			'aimg-settings',
			'aimg_ai_service_settings'
		);

		// Model selection field for AI service.
		add_settings_field(
			'aimg_api_model',
			__( 'Image Model', 'artificial-image-generator' ),
			array( $this, 'api_model_field' ),
			'aimg-settings',
			'aimg_ai_service_settings'
		);

		add_settings_field(
			'aimg_ai_size',
			__( 'Default Image Shape', 'artificial-image-generator' ),
			array( $this, 'ai_size_field' ),
			'aimg-settings',
			'aimg_ai_service_settings'
		);

		add_settings_field(
			'aimg_ai_quality',
			__( 'Image Quality', 'artificial-image-generator' ),
			array( $this, 'ai_quality_field' ),
			'aimg-settings',
			'aimg_ai_service_settings'
		);

		add_settings_field(
			'aimg_ai_access',
			__( 'Who Can Generate AI Images', 'artificial-image-generator' ),
			array( $this, 'ai_access_field' ),
			'aimg-settings',
			'aimg_ai_service_settings'
		);

		add_settings_field(
			'aimg_ai_hourly_limit',
			__( 'Hourly Limit per User', 'artificial-image-generator' ),
			array( $this, 'ai_hourly_limit_field' ),
			'aimg-settings',
			'aimg_ai_service_settings'
		);

		add_settings_section(
			'aimg_stock_settings',
			__( 'Stock Photos', 'artificial-image-generator' ),
			array( $this, 'stock_settings' ),
			'aimg-settings'
		);

		foreach ( StockRegistry::all() as $provider ) {
			add_settings_field(
				'aimg_' . $provider->get_id() . '_key',
				/* translators: %s: provider name, e.g. Unsplash */
				sprintf( __( '%s API Key', 'artificial-image-generator' ), $provider->get_label() ),
				array( $this, 'stock_key_field' ),
				'aimg-settings',
				'aimg_stock_settings',
				array( 'provider' => $provider )
			);
		}

		$stock_fields = array(
			'stock_provider'    => __( 'Library for Automatic Images', 'artificial-image-generator' ),
			'stock_orientation' => __( 'Photo Orientation', 'artificial-image-generator' ),
			'stock_size'        => __( 'Import Size', 'artificial-image-generator' ),
			'stock_attribution' => __( 'Credit', 'artificial-image-generator' ),
		);

		foreach ( $stock_fields as $key => $label ) {
			add_settings_field( 'aimg_' . $key, $label, array( $this, $key . '_field' ), 'aimg-settings', 'aimg_stock_settings' );
		}
	}

	/**
	 * Stock photos section description.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function stock_settings() {
		echo '<p>' . esc_html__( 'Search free photos from Unsplash, Pexels and Pixabay in the editor and the Media Library, or use them for automatic featured images. Each library needs its own free API key.', 'artificial-image-generator' ) . '</p>';
	}

	/**
	 * API key field of a stock photo library.
	 *
	 * @param array $args Field arguments, with `provider`.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function stock_key_field( $args ) {
		$provider    = $args['provider'];
		$id          = $provider->get_id();
		$constant    = 'AIMG_' . strtoupper( $id ) . '_KEY';
		$is_constant = defined( $constant ) && constant( $constant );
		$key         = $provider instanceof \ArtificialImageGenerator\Stock\Provider ? $provider->get_key() : '';
		$masked      = $key ? str_repeat( '•', 8 ) . substr( $key, -4 ) : '';
		?>
		<input type="hidden" name="aimg_settings[<?php echo esc_attr( $id ); ?>_key_form]" value="1" />
		<input
			type="password"
			name="aimg_settings[<?php echo esc_attr( $id ); ?>_key]"
			id="aimg_settings_<?php echo esc_attr( $id ); ?>_key"
			value=""
			class="regular-text"
			autocomplete="new-password"
			placeholder="<?php echo esc_attr( $masked ); ?>"
			<?php disabled( $is_constant ); ?>
		/>
		<button type="button" class="button aimg-test-stock" data-provider="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Test Connection', 'artificial-image-generator' ); ?></button>
		<span class="aimg-test-stock-result" aria-live="polite"></span>
		<?php if ( ! $is_constant && $key ) : ?>
			<p>
				<label>
					<input type="checkbox" name="aimg_settings[remove_<?php echo esc_attr( $id ); ?>_key]" value="1" />
					<?php esc_html_e( 'Remove the saved API key', 'artificial-image-generator' ); ?>
				</label>
			</p>
		<?php endif; ?>
		<p class="description">
			<?php
			if ( $is_constant ) {
				/* translators: %s: PHP constant name */
				echo wp_kses_post( sprintf( esc_html__( 'Defined with the %s constant in wp-config.php.', 'artificial-image-generator' ), '<code>' . esc_html( $constant ) . '</code>' ) );
			} elseif ( $key ) {
				esc_html_e( 'A key is saved. Leave the field empty to keep it, or enter a new key to replace it.', 'artificial-image-generator' );
			} else {
				echo wp_kses_post(
					sprintf(
					/* translators: 1: link to the provider's API page, 2: PHP constant name */
					esc_html__( 'Get a free key at %1$s, or define the %2$s constant in wp-config.php.', 'artificial-image-generator' ),
					'<a href="' . esc_url( $provider->get_signup_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $provider->get_label() ) . '</a>',
					'<code>' . esc_html( $constant ) . '</code>'
					)
				);
			}
			?>
		</p>
		<?php
	}

	/**
	 * Library used for automatic featured images.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function stock_provider_field() {
		$options = array( '' => __( 'The first library with a key', 'artificial-image-generator' ) );

		foreach ( StockRegistry::all() as $provider ) {
			$options[ $provider->get_id() ] = $provider->get_label();
		}

		$this->select_field( 'stock_provider', $options, '', __( 'Used when "Generate With" is a stock photo. The photo is found with keywords from the post title.', 'artificial-image-generator' ) );
	}

	/**
	 * Orientation of automatic stock photos.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function stock_orientation_field() {
		$this->select_field( 'stock_orientation', StockRegistry::orientations(), 'landscape' );
	}

	/**
	 * Size stock photos are imported at.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function stock_size_field() {
		$this->select_field( 'stock_size', StockRegistry::sizes(), 'large' );
	}

	/**
	 * Whether imported photos get a credit caption.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function stock_attribution_field() {
		?>
		<label>
			<input type="checkbox" name="aimg_settings[stock_attribution]" value="1" <?php checked( aimg_get_settings( 'stock_attribution', 'yes' ), 'yes' ); ?> />
			<?php esc_html_e( 'Add the photographer credit to the image caption', 'artificial-image-generator' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Unsplash, Pexels and Pixabay ask you to credit photographers where you can. The credit is always saved with the image.', 'artificial-image-generator' ); ?></p>
		<?php
	}

	/**
	 * Render the AI access field.
	 *
	 * @since 1.5.4
	 * @return void
	 */
	public function ai_access_field() {
		$access  = aimg_get_settings( 'ai_access', 'authors' );
		$options = array(
			'authors' => __( 'Authors and above', 'artificial-image-generator' ),
			'editors' => __( 'Editors and above', 'artificial-image-generator' ),
			'admins'  => __( 'Administrators only', 'artificial-image-generator' ),
		);
		?>
		<select name="aimg_settings[ai_access]" id="aimg_settings_ai_access">
			<?php foreach ( $options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $access, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'AI images are paid for with your API key. Templates stay available to everyone who can upload files.', 'artificial-image-generator' ); ?></p>
		<?php
	}

	/**
	 * Render the AI hourly limit field.
	 *
	 * @since 1.5.4
	 * @return void
	 */
	public function ai_hourly_limit_field() {
		$limit = absint( aimg_get_settings( 'ai_hourly_limit', 20 ) );
		?>
		<input type="number" min="0" step="1" class="small-text" name="aimg_settings[ai_hourly_limit]" id="aimg_settings_ai_hourly_limit" value="<?php echo esc_attr( $limit ); ?>" />
		<p class="description"><?php esc_html_e( 'Maximum AI images each user can generate per hour. Set to 0 for no limit.', 'artificial-image-generator' ); ?></p>
		<?php
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
	 * Display AI service settings section description.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ai_service_settings() {
		echo '<p>' . esc_html__( 'The AI service used for prompts in the editor and the Media Library, and for automatic AI featured images.', 'artificial-image-generator' ) . '</p>';
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
	 * Print a select field.
	 *
	 * @param string $key         Setting key.
	 * @param array  $options     Options as value => label.
	 * @param string $default_value Default value.
	 * @param string $description Description.
	 *
	 * @return void
	 */
	private function select_field( $key, $options, $default_value, $description = '' ) {
		$current = (string) aimg_get_settings( $key, $default_value );
		?>
		<select name="aimg_settings[<?php echo esc_attr( $key ); ?>]" id="aimg_settings_<?php echo esc_attr( $key ); ?>">
			<?php foreach ( $options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, (string) $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php if ( $description ) : ?>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Automatic featured images section description.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function auto_settings() {
		echo '<p>' . esc_html__( 'When a post or page is saved without a featured image, one is generated for it.', 'artificial-image-generator' ) . '</p>';
	}

	/**
	 * Generation method field.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function generation_method_field() {
		$this->select_field(
			'generation_method',
			Generator::get_methods(),
			Generator::METHOD_TEMPLATE,
			__( 'AI images are created in the background once a post is published or scheduled, and use your API key. Templates are free and instant.', 'artificial-image-generator' )
		);
	}

	/**
	 * Default template field.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function default_template_id_field() {
		$options = array( 0 => __( 'A random template', 'artificial-image-generator' ) );

		foreach ( (array) aimg_get_templates( array( 'post_status' => 'publish' ) ) as $template ) {
			if ( $template ) {
				$options[ $template->ID ] = $template->post_title;
			}
		}

		$this->select_field( 'default_template_id', $options, '0' );
	}

	/**
	 * Automatic AI image shape field.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function auto_ai_size_field() {
		$this->select_field( 'auto_ai_size', self::get_sizes(), 'landscape' );
	}

	/**
	 * Prompt template field.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function ai_prompt_template_field() {
		$template = (string) aimg_get_settings( 'ai_prompt_template', '' );
		?>
		<textarea name="aimg_settings[ai_prompt_template]" id="aimg_settings_ai_prompt_template" rows="3" class="large-text" placeholder="<?php echo esc_attr( PromptBuilder::get_default_template() ); ?>"><?php echo esc_textarea( $template ); ?></textarea>
		<p class="description">
			<?php
			printf(
				/* translators: %s: list of merge tags */
				esc_html__( 'Leave empty to use the default shown. Available tags: %s', 'artificial-image-generator' ),
				'<code>{title}</code> <code>{excerpt}</code> <code>{category}</code> <code>{tags}</code> <code>{site_name}</code> <code>{custom_field:key}</code>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Style preset field.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function ai_style_field() {
		$this->select_field( 'ai_style', wp_list_pluck( PromptBuilder::get_styles(), 0 ), 'photo', __( 'Added to the prompt of automatic AI images.', 'artificial-image-generator' ) );
	}

	/**
	 * Negative instructions field.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function ai_negative_prompt_field() {
		$value = (string) aimg_get_settings( 'ai_negative_prompt', PromptBuilder::get_default_negative() );
		?>
		<textarea name="aimg_settings[ai_negative_prompt]" id="aimg_settings_ai_negative_prompt" rows="2" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Added to the end of every automatic AI prompt. AI models often draw garbled text, so the default asks for none.', 'artificial-image-generator' ); ?></p>
		<?php
	}

	/**
	 * Default AI image shape field.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function ai_size_field() {
		$this->select_field( 'ai_size', self::get_sizes(), 'square', __( 'Used for prompts in the editor and the Media Library; it can be changed per image there.', 'artificial-image-generator' ) );
	}

	/**
	 * AI image quality field.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function ai_quality_field() {
		$this->select_field( 'ai_quality', self::get_qualities(), 'auto', __( 'Higher quality costs more. Extra high and Maximum need GPT Image 2.5; GPT Image 2 uses High instead.', 'artificial-image-generator' ) );
	}

	/**
	 * Render API key field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function api_key_field() {
		$is_constant = defined( 'AIMG_API_KEY' ) && AIMG_API_KEY;
		$api_key     = $is_constant ? AIMG_API_KEY : aimg_get_settings( 'api_key', '' );
		$masked      = $api_key ? str_repeat( '•', 8 ) . substr( $api_key, -4 ) : '';
		?>
		<input type="hidden" name="aimg_settings[api_key_form]" value="1" />
		<input
			type="password"
			name="aimg_settings[api_key]"
			id="aimg_settings_api_key"
			value=""
			class="regular-text"
			autocomplete="new-password"
			placeholder="<?php echo $masked ? esc_attr( $masked ) : 'sk-...'; ?>"
			<?php disabled( $is_constant ); ?>
		/>
		<?php if ( ! $is_constant && $api_key ) : ?>
			<p>
				<label for="aimg_settings_remove_api_key">
					<input type="checkbox" name="aimg_settings[remove_api_key]" id="aimg_settings_remove_api_key" value="1" />
					<?php esc_html_e( 'Remove the saved API key', 'artificial-image-generator' ); ?>
				</label>
			</p>
		<?php endif; ?>
		<p class="description">
			<?php
			if ( $is_constant ) {
				esc_html_e( 'Your API key is currently defined via the AIMG_API_KEY PHP constant and cannot be edited here.', 'artificial-image-generator' );
			} elseif ( $api_key ) {
				esc_html_e( 'An API key is saved. Leave the field empty to keep it, or enter a new key to replace it.', 'artificial-image-generator' );
			} else {
				esc_html_e( 'Enter your image generation API key (an OpenAI API key). For maximum security you can instead define the AIMG_API_KEY constant in wp-config.php.', 'artificial-image-generator' );
			}
			?>
		</p>
		<?php
	}

	/**
	 * Render model selection field.
	 *
	 * @since 1.4.3
	 * @return void
	 */
	public function api_model_field() {
		$model = aimg_get_settings( 'api_model', ( new OpenAI() )->get_default_model() );
		?>
		<select name="aimg_settings[api_model]" id="aimg_settings_api_model">
			<?php foreach ( self::get_models() as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $model, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'The OpenAI model used for AI images. GPT Image 2.5 Flare is fast and suited to everyday images; Sunburst is OpenAI\'s most capable model. Retired models (GPT Image 1 and DALL·E) are switched to GPT Image 2.5 Flare automatically.', 'artificial-image-generator' ); ?>
		</p>
		<?php
	}

	/**
	 * Display general settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function general_settings() {
		echo '<p>' . esc_html__( 'Configure the Image Generator general settings.', 'artificial-image-generator' ) . '</p>';
	}

	/**
	 * Display default background color field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function default_bg_color() {
		$default_bg_color = aimg_get_settings( 'default_bg_color' );
		?>
		<input type="text" name="aimg_settings[default_bg_color]" id="aimg_settings[default_bg_color]" value="<?php echo esc_attr( $default_bg_color ); ?>" class="regular-text" placeholder="<?php esc_attr_e( '#008000', 'artificial-image-generator' ); ?>" />
		<p class="description"><?php esc_html_e( 'Enter the default background color for the thumbnails. This will be used as a fallback color if no specific color is set.', 'artificial-image-generator' ); ?></p>
		<?php
	}

	/**
	 * Display default text color field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function default_text_color() {
		$default_text_color = aimg_get_settings( 'default_text_color' );
		?>
		<input type="text" name="aimg_settings[default_text_color]" id="aimg_settings[default_text_color]" value="<?php echo esc_attr( $default_text_color ); ?>" class="regular-text" placeholder="<?php esc_attr_e( '#ffffff', 'artificial-image-generator' ); ?>" />
		<p class="description"><?php esc_html_e( 'Enter the default text color for the thumbnails. This will be used as a fallback color if no specific color is set.', 'artificial-image-generator' ); ?></p>
		<?php
	}

	/**
	 * Display is post thumbnail field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function is_post_thumbnail() {
		$is_post_thumbnail = aimg_get_settings( 'is_post_thumbnail', 'yes' );
		?>
		<label for="aimg_settings[is_post_thumbnail]">
			<input type="checkbox" name="aimg_settings[is_post_thumbnail]" id="aimg_settings[is_post_thumbnail]" value="1" <?php checked( $is_post_thumbnail, 'yes' ); ?> />
			<?php esc_html_e( 'Enable Post Thumbnails', 'artificial-image-generator' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Generate a featured image when a post is saved without one, using the method chosen below.', 'artificial-image-generator' ); ?></p>
		<?php
	}

	/**
	 * Display is page thumbnail field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function is_page_thumbnail() {
		$is_page_thumbnail = aimg_get_settings( 'is_page_thumbnail' );
		?>
		<label for="aimg_settings[is_page_thumbnail]">
			<input type="checkbox" name="aimg_settings[is_page_thumbnail]" id="aimg_settings[is_page_thumbnail]" value="1" <?php checked( $is_page_thumbnail, 'yes' ); ?> />
			<?php esc_html_e( 'Enable Page Thumbnails', 'artificial-image-generator' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Generate a featured image when a page is saved without one, using the method chosen below.', 'artificial-image-generator' ); ?></p>
		<?php
	}

	/**
	 * Display the remove-data-on-uninstall field.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public function remove_data_field() {
		$remove_data = aimg_get_settings( 'remove_data', 'no' );
		?>
		<label for="aimg_settings[remove_data]">
			<input type="checkbox" name="aimg_settings[remove_data]" id="aimg_settings[remove_data]" value="1" <?php checked( $remove_data, 'yes' ); ?> />
			<?php esc_html_e( 'Delete templates and settings when the plugin is deleted', 'artificial-image-generator' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Leave this unchecked to keep your image templates if you delete and later reinstall the plugin. Images already added to the Media Library are never removed.', 'artificial-image-generator' ); ?></p>
		<?php
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $settings Settings to sanitize.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function sanitize_settings( $settings ) {
		$sanitized_settings = array();

		// Sanitize the default background color.
		$sanitized_settings['default_bg_color'] = isset( $settings['default_bg_color'] ) ? sanitize_text_field( $settings['default_bg_color'] ) : '';

		// Sanitize the default text color.
		$sanitized_settings['default_text_color'] = isset( $settings['default_text_color'] ) ? sanitize_text_field( $settings['default_text_color'] ) : '';

		// Sanitize the checkbox settings. The value decides, not merely its presence,
		// so saving the option back programmatically cannot flip a 'no' to 'yes'.
		$sanitized_settings['is_post_thumbnail'] = aimg_sanitize_checkbox( isset( $settings['is_post_thumbnail'] ) ? $settings['is_post_thumbnail'] : '' );
		$sanitized_settings['is_page_thumbnail'] = aimg_sanitize_checkbox( isset( $settings['is_page_thumbnail'] ) ? $settings['is_page_thumbnail'] : '' );
		$sanitized_settings['remove_data']       = aimg_sanitize_checkbox( isset( $settings['remove_data'] ) ? $settings['remove_data'] : '' );

		// Sanitize the API key. If the constant is defined, never persist a value here.
		if ( defined( 'AIMG_API_KEY' ) && AIMG_API_KEY ) {
			$sanitized_settings['api_key'] = '';
		} else {
			$sanitized_settings['api_key'] = isset( $settings['api_key'] ) ? trim( sanitize_text_field( $settings['api_key'] ) ) : '';

			// The form never prints the saved key, so an empty field there means "keep it".
			if ( ! empty( $settings['remove_api_key'] ) ) {
				$sanitized_settings['api_key'] = '';
			} elseif ( ! empty( $settings['api_key_form'] ) && '' === $sanitized_settings['api_key'] ) {
				$sanitized_settings['api_key'] = (string) aimg_get_settings( 'api_key', '' );
			}
		}

		// Sanitize the model; fall back to the default when the value is unknown.
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

		// On by default: only the settings form (which always sends stock_size) can turn it off by leaving it out.
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
