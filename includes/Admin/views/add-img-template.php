<?php
/**
 * Add New Image Template Admin View.
 *
 * This file renders the admin view for adding a new image template in the Artificial Image Generator plugin.
 *
 * @package ArtificialImageGenerator\Admin\Views
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.
?>
<div class="wrap aimg-wrap">
	<h1>
		<?php esc_html_e( 'Add New Image Template', 'artificial-image-generator' ); ?>
		<abbr title="<?php esc_attr_e( 'Image Generator', 'artificial-image-generator' ); ?>" class="dashicons dashicons-format-image"></abbr>
	</h1>
	<p><?php esc_html_e( 'Configure the template options to generate images.', 'artificial-image-generator' ); ?></p>
	<form id="aimg-form" method="POST" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<div class="columns">
			<div class="column column-left">
				<table class="form-table">
					<tbody>
					<tr>
						<th scope="row">
							<label for="title"><?php esc_html_e( 'Title', 'artificial-image-generator' ); ?> <span class="required">*</span></label>
						</th>
						<td>
							<input type="text" id="title" name="title" class="regular-text" placeholder="<?php esc_attr_e( 'Awesome image template', 'artificial-image-generator' ); ?>" required />
							<p class="description"><?php esc_html_e( 'Enter the title for the image template.', 'artificial-image-generator' ); ?></p>
						</td>
					</tr>
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
								<div id="overlay-image-list" class="aimg-overlay-images__items"></div>
								<button type="button" id="upload_overlay_images" class="button button-secondary"><?php esc_html_e( 'Select Images', 'artificial-image-generator' ); ?></button>
								<input type="hidden" id="overlay_images" name="overlay_images" value=""/>
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
					</tbody>
				</table>
			</div>

			<div class="column column-right">
				<div class="preview-section">
					<h2><?php esc_html_e( 'Preview Image', 'artificial-image-generator' ); ?></h2>
					<?php $aimg_preview_image_url = get_option( 'aimg_preview_image_url' ); ?>
					<?php if ( $aimg_preview_image_url ) : ?>
						<div class="preview-image">
							<img src="<?php echo esc_url( $aimg_preview_image_url ); ?>" alt="<?php esc_attr_e( 'Preview Image', 'artificial-image-generator' ); ?>"/>
						</div>
						<p><?php esc_html_e( 'This is a preview of how the image will look like based on the current settings. Save changes to generate a new preview.', 'artificial-image-generator' ); ?></p>
					<?php else : ?>
						<p><?php esc_html_e( 'Preview could not be generated. Save changes to generate a preview.', 'artificial-image-generator' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<input type="hidden" name="action" value="aimg_update_template"/>
		<?php wp_nonce_field( 'aimg_update_template' ); ?>
		<?php submit_button( 'Save Changes', 'primary', 'aimg_submit' ); ?>
	</form>
</div>
