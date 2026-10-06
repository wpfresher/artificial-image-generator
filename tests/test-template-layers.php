<?php
/**
 * Layer types and merge tags added in M1 step 2.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Rendering\GdRenderer;
use ArtificialImageGenerator\Templates\MergeTags;
use ArtificialImageGenerator\Templates\Schema;

/**
 * @covers \ArtificialImageGenerator\Rendering\Layers\Shape
 * @covers \ArtificialImageGenerator\Rendering\Layers\Pattern
 * @covers \ArtificialImageGenerator\Rendering\Layers\Frame
 * @covers \ArtificialImageGenerator\Rendering\Paint
 * @covers \ArtificialImageGenerator\Templates\MergeTags
 */
class Test_Template_Layers extends AIMG_TestCase {

	/**
	 * Render layers on a black 200×100 canvas.
	 *
	 * @param array $layers  Layers.
	 * @param array $tags    Merge tags.
	 * @param int   $post_id Post ID.
	 *
	 * @return \GdImage|resource
	 */
	private function render( $layers, $tags = array(), $post_id = 0 ) {
		return GdRenderer::render(
			Schema::sanitize(
				array(
					'canvas' => array(
						'width'      => 200,
						'height'     => 100,
						'background' => '#000000',
					),
					'layers' => $layers,
				)
			),
			$tags,
			$post_id
		);
	}

	/**
	 * Color at a pixel as array( r, g, b, alpha ).
	 *
	 * @param \GdImage|resource $image Image.
	 * @param int               $x     X.
	 * @param int               $y     Y.
	 *
	 * @return int[]
	 */
	private function pixel( $image, $x, $y ) {
		$c = imagecolorat( $image, $x, $y );

		return array( ( $c >> 16 ) & 0xFF, ( $c >> 8 ) & 0xFF, $c & 0xFF, ( $c >> 24 ) & 0x7F );
	}

	public function test_linear_gradient_runs_in_the_given_direction() {
		$image = $this->render(
			array(
				array(
					'type' => 'background',
					'fill' => array(
						'kind'  => 'linear',
						'angle' => 90,
						'stops' => array(
							array(
								'color' => '#000000',
								'pos'   => 0,
							),
							array(
								'color' => '#ffffff',
								'pos'   => 1,
							),
						),
					),
				),
			)
		);

		$this->assertLessThan( 20, $this->pixel( $image, 2, 50 )[0], 'Dark on the left.' );
		$this->assertGreaterThan( 235, $this->pixel( $image, 197, 50 )[0], 'Light on the right.' );
		$this->assertEqualsWithDelta( 128, $this->pixel( $image, 100, 50 )[0], 12 );
	}

	public function test_shapes_fill_their_box_with_smooth_edges() {
		$image = $this->render(
			array(
				array(
					'type'   => 'shape',
					'shape'  => 'rect',
					'box'    => array(
						'x' => 50,
						'y' => 20,
						'w' => 100,
						'h' => 60,
					),
					'radius' => 20,
					'fill'   => array( 'color' => '#ff0000' ),
				),
			)
		);

		$this->assertSame( array( 255, 0, 0, 0 ), $this->pixel( $image, 100, 50 ) );
		$this->assertSame( array( 0, 0, 0, 0 ), $this->pixel( $image, 51, 21 ), 'Rounded corner stays empty.' );
		$this->assertSame( array( 0, 0, 0, 0 ), $this->pixel( $image, 160, 50 ), 'Nothing outside the box.' );
	}

	public function test_border_only_shapes_and_frames_are_hollow() {
		$image = $this->render(
			array(
				array(
					'type'   => 'shape',
					'shape'  => 'ellipse',
					'box'    => array(
						'x' => 10,
						'y' => 10,
						'w' => 80,
						'h' => 80,
					),
					'fill'   => array( 'kind' => 'none' ),
					'border' => array(
						'width' => 6,
						'color' => '#00ff00',
					),
				),
				array(
					'type'  => 'frame',
					'width' => 4,
					'color' => '#0000ff',
				),
			)
		);

		$this->assertSame( array( 0, 0, 0, 0 ), $this->pixel( $image, 50, 50 ), 'The ring is empty inside.' );
		$this->assertSame( 255, $this->pixel( $image, 50, 13 )[1], 'The ring is drawn.' );
		$this->assertSame( array( 0, 0, 255, 0 ), $this->pixel( $image, 1, 50 ), 'The frame is drawn at the edge.' );
		$this->assertSame( array( 0, 0, 0, 0 ), $this->pixel( $image, 150, 50 ), 'The frame is hollow.' );
	}

	public function test_patterns_draw_at_their_opacity() {
		$image = $this->render(
			array(
				array(
					'type'    => 'pattern',
					'pattern' => 'grid',
					'color'   => '#ffffff',
					'opacity' => 0.5,
					'spacing' => 20,
					'size'    => 1,
				),
			)
		);

		$this->assertEqualsWithDelta( 128, $this->pixel( $image, 0, 10 )[0], 2, 'On a grid line.' );
		$this->assertSame( 0, $this->pixel( $image, 10, 10 )[0], 'Between lines.' );
	}

	public function test_rotated_and_styled_text_draws_within_reach() {
		$plain  = $this->render(
			array(
				array(
					'type'    => 'text',
					'content' => 'Hi',
					'size'    => array( 'max' => 40 ),
				),
			)
		);
		$styled = $this->render(
			array(
				array(
					'type'     => 'text',
					'content'  => 'Hi',
					'size'     => array( 'max' => 40 ),
					'rotation' => 30,
					'stroke'   => array(
						'width' => 3,
						'color' => '#ff0000',
					),
				),
			)
		);

		$white = 0;
		$red   = 0;
		for ( $x = 0; $x < 200; $x++ ) {
			for ( $y = 0; $y < 100; $y++ ) {
				$white += $this->pixel( $plain, $x, $y )[1] > 200 ? 1 : 0;
				$pixel  = $this->pixel( $styled, $x, $y );
				$red   += $pixel[0] > 200 && $pixel[1] < 60 ? 1 : 0;
			}
		}
		$this->assertGreaterThan( 50, $white, 'Plain text is drawn.' );
		$this->assertGreaterThan( 50, $red, 'The outline is drawn.' );
	}

	public function test_merge_tags_for_a_post() {
		$author = self::factory()->user->create( array( 'display_name' => 'Ada & Co' ) );
		$cat    = self::factory()->category->create( array( 'name' => 'Science' ) );
		$post   = self::factory()->post->create(
			array(
				'post_title'    => 'Bees',
				'post_author'   => $author,
				'post_content'  => str_repeat( 'word ', 450 ),
				'post_category' => array( $cat ),
			)
		);
		update_post_meta( $post, 'subtitle', 'All about <b>bees</b>' );
		update_post_meta( $post, '_secret', 'hidden' );

		$values = MergeTags::values( $post );

		$this->assertSame( 'Ada & Co', $values['author'] );
		$this->assertSame( 'Science', $values['category'] );
		$this->assertSame( '3 min read', $values['reading_time'] );
		$this->assertSame(
			'Bees by Ada & Co: All about bees.  ',
			MergeTags::replace( '{title} by {author}: {custom_field:subtitle}.{custom_field:_secret} {unknown} ', $values, $post ),
			'Protected meta and unknown tags render as nothing.'
		);
		$this->assertSame( '{author}', MergeTags::replace( '{title}', array( 'title' => '{author}' ) ), 'Values are never parsed again.' );
	}

	public function test_show_if_hides_layers_with_an_empty_tag() {
		$layers = array(
			array(
				'type'    => 'overlay',
				'color'   => '#ffffff',
				'opacity' => 1,
				'showIf'  => 'category',
			),
		);

		$this->assertSame( 0, $this->pixel( $this->render( $layers, array( 'category' => '' ) ), 5, 5 )[0] );
		$this->assertSame( 255, $this->pixel( $this->render( $layers, array( 'category' => 'News' ) ), 5, 5 )[0] );
		$this->assertSame( '', MergeTags::sanitize_condition( '{title}' ), 'Conditions are tag names only.' );
	}

	public function test_image_masks_and_post_images() {
		$png  = $this->create_png_attachment( 'aimg-layer-mask.png' );
		$post = self::factory()->post->create();
		set_post_thumbnail( $post, $png );

		$file  = get_attached_file( $png );
		$white = imagecreatetruecolor( 50, 50 );
		imagefill( $white, 0, 0, imagecolorallocate( $white, 255, 255, 255 ) );
		imagepng( $white, $file );

		$image = $this->render(
			array(
				array(
					'type'   => 'image',
					'source' => 'featured',
					'box'    => array(
						'x' => 0,
						'y' => 0,
						'w' => 100,
						'h' => 100,
					),
					'fit'    => 'cover',
					'mask'   => 'circle',
				),
			),
			array(),
			$post
		);

		$this->assertSame( 255, $this->pixel( $image, 50, 50 )[0], 'The post image is drawn.' );
		$this->assertSame( 0, $this->pixel( $image, 2, 2 )[0], 'The circle mask leaves the corners empty.' );

		$none = $this->render(
			array(
				array(
					'type'   => 'image',
					'source' => 'featured',
				),
			)
		);
		$this->assertSame( 0, $this->pixel( $none, 50, 50 )[0], 'No post, no image.' );
	}

	public function test_template_images_from_rest_are_attached_to_the_post() {
		$post     = self::factory()->post->create();
		$response = $this->rest(
			'POST',
			'/aimg/v1/generate',
			array(
				'template_id' => $this->create_template(),
				'post_id'     => $post,
			)
		);

		$this->assertSame( $post, wp_get_post_parent_id( $response->get_data()['id'] ) );
	}
}
