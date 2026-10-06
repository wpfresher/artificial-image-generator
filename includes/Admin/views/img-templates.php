<?php
/**
 * Image templates as a grid of cards.
 *
 * @since 1.0.0
 * @since 1.7.0 Cards with previews and actions instead of a table.
 * @package ArtificialImageGenerator\Admin\views
 *
 * @var WP_Post[] $templates Templates to show.
 * @var string    $search    Current search.
 */

use ArtificialImageGenerator\Admin\Actions;
use ArtificialImageGenerator\Templates\Repository;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

$aimg_default = absint( aimg_get_settings( 'default_template_id', 0 ) );
?>
<div class="wrap aimg-templates">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'Image Templates', 'artificial-image-generator' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=image-generator&add' ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add New', 'artificial-image-generator' ); ?>
		</a>
	</h1>
	<hr class="wp-header-end">

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="image-generator">
		<?php wp_nonce_field( 'bulk-templates', '_wpnonce', false ); ?>

		<div class="aimg-templates__bar">
			<div>
				<label class="screen-reader-text" for="aimg-bulk-action"><?php esc_html_e( 'Bulk actions', 'artificial-image-generator' ); ?></label>
				<select name="action" id="aimg-bulk-action">
					<option value="-1"><?php esc_html_e( 'Bulk actions', 'artificial-image-generator' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete', 'artificial-image-generator' ); ?></option>
				</select>
				<?php submit_button( __( 'Apply', 'artificial-image-generator' ), 'action', '', false ); ?>
			</div>
			<p class="search-box">
				<label class="screen-reader-text" for="aimg-search"><?php esc_html_e( 'Search templates', 'artificial-image-generator' ); ?></label>
				<input type="search" id="aimg-search" name="s" value="<?php echo esc_attr( $search ); ?>">
				<?php submit_button( __( 'Search', 'artificial-image-generator' ), '', '', false ); ?>
			</p>
		</div>

		<?php if ( empty( $templates ) ) : ?>
			<div class="aimg-templates__empty">
				<p><?php echo '' !== $search ? esc_html__( 'No templates match your search.', 'artificial-image-generator' ) : esc_html__( 'No templates yet. Create one to make on-brand featured images.', 'artificial-image-generator' ); ?></p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=image-generator&add' ) ); ?>"><?php esc_html_e( 'Create a template', 'artificial-image-generator' ); ?></a>
			</div>
		<?php else : ?>
			<ul class="aimg-templates__grid">
				<?php foreach ( $templates as $aimg_template ) : ?>
					<?php
					$aimg_id      = (int) $aimg_template->ID;
					$aimg_title   = aimg_plain_text( $aimg_template->post_title );
					$aimg_preview = (string) get_post_meta( $aimg_id, '_aimg_preview_image_url', true );
					$aimg_width   = (int) get_post_meta( $aimg_id, '_aimg_width', true );
					$aimg_height  = (int) get_post_meta( $aimg_id, '_aimg_height', true );
					$aimg_edit    = admin_url( 'admin.php?page=image-generator&edit=' . $aimg_id );
					$aimg_is_def  = $aimg_default === $aimg_id;
					?>
					<li class="aimg-card<?php echo 'publish' !== $aimg_template->post_status ? ' is-draft' : ''; ?>">
						<a class="aimg-card__preview" href="<?php echo esc_url( $aimg_edit ); ?>">
							<?php if ( $aimg_preview ) : ?>
								<img src="<?php echo esc_url( $aimg_preview ); ?>" alt="" loading="lazy">
							<?php else : ?>
								<span><?php esc_html_e( 'No preview yet', 'artificial-image-generator' ); ?></span>
							<?php endif; ?>
						</a>
						<div class="aimg-card__body">
							<label class="aimg-card__check">
								<input type="checkbox" name="ids[]" value="<?php echo esc_attr( $aimg_id ); ?>">
								<span class="screen-reader-text"><?php echo esc_html( $aimg_title ); ?></span>
							</label>
							<a class="aimg-card__title" href="<?php echo esc_url( $aimg_edit ); ?>"><?php echo esc_html( '' !== $aimg_title ? $aimg_title : __( '(no title)', 'artificial-image-generator' ) ); ?></a>
							<p class="aimg-card__meta">
								<?php if ( 'publish' !== $aimg_template->post_status ) : ?>
									<span class="aimg-badge"><?php esc_html_e( 'Draft', 'artificial-image-generator' ); ?></span>
								<?php endif; ?>
								<?php if ( $aimg_is_def ) : ?>
									<span class="aimg-badge is-default"><?php esc_html_e( 'Default', 'artificial-image-generator' ); ?></span>
								<?php endif; ?>
								<?php if ( Repository::has_document( $aimg_id ) ) : ?>
									<span class="aimg-badge"><?php esc_html_e( 'Studio', 'artificial-image-generator' ); ?></span>
								<?php endif; ?>
								<?php if ( $aimg_width && $aimg_height ) : ?>
									<span><?php echo esc_html( $aimg_width . ' × ' . $aimg_height ); ?></span>
								<?php endif; ?>
							</p>
							<p class="aimg-card__actions">
								<a href="<?php echo esc_url( $aimg_edit ); ?>"><?php esc_html_e( 'Edit', 'artificial-image-generator' ); ?></a>
								<a href="<?php echo esc_url( Actions::action_url( 'duplicate', $aimg_id ) ); ?>"><?php esc_html_e( 'Duplicate', 'artificial-image-generator' ); ?></a>
								<?php if ( $aimg_is_def ) : ?>
									<a href="<?php echo esc_url( Actions::action_url( 'undefault', $aimg_id ) ); ?>"><?php esc_html_e( 'Use a random template', 'artificial-image-generator' ); ?></a>
								<?php elseif ( 'publish' === $aimg_template->post_status ) : ?>
									<a href="<?php echo esc_url( Actions::action_url( 'default', $aimg_id ) ); ?>"><?php esc_html_e( 'Set as default', 'artificial-image-generator' ); ?></a>
								<?php endif; ?>
								<a href="<?php echo esc_url( Actions::action_url( 'export', $aimg_id ) ); ?>"><?php esc_html_e( 'Export', 'artificial-image-generator' ); ?></a>
								<a class="aimg-card__delete" href="<?php echo esc_url( Actions::action_url( 'delete', $aimg_id ) ); ?>" onclick="return window.confirm( <?php echo esc_attr( wp_json_encode( __( 'Delete this template? Images already made with it stay in the Media Library.', 'artificial-image-generator' ) ) ); ?> );"><?php esc_html_e( 'Delete', 'artificial-image-generator' ); ?></a>
							</p>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</form>
</div>
