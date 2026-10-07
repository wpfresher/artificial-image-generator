<?php

namespace ArtificialImageGenerator\Rendering;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Finding, opening and fitting images for background and image layers.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
class Images {

	/**
	 * Image sources other than picked Media Library images, as source => label.
	 *
	 * @return array
	 */
	public static function sources() {
		return array(
			'media'         => __( 'Media Library', 'artificial-image-generator' ),
			'featured'      => __( 'Post featured image', 'artificial-image-generator' ),
			'first'         => __( 'First image in the post', 'artificial-image-generator' ),
			'site_logo'     => __( 'Site logo', 'artificial-image-generator' ),
			'site_icon'     => __( 'Site icon', 'artificial-image-generator' ),
			'author_avatar' => __( 'Author avatar (uploaded)', 'artificial-image-generator' ),
		);
	}

	/**
	 * Files to draw for a layer: its picked attachments, or a dynamic source.
	 *
	 * @param array $layer   Layer with `attachments`, `pick` and optionally `source`.
	 * @param int   $post_id Post the image is for.
	 *
	 * @return string[]
	 */
	public static function files( $layer, $post_id ) {
		$source = isset( $layer['source'] ) ? $layer['source'] : 'media';
		$ids    = 'media' === $source ? (array) $layer['attachments'] : array_filter( array( self::dynamic( $source, $post_id ) ) );
		$files  = array();

		foreach ( $ids as $id ) {
			$file = get_attached_file( $id );
			if ( $file && file_exists( $file ) ) {
				$files[] = $file;
			}
		}

		if ( count( $files ) > 1 ) {
			$files = array( 'random' === $layer['pick'] ? $files[ array_rand( $files ) ] : $files[0] );
		}

		return $files;
	}

	/**
	 * Attachment ID of a dynamic source.
	 *
	 * Author avatars are only used when uploaded to this site: rendering never
	 * fetches images from other servers.
	 *
	 * @param string $source  Source.
	 * @param int    $post_id Post ID.
	 *
	 * @return int
	 */
	public static function dynamic( $source, $post_id ) {
		$post = $post_id ? get_post( $post_id ) : null;

		switch ( $source ) {
			case 'featured':
				return $post ? (int) get_post_thumbnail_id( $post ) : 0;

			case 'first':
				if ( $post && preg_match( '/wp-image-(\d+)/', $post->post_content, $match ) ) {
					return wp_attachment_is_image( (int) $match[1] ) ? (int) $match[1] : 0;
				}
				return 0;

			case 'site_logo':
				return (int) get_theme_mod( 'custom_logo' );

			case 'site_icon':
				return (int) get_option( 'site_icon' );

			case 'author_avatar':
				if ( ! $post ) {
					return 0;
				}

				$local = get_user_meta( $post->post_author, 'simple_local_avatar', true );
				$id    = is_array( $local ) && ! empty( $local['media_id'] ) ? (int) $local['media_id'] : (int) get_user_meta( $post->post_author, 'wp_user_avatar', true );

				/**
				 * Filter the attachment used as a post author's avatar in templates.
				 *
				 * @param int $attachment_id Attachment ID, or 0 for none.
				 * @param int $user_id       Author's user ID.
				 *
				 * @since 1.7.0
				 */
				$id = (int) apply_filters( 'aimg_author_avatar_attachment', $id, (int) $post->post_author );

				return wp_attachment_is_image( $id ) ? $id : 0;
		}

		return 0;
	}

	/**
	 * Open a PNG, JPEG or WebP file.
	 *
	 * @param string $file Path.
	 *
	 * @return \GdImage|resource|false
	 */
	public static function load( $file ) {
		// A corrupt file makes the imagecreatefrom*() call fail; never let that break a render.
		$info = file_exists( $file ) ? @getimagesize( $file ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! $info ) {
			return false;
		}

		switch ( $info[2] ) {
			case IMAGETYPE_PNG:
				$image = @imagecreatefrompng( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				break;
			case IMAGETYPE_JPEG:
				$image = @imagecreatefromjpeg( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				break;
			case IMAGETYPE_WEBP:
				$image = function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $file ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				break;
			default:
				$image = false;
		}

		if ( $image ) {
			imagesavealpha( $image, true );
		}

		return $image;
	}

	/**
	 * Scale an image into a box.
	 *
	 * @param \GdImage|resource $source Image.
	 * @param int               $width  Box width.
	 * @param int               $height Box height.
	 * @param string            $fit    contain, cover, fill or tile.
	 * @param string            $anchor Position for contain (see Layers\Image::anchors()).
	 * @param array             $focal  Point kept in view for cover, as { x, y } 0–1.
	 *
	 * @return array { @type \GdImage|resource $image, @type float $x, @type float $y } Position relative to the box.
	 */
	public static function place( $source, $width, $height, $fit, $anchor = 'center-center', $focal = null ) {
		$sw   = imagesx( $source );
		$sh   = imagesy( $source );
		$crop = array( 0, 0, $sw, $sh );

		if ( 'tile' === $fit ) {
			$image = Paint::transparent( $width, $height );
			imagesettile( $image, $source );
			imagefilledrectangle( $image, 0, 0, $width - 1, $height - 1, IMG_COLOR_TILED );

			return array(
				'image' => $image,
				'x'     => 0,
				'y'     => 0,
			);
		}

		if ( 'fill' === $fit ) {
			$w = $width;
			$h = $height;
		} elseif ( 'cover' === $fit ) {
			$w     = $width;
			$h     = $height;
			$scale = max( $w / $sw, $h / $sh );
			$cw    = max( 1, min( $sw, (int) round( $w / $scale ) ) );
			$ch    = max( 1, min( $sh, (int) round( $h / $scale ) ) );
			$fx    = isset( $focal['x'] ) ? (float) $focal['x'] : 0.5;
			$fy    = isset( $focal['y'] ) ? (float) $focal['y'] : 0.5;
			$crop  = array(
				(int) max( 0, min( $sw - $cw, round( $fx * $sw - $cw / 2 ) ) ),
				(int) max( 0, min( $sh - $ch, round( $fy * $sh - $ch / 2 ) ) ),
				$cw,
				$ch,
			);
		} else {
			$scale = min( $width / $sw, $height / $sh );
			$w     = (int) ( $sw * $scale );
			$h     = (int) ( $sh * $scale );
		}

		$image = imagecreatetruecolor( max( 1, $w ), max( 1, $h ) );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		imagecopyresampled( $image, $source, 0, 0, $crop[0], $crop[1], $w, $h, $crop[2], $crop[3] );

		list( $x, $y ) = self::anchor( $anchor, $width, $height, $w, $h );

		return array(
			'image' => $image,
			'x'     => $x,
			'y'     => $y,
		);
	}

	/**
	 * Offset of a `$w` × `$h` image anchored in a `$width` × `$height` box.
	 *
	 * @param string $anchor Anchor.
	 * @param int    $width  Box width.
	 * @param int    $height Box height.
	 * @param int    $w      Image width.
	 * @param int    $h      Image height.
	 *
	 * @return float[]
	 */
	private static function anchor( $anchor, $width, $height, $w, $h ) {
		$center = ( $width - $w ) / 2;
		$right  = $width - $w;
		$middle = ( $height - $h ) / 2;
		$bottom = $height - $h;

		switch ( $anchor ) {
			case 'top-left':
				return array( 0, 0 );
			case 'top-center':
				return array( $center, 0 );
			case 'top-right':
				return array( $right, 0 );
			case 'left-center':
				return array( 0, $middle );
			case 'center-center':
				return array( $center, $middle );
			case 'right-center':
				return array( $right, $middle );
			case 'bottom-left':
				return array( 0, $bottom );
			case 'bottom-center':
				return array( $center, $bottom );
			default:
				return array( $right, $bottom );
		}
	}
}
