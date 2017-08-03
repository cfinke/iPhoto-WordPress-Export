iPhoto-WordPress-Export
==================

Export your iPhoto library as a WordPress website.

Usage
=====

Run it like this:

`$ ./iphoto2wordpress.php --library=/path/to/photo/library --wordpress=https://www.example.com/`

The script will upload all photos in the library to the given WordPress site and for each event, it will create a post containing a gallery of the photos in the event.

It converts any regular albums into categories, categorizing the photos themselves. (For this, you will want to enable categories for attachments using `add-categories-to-attachments.php` in `recommended-mu-plugins/`.)

It treats Faces as tags, tagging the photos with the names of the pictured people. (For this, you will want to enable tags for attachments using `add-tags-to-attachments.php` in `recommended-mu-plugins/`.)

You will need to have the Basic Authentication plugin installed on the WordPress site: https://github.com/WP-API/Basic-Auth

You will need pretty permalinks enabled so that the REST API endpoints work: https://codex.wordpress.org/Using_Permalinks

If the script stops for any reason, you can restart it, and it will pick up where it left off. Depending on what it was doing when it stopped, you may have an orphaned attachment in your Media.

Posts are created as drafts and left for you to publish at your leisure.

Misc
====

The libraries `photolibrary` and `CFPropertyList` are included for convenience but the canonical repositories can be found at https://github.com/cfinke/photolibrary and https://github.com/rodneyrehm/CFPropertyList respectively.
