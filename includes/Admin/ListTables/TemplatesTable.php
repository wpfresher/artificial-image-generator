<?php

namespace ArtificialImageGenerator\Admin\ListTables;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

// WP_List_Table is not loaded automatically, so we need to load it in our application.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class TemplatesTable.
 *
 * @since 1.0.0
 * @package ArtificialImageGenerator\Admin\ListTables
 */
class TemplatesTable extends \WP_List_Table {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'template',
				'plural'   => 'templates',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Prepare items.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = $this->get_hidden_columns();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$per_page              = $this->get_items_per_page( 'aimg_img_templates_per_page', 20 );
		$paged                 = $this->get_pagenum();
		$order_by              = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read only operation.
		$order                 = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read only operation.
		$search                = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read only operation.

		$args = array(
			'post_type'      => 'aimg_template',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			's'              => $search,
			'orderby'        => $order_by,
			'order'          => $order,
			'post_status'    => 'any',
		);

		/**
		 * Filter the query arguments for the list table.
		 *
		 * @param array $args An associative array of arguments.
		 *
		 * @since 1.0.0
		 */
		$args = apply_filters( 'aimg_templates_table_query_args', $args );

		$this->items = aimg_get_templates( $args );
		$total       = aimg_get_templates( $args, true );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * No items found text.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No templates found.', 'artificial-image-generator' );
	}


	/**
	 * Get the table columns
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_columns() {
		$columns = array(
			'cb'          => '<input type="checkbox" />',
			'name'        => __( 'Name', 'artificial-image-generator' ),
			'preview_img' => __( 'Preview Image', 'artificial-image-generator' ),
			'date'        => __( 'Date', 'artificial-image-generator' ),
		);

		return $columns;
	}

	/**
	 * Get hidden columns.
	 */
	public function get_hidden_columns() {
		return get_hidden_columns( get_current_screen() );
	}

	/**
	 * Get sortable columns.
	 */
	public function get_sortable_columns() {
		return array( 'name' => array( 'post_title', true ) );
	}

	/**
	 * Get primary columns name. or define the primary column name.
	 */
	public function get_primary_column_name() {
		return 'name';
	}

	/**
	 * Renders the checkbox column in the items list table.
	 *
	 * @param Object $item The current master key object.
	 *
	 * @return string Displays a checkbox.
	 * @since  1.0.0
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d"/>', esc_attr( $item->ID ) );
	}

	/**
	 * Renders the master_key column in the items list table.
	 *
	 * @param Object $item The current master key object.
	 *
	 * @return string Displays the Master key.
	 * @since  1.0.0
	 */
	public function column_name( $item ) {
		$edit_url   = add_query_arg( array( 'edit' => $item->ID ), admin_url( 'admin.php?page=image-generator' ) );
		$delete_url = add_query_arg(
			array(
				'ids'    => $item->ID,
				'action' => 'delete',
			),
			admin_url( 'admin.php?page=image-generator' )
		);
		$item_title = sprintf( '<a href="%1$s">%2$s</a>', $edit_url, esc_html( $item->post_title ) );
		// translators: %d: key id.
		$actions['ids']    = sprintf( __( 'ID: %d', 'artificial-image-generator' ), esc_html( $item->ID ) );
		$actions['edit']   = sprintf( '<a href="%1$s">%2$s</a>', $edit_url, __( 'Edit', 'artificial-image-generator' ) );
		$actions['delete'] = sprintf( '<a href="%1$s">%2$s</a>', wp_nonce_url( $delete_url, 'bulk-' . $this->_args['plural'] ), __( 'Delete', 'artificial-image-generator' ) );

		return sprintf( '%1$s %2$s', $item_title, $this->row_actions( $actions ) );
	}

	/**
	 * Get bulk actions.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'artificial-image-generator' ),
		);
	}

	/**
	 * Display column preview_img.
	 *
	 * @param Object $item Item.
	 *
	 * @since 1.0.0
	 */
	protected function column_preview_img( $item ) {
		$value           = '&mdash;';
		$preview_img_url = get_post_meta( $item->ID, '_aimg_preview_image_url', true );
		if ( $preview_img_url ) {
			$value = sprintf( '<img src="%s" alt="%s" style="max-width:50px; height:auto;"/>', esc_url( $preview_img_url ), esc_attr( $item->post_title ) );
		}

		return $value;
	}

	/**
	 * Display column date.
	 *
	 * @param Object $item Item.
	 *
	 * @since 1.0.0
	 */
	protected function column_date( $item ) {
		$value = '&mdash;';
		$date  = $item->post_date;
		if ( $date ) {
			$value = sprintf( '<time datetime="%s">%s</time>', esc_attr( $date ), esc_html( date_i18n( get_option( 'date_format' ) . ' | ' . get_option( 'time_format' ), strtotime( $date ) ) ) );
		}

		return $value;
	}
}
