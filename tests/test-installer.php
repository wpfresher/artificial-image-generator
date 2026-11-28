<?php
/**
 * Starter template on a fresh install.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Templates\Installer;
use ArtificialImageGenerator\Templates\Repository;

/**
 * @covers \ArtificialImageGenerator\Templates\Installer
 */
class Test_Installer extends AIMG_TestCase {

	/**
	 * Published templates.
	 *
	 * @return int[]
	 */
	private function templates() {
		return get_posts(
			array(
				'post_type'      => 'aimg_template',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
	}

	public function test_a_fresh_install_gets_one_published_starter_once() {
		Installer::activated( true );

		$id = Installer::maybe_create_starter();

		$this->assertGreaterThan( 0, $id );
		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertTrue( Repository::has_document( $id ) );
		$this->assertFalse( get_option( Installer::OPTION ) );

		$this->assertSame( 0, Installer::maybe_create_starter(), 'It runs only once.' );
		$this->assertCount( 1, $this->templates() );
	}

	public function test_reactivation_on_a_site_that_ran_the_plugin_adds_nothing() {
		Installer::activated( false );

		$this->assertSame( 0, Installer::maybe_create_starter() );
		$this->assertCount( 0, $this->templates() );
	}

	public function test_a_site_with_templates_keeps_them() {
		$existing = self::factory()->post->create(
			array(
				'post_type'   => 'aimg_template',
				'post_status' => 'draft',
			)
		);
		Installer::activated( true );

		$this->assertSame( 0, Installer::maybe_create_starter() );
		$this->assertSame( array( $existing ), $this->templates() );
		$this->assertFalse( get_option( Installer::OPTION ), 'The pending flag is cleared anyway.' );
	}

	public function test_the_starter_can_be_turned_off() {
		add_filter( 'aimg_install_starter', '__return_false' );
		Installer::activated( true );

		$this->assertSame( 0, Installer::maybe_create_starter() );
		$this->assertCount( 0, $this->templates() );
	}

	public function test_a_new_post_gets_an_image_with_no_setup() {
		Installer::activated( true );
		Installer::maybe_create_starter();

		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Hello from a fresh install',
				'post_status' => 'publish',
			)
		);

		$this->assertTrue( has_post_thumbnail( $post_id ) );
	}
}
