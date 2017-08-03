<?php

function add_tags_to_attachments() {
	register_taxonomy_for_object_type( 'post_tag', 'attachment' );
}

add_action( 'init' , 'add_tags_to_attachments' );