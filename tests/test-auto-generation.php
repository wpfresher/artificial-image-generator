<?php
/**
 * Automatic featured image generation.
 *
 * @package ArtificialImageGenerator
 */

/**
 * @covers \ArtificialImageGenerator\GenerateImages
 */
class Test_Auto_Generation extends AIMG_TestCase {

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->create_template();
	}

	public function test_post_saved_without_featured_image_gets_a_generated_one() {
		$post_id      = $this->rest(
			'POST',
			'/wp/v2/posts',
			array(
				'title'  => 'Hello world',
				'status' => 'draft',
			)
		)->get_data()['id'];
		$thumbnail_id = get_post_thumbnail_id( $post_id );

		$this->assertGreaterThan( 0, $thumbnail_id );
		$this->assertSame( '1', get_post_meta( $thumbnail_id, '_aimg_generated', true ) );
	}

	public function test_featured_image_chosen_before_first_save_is_kept() {
		$chosen = $this->create_png_attachment();

		$post_id = $this->rest(
			'POST',
			'/wp/v2/posts',
			array(
				'title'          => 'Chosen image',
				'status'         => 'draft',
				'featured_media' => $chosen,
			)
		)->get_data()['id'];

		$this->assertSame( $chosen, get_post_thumbnail_id( $post_id ) );
		$this->assertCount(
			0,
			get_posts(
				array(
					'post_type' => 'attachment',
					'meta_key'  => '_aimg_generated', // phpcs:ignore WordPress.DB.SlowDBQuery
					'fields'    => 'ids',
				)
			)
		);
	}

	public function test_removing_a_generated_image_opts_the_post_out() {
		$post_id = $this->rest(
			'POST',
			'/wp/v2/posts',
			array(
				'title'  => 'Opt out',
				'status' => 'draft',
			)
		)->get_data()['id'];

		$this->rest( 'POST', '/wp/v2/posts/' . $post_id, array( 'featured_media' => 0 ) );

		$this->assertSame( 0, get_post_thumbnail_id( $post_id ) );
		$this->assertSame( '1', get_post_meta( $post_id, '_aimg_disable_auto', true ) );
	}

	public function test_removing_a_manual_image_does_not_opt_the_post_out() {
		$post_id = $this->rest(
			'POST',
			'/wp/v2/posts',
			array(
				'title'          => 'Manual image',
				'status'         => 'draft',
				'featured_media' => $this->create_png_attachment(),
			)
		)->get_data()['id'];

		$this->rest( 'POST', '/wp/v2/posts/' . $post_id, array( 'featured_media' => 0 ) );

		$this->assertSame( '', get_post_meta( $post_id, '_aimg_disable_auto', true ) );
		$this->assertGreaterThan( 0, get_post_thumbnail_id( $post_id ) );
	}

	public function test_title_entities_are_decoded() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Tom &amp; Jerry\'s "Best"' ) );

		$this->assertSame( 'Tom & Jerry\'s "Best"', aimg_get_plain_title( $post_id ) );
	}
}
