iPhoto-WordPress-Export
==================

Export your iPhoto library as a WordPress website.

Usage
=====

Run it like this:

`$ ./iphoto2wordpress.php --library=/path/to/photo/library --wordpress=https://www.example.com/`

The script will upload all photos in the library to the given WordPress site and for each event, it will create a post containing a gallery of the photos in the event. It will also convert any regular albums into categories, categorizing the photos themselves. (For this, you will want to enable categories for attachments.)
 https://code.tutsplus.com/articles/applying-categories-tags-and-custom-taxonomies-to-media-attachments--wp-32319

I've noticed that the iPhoto Library you're exporting must be the last one you opened in iPhoto (if you have multiple libraries); I think this is a bug in one of the libraries this software uses, but I haven't taken the time to figure that out.

You will need to have the Basic Authentication plugin installed on the WordPress site: https://github.com/WP-API/Basic-Auth

You will need pretty permalinks enabled so that the REST API endpoints work: https://codex.wordpress.org/Using_Permalinks

If the script stops for any reason, you can restart it, and it will pick up where it left off. Depending on what it was doing when it stopped, you may have an orphaned attachment in your Media.

Posts are created as drafts and left for you to publish at your leisure.

Misc
====

The libraries `photolibrary` and `CFPropertyList` are included for convenience but the canonical repositories can be found at https://github.com/cfinke/photolibrary and https://github.com/rodneyrehm/CFPropertyList respectively.
