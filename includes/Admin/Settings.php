<?php

namespace ArtificialImageGenerator\Admin;

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

		// Generate Thumbnails for Posts.
		add_settings_field(
			'aimg_is_post_thumbnail',
			__( 'Enable Post Thumbnails', 'artificial-image-generator' ),
			array( $this, 'is_post_thumbnail' ),
			'aimg-settings',
			'aimg_general_settings'
		);

		// Generate Thumbnails for Pages.
		add_settings_field(
			'aimg_is_page_thumbnail',
			__( 'Enable Page Thumbnails', 'artificial-image-generator' ),
			array( $this, 'is_page_thumbnail' ),
			'aimg-settings',
			'aimg_general_settings'
		);

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
	}

	/**
	 * Display AI service settings section description.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ai_service_settings() {
		echo '<p>' . esc_html__( 'Configure the AI image generation service used by the block editor. The API key is used when generating images from a custom prompt.', 'artificial-image-generator' ) . '</p>';
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
		$masked      = $api_key ? str_repeat( '•', max( 0, strlen( $api_key ) - 4 ) ) . substr( $api_key, -4 ) : '';
		?>
		<input
			type="password"
			name="aimg_settings[api_key]"
			id="aimg_settings_api_key"
			value="<?php echo esc_attr( $is_constant ? '' : $api_key ); ?>"
			class="regular-text"
			autocomplete="off"
			placeholder="<?php echo $is_constant ? esc_attr( $masked ) : 'sk-...'; ?>"
			<?php disabled( $is_constant ); ?>
		/>
		<p class="description">
			<?php
			if ( $is_constant ) {
				esc_html_e( 'Your API key is currently defined via the AIMG_API_KEY PHP constant and cannot be edited here.', 'artificial-image-generator' );
			} else {
				esc_html_e( 'Enter your image generation API key (e.g. an OpenAI key for DALL·E). For maximum security you can instead define the AIMG_API_KEY constant in wp-config.php.', 'artificial-image-generator' );
			}
			?>
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
		<p class="description"><?php esc_html_e( 'Check this box to enable automatic generation of post thumbnails when a post is saved. This will create a thumbnail image based on the post title, using the random background colors and overlay images if configured.', 'artificial-image-generator' ); ?></p>
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
		<p class="description"><?php esc_html_e( 'Check this box to enable automatic generation of page thumbnails when a page is saved. This will create a thumbnail image based on the page title, using the random background colors and overlay images if configured.', 'artificial-image-generator' ); ?></p>
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

		// Sanitize the is post thumbnail setting.
		$sanitized_settings['is_post_thumbnail'] = isset( $settings['is_post_thumbnail'] ) ? 'yes' : 'no';

		// Sanitize the is page thumbnail setting.
		$sanitized_settings['is_page_thumbnail'] = isset( $settings['is_page_thumbnail'] ) ? 'yes' : 'no';

		// Sanitize the API key. If the constant is defined, never persist a value here.
		if ( defined( 'AIMG_API_KEY' ) && AIMG_API_KEY ) {
			$sanitized_settings['api_key'] = '';
		} else {
			$sanitized_settings['api_key'] = isset( $settings['api_key'] ) ? trim( sanitize_text_field( $settings['api_key'] ) ) : '';
		}

		return $sanitized_settings;
	}
}
