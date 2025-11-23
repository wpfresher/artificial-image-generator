<?php

namespace AutoImageGenerator\Admin;

/**
 * Class Settings
 *
 * This class handles the settings for the Auto Image Generator plugin.
 *
 * @since 1.0.0
 * @package AutoImageGenerator/Admin
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
			'auto-image-generator',
			__( 'Settings', 'auto-image-generator' ),
			__( 'Settings', 'auto-image-generator' ),
			'manage_options',
			'auto-image-generator-settings',
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
			aimg_auto_image_generator()->flash_notice( __( 'You do not have sufficient permissions to access this page.', 'auto-image-generator' ), 'error' );
			return;
		}
		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'Settings', 'auto-image-generator' ); ?>
				<abbr title="<?php esc_attr_e( 'Auto Image Generator', 'auto-image-generator' ); ?>" class="dashicons dashicons-format-image"></abbr>
			</h1>
			<p><?php esc_html_e( 'Configure the settings for the Auto Image Generator plugin.', 'auto-image-generator' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php
				settings_fields( 'auto_image_generator' );
				do_settings_sections( 'auto-image-generator' );
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
		register_setting( 'auto_image_generator', 'aimg_settings', array( $this, 'sanitize_settings' ) );

		// Add settings section.
		add_settings_section(
			'aimg_general_settings',
			__( 'General Settings', 'auto-image-generator' ),
			array( $this, 'general_settings' ),
			'auto-image-generator'
		);

		// Fallback default bg color for thumbnails.
		add_settings_field(
			'aimg_default_bg_color',
			__( 'Default Background Color', 'auto-image-generator' ),
			array( $this, 'default_bg_color' ),
			'auto-image-generator',
			'aimg_general_settings'
		);

		// Fallback default text color for thumbnails.
		add_settings_field(
			'aimg_default_text_color',
			__( 'Default Text Color', 'auto-image-generator' ),
			array( $this, 'default_text_color' ),
			'auto-image-generator',
			'aimg_general_settings'
		);

		// Generate Thumbnails for Posts.
		add_settings_field(
			'aimg_is_post_thumbnail',
			__( 'Enable Post Thumbnails', 'auto-image-generator' ),
			array( $this, 'is_post_thumbnail' ),
			'auto-image-generator',
			'aimg_general_settings'
		);

		// Generate Thumbnails for Pages.
		add_settings_field(
			'aimg_is_page_thumbnail',
			__( 'Enable Page Thumbnails', 'auto-image-generator' ),
			array( $this, 'is_page_thumbnail' ),
			'auto-image-generator',
			'aimg_general_settings'
		);
	}

	/**
	 * Display general settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function general_settings() {
		echo '<p>' . esc_html__( 'Configure the Auto Image Generator general settings.', 'auto-image-generator' ) . '</p>';
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
		<input type="text" name="aimg_settings[default_bg_color]" id="aimg_settings[default_bg_color]" value="<?php echo esc_attr( $default_bg_color ); ?>" class="regular-text" placeholder="<?php esc_attr_e( '#008000', 'auto-image-generator' ); ?>" />
		<p class="description"><?php esc_html_e( 'Enter the default background color for the thumbnails. This will be used as a fallback color if no specific color is set.', 'auto-image-generator' ); ?></p>
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
		<input type="text" name="aimg_settings[default_text_color]" id="aimg_settings[default_text_color]" value="<?php echo esc_attr( $default_text_color ); ?>" class="regular-text" placeholder="<?php esc_attr_e( '#ffffff', 'auto-image-generator' ); ?>" />
		<p class="description"><?php esc_html_e( 'Enter the default text color for the thumbnails. This will be used as a fallback color if no specific color is set.', 'image-generator' ); ?></p>
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
			<?php esc_html_e( 'Enable Post Thumbnails', 'image-generator' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Check this box to enable automatic generation of post thumbnails when a post is saved. This will create a thumbnail image based on the post title, using the random background colors and overlay images if configured.', 'image-generator' ); ?></p>
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
			<?php esc_html_e( 'Enable Page Thumbnails', 'image-generator' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Check this box to enable automatic generation of page thumbnails when a page is saved. This will create a thumbnail image based on the page title, using the random background colors and overlay images if configured.', 'image-generator' ); ?></p>
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

		return $sanitized_settings;
	}
}
