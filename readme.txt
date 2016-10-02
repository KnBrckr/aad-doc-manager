=== Document Manager ===
Contributors: draca
Donate link: http://action-a-day.com/aad-document-manager
Tags: csv, viewer, document, manager
Requires at least: 4.2
Tested up to: 4.5.3
Stable tag: trunk
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Custom post type to manage and display uploaded documents.

== Description ==

Custom post type to manage and display uploaded documents. A Shortcode is available to display CSV format documents as inline tables. The plugin allows documents to be updated with an invariant post_id so each new upload does not require referring locations to be changed.

=Usage=
[docmgr-csv-table id=<post_id> {date=0|1} {row-number=0|1} {row-colors="color, ..."}]
	id => document id
	date => boolean, 1 ==> display document create/update date, whichever is newer in table caption
	row-number => boolean, 1 ==> Include row numbers
	row-colors => string, comma separated list of row color for each n-rows.

[docmgr-created id=<post_id>]
	Displays created date for document
	
[docmgr-modified id=<post_id>]
	Displays modified date for document

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

= 0.4 =
* Support PDF uploads

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

* Allow multiple document types to be managed
