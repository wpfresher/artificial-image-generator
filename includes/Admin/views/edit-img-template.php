<?php
/**
 * Edit Image Template Admin View.
 *
 * The Template Studio mounts here and replaces this markup.
 *
 * @package ArtificialImageGenerator\Admin\Views
 * @since 1.0.0
 * @var \WP_Post $template The image template post object.
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.
?>
<div id="aimg-template-studio" class="wrap">
	<h1><?php esc_html_e( 'Edit Image Template', 'artificial-image-generator' ); ?></h1>
	<div class="notice notice-info inline">
		<p><?php esc_html_e( 'Loading the Template Studio…', 'artificial-image-generator' ); ?></p>
		<p><?php esc_html_e( 'If this message does not go away, make sure JavaScript is enabled and reload the page. Your templates keep working in the meantime.', 'artificial-image-generator' ); ?></p>
	</div>
</div>
