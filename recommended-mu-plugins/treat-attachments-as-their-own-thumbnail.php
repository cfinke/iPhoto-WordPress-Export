<?php

/**
 * If WordPress is looking for the post thumbnail for an attachment, say it's the attachment itself.
 */
function make_attachment_its_own_thumbnail( $unused, $object_id, $meta_key, $single ) {
	if ( '_thumbnail_id' !== $meta_key ) {
		return $unused;
	}

	if ( get_post_type( $object_id ) === 'attachment' ) {
		return $object_id;
	}

	return $unused;
}

add_filter( 'get_post_metadata', 'make_attachment_its_own_thumbnail', 10, 4 );