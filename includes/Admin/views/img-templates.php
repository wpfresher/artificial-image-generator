<?php
/**
 * Templates list table.
 *
 * @since 1.0.0
 * @package ArtificialImageGenerator\Admin\views
 *
 * @var object $list_table Image templates list table.
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

?>
<div class="wrap">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'Image Templates', 'artificial-image-generator' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=image-generator&add' ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add New', 'artificial-image-generator' ); ?>
		</a>
	</h1>
	<hr class="wp-header-end">
	<form id="aimg-templates-table" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<?php
		$list_table->views();
		$list_table->search_box( __( 'Search', 'artificial-image-generator' ), 'search' );
		$list_table->display();
		?>
		<input type="hidden" name="page" value="artificial-image-generator">
	</form>
</div>
<?php
