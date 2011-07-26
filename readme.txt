=== IndexTank ===
Contributors: dbuthay, flaptor
Tags: search, realtime, relevance, better search, autocomplete, ajax, cloud, hosted, instantsearch
Requires at least: 2.8
Tested up to: 3.2
Stable tag: 1.1

IndexTank hosted, realtime search (FTW!)

== Description == 

IndexTank brings nice, instant search to your blog. 

= Features = 

* No need to write PHP code
* Works with *ALL* WordPress templates 
* InstantSearch
* AutoComplete
* Pagination

== Installation ==

1. Upload `indextank/` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to the 'Tools' -> 'Indextank Searching' menu in WordPress
1. If you have an account, enter your data and hit 'Save'. If not, hit the **'Get one'** button.
1. Hit the **'Index All posts'** button

You are all set, now just query something on your blog sidebar. If the results don't look nice with your template:

1. Go to the 'Tools' -> 'Indextank Searching' menu in WordPress
1. Hit the **'Magic'** button, on the 'Look and Feel' section

== Frequently Asked Questions ==

None so far

== Changelog ==

= 1.1 = 

* Apply filters on content before indexing. Fixes MardDown problems.
* Prefix document ids with home_url(). Allows to have multiple blogs indexed on the same index.
* Index format versioning. New index format changes will alert blog owners
* *Reset index* button


= 1.0.4 = 

* indexing working with templates that don't provide get_post_thumbnail_id() // thanks @dtunkelang
* indextank client upgraded. No dependencies en pecl_http

= 1.0.3 = 

* improved error logging for batch indexing.

= 1.0.2 = 

* wp-it-jq configuration parameters may not work with *site_url*. See **OR** on http://codex.wordpress.org/Function_Reference/site_url. Using *home_url* instead.

= 1.0.1 = 

* Setting scoring functions when provisioning new accounts

= 1.0 =

* Adding AjaxSearch, AutoComplete and Pagination.
* Adding provisioning. The plugin can get an IndexTank account for you.
