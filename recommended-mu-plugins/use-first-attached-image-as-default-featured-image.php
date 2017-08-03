<?php

/**
 * If WordPress is looking for the post thumbnail for a post that has no thumbnail, use the first attached image.
 */
function use_first_attached_image_as_default_post_thumbnail( $unused, $object_id, $meta_key, $single ) {
	if ( '_thumbnail_id' !== $meta_key ) {
		return $unused;
	}

	if ( $unused ) {
		return $unused;
	}
	
	if ( get_post_type( $object_id ) === 'post' ) {
		// Find it.
		$images = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_parent'    => $object_id,
				'posts_per_page' => 1,
			)
		);

		if ( ! empty( $images ) ) {
			return $images[0]->ID;
		}
	}

	return $unused;
}

add_filter( 'get_post_metadata', 'use_first_attached_image_as_default_post_thumbnail', 99, 4 );