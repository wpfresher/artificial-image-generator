<?php
/**
 * PHPUnit bootstrap.
 *
 * @package ArtificialImageGenerator
 */

$aimg_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $aimg_tests_dir ) {
	$aimg_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $aimg_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$aimg_tests_dir}/includes/functions.php. Run bin/install-wp-tests.sh first." . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );
}

require_once $aimg_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () {
		require dirname( __DIR__ ) . '/artificial-image-generator.php';
	}
);

require $aimg_tests_dir . '/includes/bootstrap.php';
require __DIR__ . '/class-aimg-testcase.php';
require __DIR__ . '/class-aimg-test-square-layer.php';
require __DIR__ . '/legacy/legacy-renderer.php';
