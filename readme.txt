=== Document Manager ===
Contributors: draca
Donate link: http://action-a-day.com/aad-document-manager
Tags: csv, viewer, document, manager
Requires at least: 4.2
Tested up to: 4.8
Stable tag: trunk
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Custom post type to manage and display uploaded documents.

== Description ==

Custom post type to manage and display uploaded documents. A Shortcode is available to display CSV format documents as inline tables. The plugin allows documents to be updated with an invariant post_id so each new upload does not require referring locations to be changed.

=Usage=
[docmgr-csv-table id=<post_id> {date=0|1} {row-number=0|1} {row-colors="color, ..."} {page-length=#}]
	id => document id
	date => boolean, 1 ==> display document create/update date, whichever is newer in table caption
	row-number => boolean, 1 ==> Include row numbers
	row-colors => string, comma separated list of row color for each n-rows.
    page-length => integer, number of rows to display in table by default
	rows => string, list of rows to include in resulting table. e.g. "1-10,23,100-199"

TODO Add method to disable sorting

[docmgr-created id=<post_id>]
	Displays created date for document

[docmgr-modified id=<post_id>]
	Displays modified date for document

[docmgr-download-url id=<post_id>]
    Displays a download link for the document

== Installation ==

1. Upload `aad-doc-manager` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= No Questions Yet =

Ask a question!

== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from
the /assets directory or the directory that contains the stable readme.txt (tags or trunk). Screenshots in the /assets
directory take precedence. For example, `/assets/screenshot-1.png` would win over `/tags/4.3/screenshot-1.png`
(or jpg, jpeg, gif).
2. This is the second screen shot

== Changelog ==

= 0.9.3 =
* FIX: Document manager installing in the wrong directory when using composer

= 0.9.1 =
* FIX: Difficult to edit pages that include large CSV tables
* Add composer.json

= 0.9 =
* Add rows option to docmgr-csv-table shortcode.

= 0.8 =
* Update to Datatables 1.10.16
* Update to mark.js 8.9.1
* Fix: 403 errors when trying to use cdn.datatables.net by making local copies

= 0.7 =
* Enhance: Document Updates only allow same mime type to be uploaded

= 0.6.2 =
* FIX: Sorting by mime type does not work; must be removed as it is not supported by WP_Query

= 0.6.1 =
* FIX: Table sorting not working

= 0.6 =
* Add download URL to Admin screen Document list
* Enable Document Manager URLs to be used for WooCommerce downloadable products

= 0.5.1=
* FIX: Download count column in admin screen is too wide

= 0.5 =
* FIX: Highlight when searching a table is not reliably displaying

= 0.4.4 =
* FIX: Unclear error when uploading files that exceed configured maximum
* Security: Only allow download of a file if it's inside the upload area of WP.

= 0.4.3 =
* FIX: PDF files downloading as text/html mime type

= 0.4.2 =
* Add page-length option to docmgr-csv-table shortcode

= 0.4.1 =
* FIX: Failing to handle uploaded media correctly

= 0.4 =
* Support PDF uploads
* Add shortcode [docmgr-download-url] to provide a URL for downloading documents based on a unique UUID

= 0.3.1 =
* Store pre-rendered CSV table as post meta data

= 0.3 =
* Update to DataTable v1.10.12
* CSV rows with fewer columns generate DataTable error
* Highlight Table Search terms in table
* Deprecate [csvview] shortcode, replace with [docmgr-csv-table]
* Add [docmgr-modified] and [docmgr-created] shortcodes
* Display dates using format from WordPress General Settings
* Add row-number and row-colors options to [docmgr-csv-table]

= 0.2 =
* Include DataTable v1.10.7 to make table searchable and more responsive

== Future ==

* RFE Allow multiple document types to be managed
* RFE Allow classes of users to download a file
* RFE Organize documents in folders or via tags
* RFE Connect with media selector to provide correct URL to files
* RFE Delete from Media folder should completely deleted the related document entry
* RFE Filter list by document type
* RFE Sort on mime type using filter 'posts_orderby'
* TODO Address hard coded plugin name references in directory structure