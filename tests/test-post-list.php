<?php
/**
 * Post list column, row action and bulk action.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Admin\PostList;
use ArtificialImageGenerator\Bulk\Runner;
use ArtificialImageGenerator\Generator;
use ArtificialImageGenerator\Queue;

/**
 * @covers \ArtificialImageGenerator\Admin\PostList
 */
class Test_Post_List extends AIMG_TestCase {

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();
		add_filter( 'aimg_use_action_scheduler', '__return_false' );
		add_filter( 'aimg_kick_queue', '__return_false' );
		$this->set_settings( array( 'is_post_thumbnail' => 'no' ) );
		delete_option( Runner::OPTION );
	}

	/**
	 * A published post.
	 *
	 * @return int
	 */
	private function post() {
		return self::factory()->post->create(
			array(
				'post_title'  => 'Listed post',
				'post_status' => 'publish',
			)
		);
	}

	public function test_column_follows_the_title() {
		$columns = ( new PostList() )->add_column(
			array(
				'cb'    => '',
				'title' => 'Title',
				'date'  => 'Date',
			)
		);

		$this->assertSame( array( 'cb', 'title', PostList::COLUMN, 'date' ), array_keys( $columns ) );
	}

	public function test_source_label() {
		$attachment = $this->create_png_attachment();

		Generator::mark_generated( $attachment, array( 'source' => 'template' ) );
		$this->assertSame( 'Template', PostList::source_label( $attachment ) );

		Generator::mark_generated(
			$attachment,
			array(
				'source'   => 'auto',
				'provider' => 'openai',
			)
		);
		$this->assertSame( 'AI', PostList::source_label( $attachment ) );

		Generator::mark_generated( $attachment, array( 'source' => 'unsplash' ) );
		$this->assertSame( 'Stock', PostList::source_label( $attachment ) );
	}

	public function test_column_shows_the_generated_badge() {
		$this->create_template();
		$post_id = $this->post();
		PostList::regenerate( $post_id );

		ob_start();
		( new PostList() )->render_column( PostList::COLUMN, $post_id );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'aimg-col__thumb', $html );
		$this->assertStringContainsString( 'Template', $html );
	}

	public function test_row_action_label() {
		$this->create_template();
		$post_id = $this->post();
		$list    = new PostList();

		$this->assertStringContainsString( 'Generate image', $list->row_actions( array(), get_post( $post_id ) )[ PostList::ACTION ] );

		PostList::regenerate( $post_id );
		$this->assertStringContainsString( 'Regenerate image', $list->row_actions( array(), get_post( $post_id ) )[ PostList::ACTION ] );
	}

	public function test_regenerate_replaces_the_featured_image_with_a_template() {
		$this->create_template();
		$post_id = $this->post();
		$old     = $this->create_png_attachment();
		set_post_thumbnail( $post_id, $old );
		update_post_meta( $post_id, '_aimg_disable_auto', '1' );

		$this->assertSame( 'done', PostList::regenerate( $post_id ) );

		$new = get_post_thumbnail_id( $post_id );
		$this->assertNotSame( $old, $new );
		$this->assertSame( '1', get_post_meta( $new, Generator::GENERATED_META, true ) );
		$this->assertNotNull( get_post( $old ), 'The old image stays in the Media Library.' );
		$this->assertSame( '', get_post_meta( $post_id, '_aimg_disable_auto', true ) );
	}

	public function test_regenerate_queues_ai_images() {
		$this->set_settings(
			array(
				'api_key'           => 'sk-test',
				'generation_method' => 'ai',
			)
		);
		$post_id = $this->post();

		$this->assertSame( 'queued', PostList::regenerate( $post_id ) );
		$this->assertSame( 'queued', Queue::get_status( $post_id )['status'] );
		$this->assertWPError( PostList::regenerate( $post_id ), 'A pending job is not queued twice.' );
	}

	public function test_regenerate_without_a_runnable_method() {
		$this->set_settings( array( 'generation_method' => 'ai' ) );

		$this->assertWPError( PostList::regenerate( $this->post() ) );
	}

	public function test_bulk_action_starts_a_run_for_the_selected_posts() {
		$this->create_template();
		$mine   = $this->post();
		$other  = self::factory()->post->create( array( 'post_author' => $this->admin_id ) );
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$theirs = self::factory()->post->create( array( 'post_author' => $author ) );
		$this->post();
		set_current_screen( 'edit-post' );

		$redirect = ( new PostList() )->handle_bulk_action( 'edit.php', PostList::BULK_ACTION, array( $mine, $other ) );

		$this->assertStringContainsString( 'page=aimg-bulk', $redirect );
		$state = Runner::state();
		$this->assertSame( 2, $state['total'] );
		$this->assertTrue( $state['args']['replace'] );

		Runner::control( 'cancel' );
		Runner::control( 'clear' );

		wp_set_current_user( $author );
		( new PostList() )->handle_bulk_action( 'edit.php', PostList::BULK_ACTION, array( $theirs, $mine ) );
		$this->assertSame( 1, Runner::state()['total'], 'Posts the user cannot edit are left out.' );
	}

	public function test_other_bulk_actions_are_ignored() {
		$this->assertSame( 'edit.php', ( new PostList() )->handle_bulk_action( 'edit.php', 'trash', array( 1 ) ) );
	}
}
