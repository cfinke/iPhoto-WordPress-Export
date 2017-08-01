<?php

function add_attachments_to_search_results_and_categories( $query ) {
	if ( ! $query->is_search && ! $query->is_category ) {
		return;
	}

	if ( is_admin() ) {
		return;
	}

	$post_types = $query->get( 'post_type' );

	if ( ! $post_types ) {
		$post_types = array( 'post' );
	}

	if ( ! is_array( $post_types ) ) {
		$post_types = array( $post_types );
	}

	$post_types[] = "attachment";

	$query->set( 'post_type', $post_types );

	$post_status = $query->get( 'post_status' );

	if ( ! $post_status ) {
		$post_status = array( 'publish' );
	}

	if ( ! is_array( $post_status ) ) {
		$post_status = array( $post_status );
	}

	$post_status[] = 'inherit';

	$query->set( 'post_status', $post_status );

	return $query;
}

add_filter( 'pre_get_posts', 'add_attachments_to_search_results_and_categories', 99 );
