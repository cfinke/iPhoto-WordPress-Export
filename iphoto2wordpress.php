#!/usr/bin/env php
<?php

// This will take a very long time.
set_time_limit(0);

require "lib/CFPropertyList/CFPropertyList.php";
require "lib/PhotoLibrary/Album.php";
require "lib/PhotoLibrary/Library.php";
require "lib/PhotoLibrary/Photo.php";
require "lib/PhotoLibrary/Face.php";

/**
 * Command line options:
 * --library=/path/to/library
 * --wordpress=http://www.yoursite.com/
 */
$cli_options = getopt( "", array( 'library::', 'wordpress::', 'status::', ) );

/** If you don't want to provide username and password at the command line, you can set them here. **/
// $cli_options['username'] = '';
// $cli_options['password'] = '';

if ( empty( $cli_options['library'] ) || empty( $cli_options['wordpress'] ) ) {
	file_put_contents( 'php://stderr', "Usage: ./iphoto2wordpress.php --library=/path/to/photo/library --wordpress=http://www.example.com/\n" );
	die;
}

if ( ! file_exists( $cli_options['library'] ) ) {
	file_put_contents( 'php://stderr', "Error: Library does not exist (" . $cli_options['library'] . ")\n" );
	die;
}

if ( empty( $cli_options['status'] ) ) {
	$cli_options['status'] = 'draft';
}

// Ask for the username and password.
if ( empty( $cli_options['username'] ) ) {
	if (PHP_OS == 'WINNT') {
		echo 'WordPress username: ';
		$cli_options['username'] = trim( stream_get_line( STDIN, 1024, PHP_EOL ) );
	} else {
		$cli_options['username'] = trim( readline( 'WordPress username: ' ) );
	}
}

if ( empty( $cli_options['password'] ) ) {
	if (PHP_OS == 'WINNT') {
		echo 'WordPress password (will be visible): ';
		$cli_options['password'] = trim( stream_get_line( STDIN, 1024, PHP_EOL ) );
	} else {
		$cli_options['password'] = trim( readline( 'WordPress password (will be visible): ' ) );
	}
}

// Ensure the paths ends with a slash.
$cli_options['library'] = rtrim( $cli_options['library'], '/' ) . '/';
$cli_options['wordpress'] = rtrim( $cli_options['wordpress'], '/' ) . '/';

// If the script dies before it finishes, the state is stored so that it can pick up
// where it left off. This will almost definitely happen for any sizeable library.
$state = get_state();

// Load the library.
$library = new \PhotoLibrary\Library( $cli_options['library'] );

// Get all the events and sort them.
echo "Finding events...\n";
$all_events = $library->getAlbumsOfType( 'Event' );
echo "Found " . count( $all_events ) . " events\n";

usort( $all_events, 'sort_events_by_date' );

// Get a list of the existing categories on the WordPress site.
$existing_categories_json = get_categories();
$existing_categories = array();

// Save a map of "category name" => "category ID"
foreach ( $existing_categories_json as $category ) {
	$existing_categories[ $category->name ] = $category->id;
}

// Albums will be converted to categories. Ideally, this would be a conversion of iPhoto keywords => categories,
// but the PhotoLibrary library doesn't allow access to keywords.
$albums = array_merge( $library->getAlbumsOfType( 'Regular' ), $library->getAlbumsOfType( 'Smart' ) );

// Store a list of the categories that each photo should be in.
$photo_categories = array();

foreach ( $albums as $idx => $album ) {
	$album_name = $album->getName();

	// This "album" gets included in the list of Regular albums, but it's not.
	if ( $album->getName() === 'Last Import' ) {
		unset( $albums[ $idx ] );
		continue;
	}
	
	if ( ! isset( $existing_categories[ $album->getName() ] ) ) {
		// If the album does not already exist as a category, create the category.
		$category_id = create_category( $album->getName() );
		$existing_categories[ $album->getName() ] = $category_id;
	}
	else {
		$category_id = $existing_categories[ $album->getName() ];
	}
	
	// Save the map as "iPhoto photo key" => [ array of WordPress category IDs ]
	foreach ( $album->getPhotos() as $photo ) {
		$photo_categories[ $photo->getKey() ][] = $category_id;
	}
}

// Get a list of the existing tags on the WordPress site.
$existing_tags_json = get_tags();

// Save a map of "tag name" => "tag ID"
foreach ( $existing_tags_json as $tag ) {
	$existing_tags[ $tag->name ] = $tag->id;
}

// For each event, create a post. Each post will contain a gallery of photos.
foreach ( $all_events as $event_counter => $event ) {
	echo "Processing event: " . $event->getName() . "...\n";
	
	// First, upload each photo.
	$photos = $event->getPhotos();
	usort( $photos, 'sort_photos_by_date' );

	// Either create a post or get it from the cached state.
	$post = get_or_create_post_from_event( $event );
	
	// Something went wrong. Die and try re-running the script maybe.
	if ( false === $post ) {
		echo "Invalid post response.\n";
		die;
	}
	
	foreach ( $photos as $photo ) {
		// Either upload the photo or get it from the cached state.
		$photo_json = get_or_create_attachment_from_photo( $event, $photo, $post->id );

		if ( false === $photo_json ) {
			echo "Invalid photo json.\n";
			die;
		}

		// Add the photo to the appropriate categories.
		if ( isset( $photo_categories[ $photo->getKey() ] ) ) {
			sort( $photo_categories[ $photo->getKey() ] );
			sort( $photo_json->categories );
			
			if ( $photo_categories[ $photo->getKey() ] != $photo_json->categories ) {
				echo "Categories are " . implode( ",", $photo_json->categories ) . ", setting them to " . implode( ",", $photo_categories[ $photo->getKey() ] ) . "\n";
				echo "Adding categories to photo\n";
				update_photo_attachment( $photo, array( 'categories' => $photo_categories[ $photo->getKey() ] ) );
			}
		}
		
		save_state();
		
		$faces = $photo->getFaces();
		
		if ( ! empty( $faces ) ) {
			echo "Tagging photo with: ";

			$tags_to_add = array();
			
			foreach ( $faces as $face ) {
				$tag_name = $face->getName();
				
				if ( ! isset( $existing_tags[ $tag_name ] ) ) {
					$tag_id = create_tag( $tag_name );
					
					$existing_tags[ $tag_id ] = $tag_name;
					
					$tags_to_add[] = $tag_id;
				}
				else {
					$tags_to_add[] = $existing_tags[ $tag_name ];
				}
				
				echo $tag_name . ", ";
			}
			
			echo "...\n";
			
			update_photo_attachment( $photo, array( 'tags' => $tags_to_add ) );
		}
		
		save_state();
	}
}

// If we got this far, we finished, and the state is no longer needed.
delete_state();

echo "Done.\n";

/**
 * Sort a list of Photo objects by date.
 */
function sort_photos_by_date( $a, $b ) {
	return $a->getDateTime()->format( "U" ) < $b->getDateTime()->format( "U" ) ? -1 : 1;
}

/**
 * The event date is the date of the earliest photo. 
 */
function get_event_date( $event ) {
	$photos = $event->getPhotos();
	
	usort( $photos, 'sort_photos_by_date' );
	
	return $photos[0]->getDateTime()->format( "Y-m-d" );
}

function sort_events_by_date( $a, $b ) {
	return ( get_event_date( $a ) < get_event_date( $b ) ? -1 : 1 );
}

/**
 * Create a post on the WordPress site.
 */
function create_post( $name, $date ) {
	global $cli_options;
	
	echo "Creating post: " . $name . "...\n";

	$data = json_encode( array( 'title' => $name, 'date' => date( 'Y-m-d H:i:s', strtotime( $date ) ), 'content' => '[gallery]', 'status' => $cli_options['status'] ) );
	
	return post_to_wordpress_api( "posts", $data );
}

/**
 * Upload a photo to the site, creating a Media entry.
 */
function upload_photo( $event, $photo, $post_id ) {
	global $cli_options;
	
	$photo_timestamp = $photo->getDateTime()->format( "Y-m-d H:i:s" );
	
	$photo_filename = $photo->getDateTime()->format( "Y-m-d" );
	
	$title = trim( $event->getName() );
	$caption = trim( $photo->getCaption() );
	
	if ( preg_match( '/^[a-z]+_[0-9]+$/i', $caption ) ) {
		$caption = '';
	}

	if ( $caption && $caption != $title ) {
		$title .= ' - ' . $caption;
	}
	
	if ( $title ) {
		$photo_filename .= " - " . str_replace( "/", "-", $title );
	}
	
	$photo_path = $photo->getPath();
	$tmp = explode( ".", $photo_path );
	$photo_extension = array_pop( $tmp );
	$photo_filename .= "." . $photo_extension;

	$curl = curl_init();

	$data = file_get_contents( $photo_path );

	echo "\tUploading " . $photo_filename . " (" . number_format(strlen( $data )) . " bytes)...\n";
	
	curl_setopt_array( $curl, array(
			CURLOPT_URL => $cli_options['wordpress'] . "wp-json/wp/v2/media",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 120,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_HTTPHEADER => array(
				"authorization: Basic " . base64_encode( $cli_options['username'] . ":" . $cli_options['password'] ),
				"cache-control: no-cache",
				"content-disposition: attachment; filename=\"" . $photo_filename . "\"",
				"content-type: image/" . $photo_extension,
		),
		CURLOPT_POSTFIELDS => $data,
	) );

	$response = curl_exec( $curl );
	$err = curl_error( $curl );

	curl_close( $curl );

	if ( $err ) {
		echo "cURL Error #:" . $err;
		die;
	}
	
	$json_response = json_decode( $response );
	
	if ( $json_response === null ) {
		echo "Response not JSON.\n";
		echo $response . "\n";
		die;
	}
	
	if ( isset( $json_response->code ) ) {
		print_r( $json_response );
		die;
	}

	$photo_id = $json_response->id;
	
	return update_photo( $photo_id, array( 'title' => $title, 'description' => $photo->getDescription(), 'post' => $post_id, 'date' => $photo_timestamp, ) );
}

/**
 * Update metadata for an uploaded photo.
 */
function update_photo( $photo_id, $fields ) {
	$data = json_encode( $fields );

	return post_to_wordpress_api( "media/" . $photo_id, $data );
}

/**
 * Update an already-created post.
 */
function update_post( $post_id, $fields ) {
	$data = json_encode( $fields );

	return post_to_wordpress_api( "posts/" . $post_id, $data );
}

/**
 * Get the list of categories for the site.
 */
function get_categories() {
	global $cli_options;
	
	$categories = file_get_contents( $cli_options["wordpress"] . "wp-json/wp/v2/categories" );
	
	$categories_json = json_decode( $categories );
	
	if ( null === $categories_json ) {
		echo "Could not retrieve categories.\n";
		die;
	}
	
	return $categories_json;
}

/**
 * Get the list of tags for the site.
 */
function get_tags() {
	global $cli_options;
	
	$tags = file_get_contents( $cli_options["wordpress"] . "wp-json/wp/v2/tags" );
	
	$tags_json = json_decode( $tags );
	
	if ( null === $tags_json ) {
		echo "Could not retrieve tags.\n";
		die;
	}
	
	return $tags_json;

}

/**
 * Create a category on the WordPress site.
 */
function create_category( $name ) {
	$data = json_encode( array( 'name' => $name ) );

	$response = post_to_wordpress_api( "categories", $data );
	
	return $response->id;
}

/**
 * Create a tag on the WordPress site.
 */
function create_tag( $name ) {
	$data = json_encode( array( 'name' => $name ) );

	$response = post_to_wordpress_api( "tags", $data );
	
	return $response->id;
}

/**
 * Given an event, either get (from the cached state) or create a post for it.
 */ 
function get_or_create_post_from_event( $event ) {
	global $state;
	
	if ( isset( $state['posts_from_events'][ $event->getId() ] ) ) {
		echo "Retrieving cached post.\n";
		return $state['posts_from_events'][ $event->getId() ];
	}
	
	$event_date = get_event_date( $event );
	
	$event_name = $event->getName();
	
	$post = create_post( $event_name, $event_date );
	
	$state['posts_from_events'][ $event->getId() ] = $post;
	save_state();
	
	return $post;
}

/**
 * Update a post from an iPhoto event.
 */
function update_event_post( $event, $fields ) {
	global $state;
	
	$post = $state['posts_from_events'][ $event->getId() ];
	
	$post = update_post( $post->id, $fields );
	
	$state['posts_from_events'][ $event->getId() ] = $post;
	save_state();
	
	return $post;
}

/**
 * Given an iPhoto photo, either get (from the cached state) or create a WordPress media entry for it.
 */ 
function get_or_create_attachment_from_photo( $event, $photo, $post_id ) {
	global $state;
	
	if ( isset( $state['attachments_from_photos'][ $photo->getKey() ] ) ) {
		if ( isset( $state['attachments_from_photos'][ $photo->getKey() ]->id ) ) {
			echo "Retrieving cached attachment.\n";
			return $state['attachments_from_photos'][ $photo->getKey() ];
		}
	}
	
	$attachment = upload_photo( $event, $photo, $post_id );
	
	$state['attachments_from_photos'][ $photo->getKey() ] = $attachment;
	save_state();
	
	return $attachment;
}

/**
 * Given an iPhoto photo, update the corresponding WordPress media item.
 */
function update_photo_attachment( $photo, $fields ) {
	global $state;
	
	$attachment = $state['attachments_from_photos'][ $photo->getKey() ];
	
	$attachment = update_photo( $attachment->id, $fields );
	
	$state['attachments_from_photos'][ $photo->getKey() ] = $attachment;
	save_state();
	
	return $attachment;
}

/**
 * Save the current state.
 */
function save_state() {
	global $state;
	global $cli_options;
	
	$state_key = md5( serialize( $cli_options ) );
	
	file_put_contents( ".iphoto2wordpress-state-" . $state_key, serialize( $state ) );
}

/**
 * Get the saved state.
 */
function get_state() {
	global $cli_options;
	
	$state_key = md5( serialize( $cli_options ) );
	
	$state = @unserialize( file_get_contents( ".iphoto2wordpress-state-" . $state_key ) );
	
	if ( ! $state ) {
		$state = array();
	}
	
	return $state;
}

/**
 * Delete the state (but only when we're done).
 */
function delete_state() {
	global $cli_options;
	
	$state_key = md5( serialize( $cli_options ) );
	
	unlink( ".iphoto2wordpress-state-" . $state_key );
}

/**
 * Make a POST request to the REST API of the WordPress site. For everything except uploading.
 */
function post_to_wordpress_api( $endpoint, $post_data ) {
	global $cli_options;
	
	$curl = curl_init();
	
	curl_setopt_array( $curl, array(
			CURLOPT_URL => $cli_options['wordpress'] . "wp-json/wp/v2/" . $endpoint,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 120,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_HTTPHEADER => array(
				"authorization: Basic " . base64_encode( $cli_options['username'] . ":" . $cli_options['password'] ),
				"cache-control: no-cache",
				"content-type: application/json",
		),
		CURLOPT_POSTFIELDS => $post_data,
	) );

	$response = curl_exec( $curl );
	$err = curl_error( $curl );
	curl_close( $curl );
	
	if ( $err ) {
		echo "cURL Error #:" . $err . "\n";
		die;
	}
	
	$response = json_decode( $response );
	
	if ( $response === null ) {
		echo "Response from " . $endpoint . " not JSON.\n";
		echo $response . "\n";
		die;
	}
	
	if ( isset( $response->code ) ) {
		print_r( $response );
		die;
	}
	
	return $response;
}
