<?php
/**
 * AI Image Generator Admin Page
 *
 * This file renders the admin page for the AI Image Generator plugin.
 *
 * @package ArtificialImageGenerator
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.
?>
<div class="wrap">
	<h1>
		<?php esc_html_e( 'AI Image Generator', 'artificial-image-generator' ); ?>
		<abbr title="<?php esc_attr_e( 'AI Image Generator', 'artificial-image-generator' ); ?>" class="dashicons dashicons-format-image"></abbr>
	</h1>
	<p><?php esc_html_e( 'Configure to generate thumbnails.', 'artificial-image-generator' ); ?></p>
	<form id="aimg-form" method="POST" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="bg_colors"><?php esc_html_e( 'Background Colors', 'artificial-image-generator' ); ?> <span class="required">*</span></label>
				</th>
				<td>
					<input type="text" id="bg_colors" name="bg_colors" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. #e74c3c, #2ecc71, #9b59b6', 'artificial-image-generator' ); ?>" value="<?php echo esc_attr( get_option( 'aimg_bg_colors', '#e74c3c,#2ecc71,#9b59b6' ) ); ?>" required />
					<p class="description"><?php esc_html_e( 'Enter the background colors for the thumbnails. Use comma to separate multiple colors.', 'artificial-image-generator' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="width"><?php esc_html_e( 'Width', 'artificial-image-generator' ); ?> <span class="required">*</span></label>
				</th>
				<td>
					<input type="number" id="width" name="width" class="regular-text" placeholder="<?php esc_attr_e( '1200', 'artificial-image-generator' ); ?>" value="<?php echo esc_attr( get_option( 'aimg_width', 1200 ) ); ?>" min="1" required />
					<p class="description"><?php esc_html_e( 'Enter the width for the thumbnails in pixels.', 'artificial-image-generator' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="height"><?php esc_html_e( 'Height', 'artificial-image-generator' ); ?> <span class="required">*</span></label>
				</th>
				<td>
					<input type="number" id="height" name="height" class="regular-text" placeholder="<?php esc_attr_e( '800', 'artificial-image-generator' ); ?>" value="<?php echo esc_attr( get_option( 'aimg_height', 800 ) ); ?>" min="1" required />
					<p class="description"><?php esc_html_e( 'Enter the height for the thumbnails in pixels.', 'artificial-image-generator' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="title_font_size"><?php esc_html_e( 'Title Font Size', 'artificial-image-generator' ); ?> <span class="required">*</span></label>
				</th>
				<td>
					<input type="number" id="title_font_size" name="title_font_size" class="regular-text" placeholder="<?php esc_attr_e( '40', 'artificial-image-generator' ); ?>" value="<?php echo esc_attr( get_option( 'aimg_title_font_size', 40 ) ); ?>" min="1" required />
					<p class="description"><?php esc_html_e( 'Enter the font size for the title in pixels.', 'artificial-image-generator' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="is_overlay_image"><?php esc_html_e( 'Enable Overlay Images', 'artificial-image-generator' ); ?></label>
				</th>
				<td>
					<label for="is_overlay_image">
						<input type="checkbox" id="is_overlay_image" name="is_overlay_image" value="1" <?php checked( get_option( 'aimg_is_overlay_image', 'no' ), 'yes' ); ?> />
						<?php esc_html_e( 'Enable', 'artificial-image-generator' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Check this box to enable overlay images in the thumbnails.', 'artificial-image-generator' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="overlay_images"><?php esc_html_e( 'Overlay Images', 'artificial-image-generator' ); ?></label>
				</th>
				<td>
					<div class="aimg-overlay-images">
						<div id="overlay-image-list" class="aimg-overlay-images__items">
							<?php
							$aimg_overlay_images = get_option( 'aimg_overlay_images', array() );
							if ( ! empty( $aimg_overlay_images ) && is_array( $aimg_overlay_images ) ) {
								foreach ( $aimg_overlay_images as $aimg_image_id ) {
									$aimg_image_url = wp_get_attachment_image_url( $aimg_image_id, 'thumbnail' );
									if ( $aimg_image_url ) {
										echo '<div class="aimg-overlay-images__item" data-id="' . esc_attr( $aimg_image_id ) . '">';
										echo '<img src="' . esc_url( $aimg_image_url ) . '" alt="' . esc_attr__( 'Overlay Image', 'artificial-image-generator' ) . '" />';
										echo '<button type="button" class="remove-overlay button button-secondary">' . esc_html__( 'X', 'artificial-image-generator' ) . '</button>';
										echo '</div>';
									}
								}
							} else {
								echo '<p>' . esc_html__( 'No overlay images selected.', 'artificial-image-generator' ) . '</p>';
							}
							?>
						</div>
						<button type="button" id="upload_overlay_images" class="button button-secondary"><?php esc_html_e( 'Select Images', 'artificial-image-generator' ); ?></button>
						<p class="description"><?php esc_html_e( 'Select one or more transparent PNG images to use as overlay images in the thumbnails. Randomly one will be selected while generating the thumbnail.', 'artificial-image-generator' ); ?></p>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="overlay_position"><?php esc_html_e( 'Overlay Image Position', 'artificial-image-generator' ); ?></label>
				</th>
				<td>
					<?php $aimg_overlay_position = get_option( 'aimg_overlay_position', 'center-center' ); ?>
					<select name="overlay_position" id="overlay_position" class="regular-text">
						<option value="top-left" <?php selected( $aimg_overlay_position, 'top-left' ); ?>><?php esc_html_e( 'Top Left', 'artificial-image-generator' ); ?></option>
						<option value="top-center" <?php selected( $aimg_overlay_position, 'top-center' ); ?>><?php esc_html_e( 'Top Center', 'artificial-image-generator' ); ?></option>
						<option value="top-right" <?php selected( $aimg_overlay_position, 'top-right' ); ?>><?php esc_html_e( 'Top Right', 'artificial-image-generator' ); ?></option>
						<option value="left-center" <?php selected( $aimg_overlay_position, 'left-center' ); ?>><?php esc_html_e( 'Left Center', 'artificial-image-generator' ); ?></option>
						<option value="center-center" <?php selected( $aimg_overlay_position, 'center-center' ); ?>><?php esc_html_e( 'Center Center', 'artificial-image-generator' ); ?></option>
						<option value="right-center" <?php selected( $aimg_overlay_position, 'right-center' ); ?>><?php esc_html_e( 'Right Center', 'artificial-image-generator' ); ?></option>
						<option value="bottom-left" <?php selected( $aimg_overlay_position, 'bottom-left' ); ?>><?php esc_html_e( 'Bottom Left', 'artificial-image-generator' ); ?></option>
						<option value="bottom-center" <?php selected( $aimg_overlay_position, 'bottom-center' ); ?>><?php esc_html_e( 'Bottom Center', 'artificial-image-generator' ); ?></option>
						<option value="bottom-right" <?php selected( $aimg_overlay_position, 'bottom-right' ); ?>><?php esc_html_e( 'Bottom Right', 'artificial-image-generator' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Select the position for the overlay images in the thumbnails. The overlay image will be positioned based on this selection. Default is "Center Center".', 'artificial-image-generator' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Preview', 'artificial-image-generator' ); ?></th>
				<td>
					<?php $aimg_preview_image_url = get_option( 'aimg_preview_image_url' ); ?>
					<?php if ( $aimg_preview_image_url ) : ?>
						<img class="preview-image" src="<?php echo esc_url( $aimg_preview_image_url ); ?>" alt="<?php esc_attr_e( 'Preview Image', 'artificial-image-generator' ); ?>"/>
						<p><?php esc_html_e( 'This is a preview of how the thumbnail will look like based on the current settings. Save the settings to generate a new preview.', 'artificial-image-generator' ); ?></p>
					<?php else : ?>
						<p><?php esc_html_e( 'Preview could not be generated. Save the settings to generate a preview.', 'artificial-image-generator' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<input type="hidden" name="action" value="aimg_artificial_image_generator"/>
		<?php wp_nonce_field( 'aimg_artificial_image_generator' ); ?>
		<?php submit_button( 'Save Changes', 'primary', 'artificial_image_generator_submit' ); ?>
	</form>
</div>
