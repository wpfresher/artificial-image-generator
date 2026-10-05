<?php
/**
 * Prompt builder.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\PromptBuilder;

/**
 * @covers \ArtificialImageGenerator\PromptBuilder
 */
class Test_Prompt_Builder extends AIMG_TestCase {

	public function test_tags_are_replaced() {
		$category = self::factory()->category->create( array( 'name' => 'Gardening' ) );
		$post_id  = self::factory()->post->create(
			array(
				'post_title'    => 'Growing Tomatoes',
				'post_excerpt'  => 'Tips for a big harvest.',
				'post_category' => array( $category ),
			)
		);
		update_post_meta( $post_id, 'season', 'summer' );

		$prompt = PromptBuilder::build(
			$post_id,
			array(
				'template' => '{title} | {excerpt} | {category} | {custom_field:season} | {unknown}',
				'style'    => 'none',
			)
		);

		$this->assertStringStartsWith( 'Growing Tomatoes | Tips for a big harvest. | Gardening | summer | {unknown}', $prompt );
	}

	public function test_style_and_instructions_are_appended() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Space' ) );

		$prompt = PromptBuilder::build(
			$post_id,
			array(
				'template' => '{title}',
				'style'    => 'watercolor',
			)
		);

		$this->assertSame( 'Space ' . PromptBuilder::get_styles()['watercolor'][1] . ' ' . PromptBuilder::get_default_negative(), $prompt );

		$this->set_settings( array( 'ai_negative_prompt' => '' ) );
		$this->assertSame(
			'Space',
			PromptBuilder::build(
				$post_id,
				array(
					'template' => '{title}',
					'style'    => 'none',
				)
			)
		);
	}

	public function test_excerpt_falls_back_to_content() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Long post',
				'post_excerpt' => '',
				'post_content' => '<!-- wp:paragraph --><p>' . str_repeat( 'word ', 60 ) . '</p><!-- /wp:paragraph -->',
			)
		);

		$values = PromptBuilder::get_tag_values( $post_id );

		$this->assertSame( 40, str_word_count( rtrim( $values['excerpt'], '…' ) ) );
		$this->assertStringNotContainsString( '<p>', $values['excerpt'] );
	}

	public function test_unsaved_editor_values_override_the_post() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Saved title' ) );

		$prompt = PromptBuilder::build(
			$post_id,
			array(
				'template' => '{title}: {excerpt}',
				'style'    => 'none',
				'values'   => array(
					'title'   => 'Unsaved title',
					'excerpt' => 'Unsaved excerpt',
				),
			)
		);

		$this->assertStringStartsWith( 'Unsaved title: Unsaved excerpt', $prompt );
	}

	public function test_empty_excerpt_leaves_no_dangling_colon() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Bare',
				'post_content' => '',
			)
		);
		$this->set_settings( array( 'ai_negative_prompt' => '' ) );

		$this->assertStringEndsNotWith( ':', PromptBuilder::build( $post_id, array( 'style' => 'none' ) ) );
	}
}
