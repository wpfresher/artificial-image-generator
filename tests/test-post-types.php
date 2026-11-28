<?php
/**
 * Automatic featured images per post type.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Admin\Settings;
use ArtificialImageGenerator\Generator;

/**
 * @covers \ArtificialImageGenerator\Generator::get_post_type_rule
 * @covers \ArtificialImageGenerator\Generator::get_method_for_post
 * @covers \ArtificialImageGenerator\GenerateImages::generate_thumbnails
 */
class Test_Post_Types extends AIMG_TestCase {

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();
		register_post_type(
			'aimg_book',
			array(
				'public'   => true,
				'show_ui'  => true,
				'supports' => array( 'title', 'thumbnail' ),
				'label'    => 'Books',
			)
		);
		add_filter( 'aimg_kick_queue', '__return_false' );
	}

	/**
	 * Tear down each test.
	 */
	public function tear_down() {
		unregister_post_type( 'aimg_book' );
		parent::tear_down();
	}

	/**
	 * A published item.
	 *
	 * @param string $post_type Post type.
	 *
	 * @return int
	 */
	private function publish( $post_type ) {
		return wp_insert_post(
			array(
				'post_type'   => $post_type,
				'post_title'  => 'An item',
				'post_status' => 'publish',
			)
		);
	}

	public function test_posts_and_pages_follow_the_old_settings_until_saved() {
		$this->assertTrue( Generator::get_post_type_rule( 'post' )['enabled'] );
		$this->assertFalse( Generator::get_post_type_rule( 'page' )['enabled'] );
		$this->assertFalse( Generator::get_post_type_rule( 'aimg_book' )['enabled'] );

		$this->set_settings(
			array(
				'is_post_thumbnail' => 'no',
				'is_page_thumbnail' => 'yes',
			)
		);

		$this->assertFalse( Generator::get_post_type_rule( 'post' )['enabled'] );
		$this->assertTrue( Generator::get_post_type_rule( 'page' )['enabled'] );
	}

	public function test_a_custom_post_type_gets_an_image_once_turned_on() {
		$this->create_template();

		$this->assertFalse( has_post_thumbnail( $this->publish( 'aimg_book' ) ) );

		$this->set_settings( array( 'auto_post_types' => array( 'aimg_book' => array( 'enabled' => 'yes' ) ) ) );

		$this->assertTrue( has_post_thumbnail( $this->publish( 'aimg_book' ) ) );
	}

	public function test_post_types_without_featured_images_stay_off() {
		$this->set_settings( array( 'auto_post_types' => array( 'attachment' => array( 'enabled' => 'yes' ) ) ) );

		$this->assertFalse( Generator::get_post_type_rule( 'attachment' )['enabled'] );
	}

	public function test_a_post_type_can_use_its_own_method() {
		$this->set_settings(
			array(
				'generation_method' => 'template',
				'auto_post_types'   => array(
					'aimg_book' => array(
						'enabled' => 'yes',
						'method'  => 'stock',
					),
					'page'      => array(
						'enabled' => 'yes',
						'method'  => 'nonsense',
					),
				),
			)
		);

		$book = self::factory()->post->create( array( 'post_type' => 'aimg_book' ) );
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertSame( 'stock', Generator::get_method_for_post( $book ) );
		$this->assertSame( 'template', Generator::get_method_for_post( $page ), 'An unknown method falls back to the default.' );
	}

	public function test_a_post_type_template_comes_before_the_default_template() {
		$default = $this->create_template();
		$books   = $this->create_template();
		$draft   = self::factory()->post->create(
			array(
				'post_type'   => 'aimg_template',
				'post_status' => 'draft',
			)
		);
		$this->set_settings(
			array(
				'default_template_id' => $default,
				'auto_post_types'     => array(
					'aimg_book' => array(
						'enabled'  => 'yes',
						'template' => $books,
					),
					'page'      => array(
						'enabled'  => 'yes',
						'template' => $draft,
					),
				),
			)
		);

		$this->assertSame( $books, Generator::get_template_id_for_post( self::factory()->post->create( array( 'post_type' => 'aimg_book' ) ) ) );
		$this->assertSame( $default, Generator::get_template_id_for_post( self::factory()->post->create( array( 'post_type' => 'page' ) ) ), 'An unpublished template falls back to the default.' );
		$this->assertSame( $default, Generator::get_template_id_for_post( self::factory()->post->create() ) );
	}

	public function test_saving_keeps_the_old_settings_in_step() {
		$template = $this->create_template();

		$saved = Settings::save(
			array(
				'auto_post_types' => array(
					'post'      => array(
						'enabled' => false,
						'method'  => '',
					),
					'page'      => array( 'enabled' => true ),
					'aimg_book' => array(
						'enabled'  => true,
						'method'   => 'stock_template',
						'template' => (string) $template,
					),
					'nope'      => array( 'enabled' => true ),
				),
			)
		);

		$this->assertSame( 'no', $saved['is_post_thumbnail'] );
		$this->assertSame( 'yes', $saved['is_page_thumbnail'] );
		$this->assertSame(
			array(
				'enabled'  => 'yes',
				'method'   => 'stock_template',
				'template' => $template,
			),
			$saved['auto_post_types']['aimg_book']
		);
		$this->assertArrayNotHasKey( 'nope', $saved['auto_post_types'] );
	}

	public function test_settings_app_values_and_schema() {
		$values = (array) Settings::values()['auto_post_types'];

		$this->assertTrue( $values['post']['enabled'] );
		$this->assertFalse( $values['aimg_book']['enabled'] );

		$fields = wp_list_pluck( Settings::schema()['fields'], 'type', 'id' );
		$this->assertSame( 'post_types', $fields['auto_post_types'] );
		$this->assertArrayNotHasKey( 'is_post_thumbnail', $fields );
	}
}
