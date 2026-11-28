<?php
/**
 * Template rules by term and author.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Admin\Settings;
use ArtificialImageGenerator\Generator;

/**
 * @covers \ArtificialImageGenerator\Generator::get_rule_templates_for_post
 * @covers \ArtificialImageGenerator\Generator::get_template_id_for_post
 * @covers \ArtificialImageGenerator\Admin\Settings
 */
class Test_Template_Rules extends AIMG_TestCase {

	public function test_the_first_matching_rule_wins_then_type_then_default() {
		$news     = self::factory()->category->create( array( 'name' => 'News' ) );
		$featured = self::factory()->tag->create( array( 'name' => 'Featured' ) );
		$author   = self::factory()->user->create( array( 'role' => 'author' ) );
		$by_news  = $this->create_template();
		$by_tag   = $this->create_template();
		$by_user  = $this->create_template();
		$by_type  = $this->create_template();
		$default  = $this->create_template();

		$this->set_settings(
			array(
				'default_template_id' => $default,
				'auto_post_types'     => array(
					'post' => array(
						'enabled'  => 'yes',
						'template' => $by_type,
					),
				),
				'template_rules'      => array(
					array(
						'type'     => 'term',
						'taxonomy' => 'post_tag',
						'term'     => $featured,
						'template' => $by_tag,
					),
					array(
						'type'     => 'term',
						'taxonomy' => 'category',
						'term'     => $news,
						'template' => $by_news,
					),
					array(
						'type'     => 'author',
						'author'   => $author,
						'template' => $by_user,
					),
				),
			)
		);

		$both = self::factory()->post->create( array( 'post_category' => array( $news ) ) );
		wp_set_post_tags( $both, array( $featured ) );

		$this->assertSame( $by_tag, Generator::get_template_id_for_post( $both ), 'Rule order decides.' );
		$this->assertSame( $by_news, Generator::get_template_id_for_post( self::factory()->post->create( array( 'post_category' => array( $news ) ) ) ) );
		$this->assertSame( $by_user, Generator::get_template_id_for_post( self::factory()->post->create( array( 'post_author' => $author ) ) ) );
		$this->assertSame( $by_type, Generator::get_template_id_for_post( self::factory()->post->create() ), 'No rule: the post type template.' );
		$this->assertSame( $default, Generator::get_template_id_for_post( self::factory()->post->create( array( 'post_type' => 'page' ) ) ), 'Then the default.' );
	}

	public function test_a_rule_with_an_unpublished_template_is_skipped() {
		$news      = self::factory()->category->create();
		$draft     = self::factory()->post->create(
			array(
				'post_type'   => 'aimg_template',
				'post_status' => 'draft',
			)
		);
		$published = $this->create_template();

		$this->set_settings(
			array(
				'template_rules' => array(
					array(
						'type'     => 'term',
						'taxonomy' => 'category',
						'term'     => $news,
						'template' => $draft,
					),
					array(
						'type'     => 'term',
						'taxonomy' => 'category',
						'term'     => $news,
						'template' => $published,
					),
				),
			)
		);

		$this->assertSame( $published, Generator::get_template_id_for_post( self::factory()->post->create( array( 'post_category' => array( $news ) ) ) ) );
	}

	public function test_saving_from_the_settings_app_keeps_complete_rules_only() {
		$news     = self::factory()->category->create( array( 'name' => 'News' ) );
		$author   = self::factory()->user->create(
			array(
				'role'         => 'author',
				'display_name' => 'Ada',
			)
		);
		$template = $this->create_template();

		$saved = Settings::save(
			array(
				'template_rules' => array(
					array(
						'condition' => 'category',
						'item'      => (string) $news,
						'itemLabel' => 'News',
						'template'  => (string) $template,
					),
					array(
						'condition' => 'author',
						'item'      => (string) $author,
						'template'  => (string) $template,
					),
					array(
						'condition' => 'category',
						'item'      => '',
						'template'  => (string) $template,
					),
					array(
						'condition' => 'nope',
						'item'      => (string) $news,
						'template'  => (string) $template,
					),
					array(
						'condition' => 'category',
						'item'      => (string) $news,
						'template'  => '999999',
					),
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'type'     => 'term',
					'taxonomy' => 'category',
					'term'     => $news,
					'template' => $template,
				),
				array(
					'type'     => 'author',
					'author'   => $author,
					'template' => $template,
				),
			),
			$saved['template_rules']
		);

		$values = Settings::values()['template_rules'];
		$this->assertSame( 'News', $values[0]['itemLabel'] );
		$this->assertSame( 'author', $values[1]['condition'] );
		$this->assertSame( 'Ada', $values[1]['itemLabel'] );
	}

	public function test_settings_without_rules_are_unchanged() {
		$this->set_settings( array( 'generation_method' => 'template' ) );

		$this->assertArrayNotHasKey( 'template_rules', get_option( 'aimg_settings' ) );
		$this->assertSame( array(), Settings::values()['template_rules'] );

		$fields = wp_list_pluck( Settings::schema()['fields'], 'type', 'id' );
		$this->assertSame( 'template_rules', $fields['template_rules'] );
	}
}
