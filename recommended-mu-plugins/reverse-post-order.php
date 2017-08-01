<?php

function reverse_post_order( $query ) {
	if ( $query->is_home() && $query->is_main_query() ) {
		$query->set( 'order', 'asc' );
	}

	return $query;
}

add_action( 'pre_get_posts', 'reverse_post_order' );