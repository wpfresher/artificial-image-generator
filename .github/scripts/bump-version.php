<?php
/**
 * Bump plugin version script for GitHub Actions.
 *
 * This script updates the version number in plugin files:
 * - artificial-image-generator.php (plugin header + Plugin::create() version)
 * - readme.txt (Stable tag)
 * - package.json (version field)
 *
 * @package ArtificialImageGenerator
 */

$plugin_file  = 'artificial-image-generator.php';
$readme_file  = 'readme.txt';
$package_file = 'package.json';

// Pattern to match version in plugin header (Version: x.x.x).
$header_pattern = '/^(\s*\*\s*Version:\s*)(\d+\.\d+\.\d+)/m';

// Pattern for readme.txt stable tag.
$stable_tag_pattern = '/^(Stable tag:\s*)(\d+\.\d+\.\d+)/m';

// Pattern for version passed to Plugin::create().
$plugin_create_pattern = "/(Plugin::create\s*\(\s*__FILE__\s*,\s*['\"])(\d+\.\d+\.\d+)(['\"]\s*\))/";

// Read the main plugin file.
$plugin_content = file_get_contents( $plugin_file );
if ( false === $plugin_content ) {
	echo "Error: Unable to read {$plugin_file}\n";
	exit( 1 );
}

// Extract current version from plugin header.
if ( ! preg_match( $header_pattern, $plugin_content, $matches ) ) {
	echo "Error: Unable to find version in plugin header\n";
	exit( 1 );
}

$current = $matches[2];
echo "Current version: {$current}\n";

// Bump patch version, rolling over to next minor after patch 9.
$parts = explode( '.', $current );
$major = (int) $parts[0];
$minor = (int) $parts[1];
$patch = (int) $parts[2];

if ( $patch >= 9 ) {
	$patch = 0;
	++$minor;
} else {
	++$patch;
}

$new = "{$major}.{$minor}.{$patch}";

echo "New version: {$new}\n";

// Update plugin main file.
$plugin_content = preg_replace( $header_pattern, '${1}' . $new, $plugin_content );
$plugin_content = preg_replace( $plugin_create_pattern, '${1}' . $new . '${3}', $plugin_content );
file_put_contents( $plugin_file, $plugin_content );
echo "✓ Updated {$plugin_file}\n";

// Update readme.txt.
if ( file_exists( $readme_file ) ) {
	$readme_content = file_get_contents( $readme_file );
	$readme_content = preg_replace( $stable_tag_pattern, '${1}' . $new, $readme_content );
	file_put_contents( $readme_file, $readme_content );
	echo "✓ Updated {$readme_file}\n";
}

// Update package.json.
if ( file_exists( $package_file ) ) {
	$package_content = file_get_contents( $package_file );
	$package_data    = json_decode( $package_content, true );

	if ( null !== $package_data && isset( $package_data['version'] ) ) {
		$package_data['version'] = $new;
		$updated_package         = json_encode( $package_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
		file_put_contents( $package_file, $updated_package );
		echo "✓ Updated {$package_file}\n";
	} else {
		echo "⚠ Warning: Could not parse {$package_file}\n";
	}
}

// Output for GitHub Actions.
$output_file = getenv( 'GITHUB_OUTPUT' );
if ( $output_file ) {
	file_put_contents( $output_file, "version={$new}\n", FILE_APPEND );
}

echo "\n✅ Version bumped from {$current} to {$new}\n";
