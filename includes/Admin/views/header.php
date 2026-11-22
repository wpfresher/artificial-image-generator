<?php
/**
 * Plugin header shown on every plugin page.
 *
 * @since 1.9.0
 * @package ArtificialImageGenerator\Admin\Views
 *
 * @var string $page  Current page slug.
 * @var array  $nav   Items as slug => [ label, url ].
 * @var array  $links Pro, docs, support and review links.
 */

use ArtificialImageGenerator\Admin\Admin;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

$aimg_active = 'studio' === $page && Admin::is_edit_screen() ? 'templates' : $page;
?>
<div class="aimg-header">
	<a class="aimg-header__brand" href="<?php echo esc_url( admin_url( 'admin.php?page=image-generator' ) ); ?>">
		<span class="aimg-header__logo" aria-hidden="true"><span class="dashicons dashicons-format-image"></span></span>
		<span class="aimg-header__name"><?php esc_html_e( 'Image Generator', 'artificial-image-generator' ); ?></span>
		<span class="aimg-header__version"><?php echo esc_html( AIMG_VERSION ); ?></span>
	</a>
	<nav class="aimg-header__nav" aria-label="<?php esc_attr_e( 'Image Generator', 'artificial-image-generator' ); ?>">
		<?php foreach ( $nav as $aimg_slug => $aimg_item ) : ?>
			<a class="aimg-header__tab<?php echo $aimg_slug === $aimg_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $aimg_item[1] ); ?>"<?php echo $aimg_slug === $aimg_active ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $aimg_item[0] ); ?></a>
		<?php endforeach; ?>
	</nav>
	<div class="aimg-header__actions">
		<a class="aimg-header__link" href="<?php echo esc_url( $links['docs'] ); ?>" target="_blank" rel="noopener noreferrer">
			<span class="dashicons dashicons-media-document" aria-hidden="true"></span><?php esc_html_e( 'Docs', 'artificial-image-generator' ); ?>
		</a>
		<a class="aimg-header__link" href="<?php echo esc_url( $links['support'] ); ?>" target="_blank" rel="noopener noreferrer">
			<span class="dashicons dashicons-sos" aria-hidden="true"></span><?php esc_html_e( 'Support', 'artificial-image-generator' ); ?>
		</a>
		<?php if ( ! defined( 'AIMG_PRO_VERSION' ) ) : ?>
			<a class="button button-primary aimg-header__pro" href="<?php echo esc_url( $links['pro'] ); ?>" target="_blank" rel="noopener noreferrer">
				<span class="dashicons dashicons-star-filled" aria-hidden="true"></span><?php esc_html_e( 'Get Pro', 'artificial-image-generator' ); ?>
			</a>
		<?php endif; ?>
	</div>
</div>
