=== IndexTank Search ===
Contributors: dbuthay, flaptor
Tags: search, better search, search replacement, autocomplete, ajax, cloud, instantsearch, suggestions, autocorrect
Requires at least: 2.8
Tested up to: 3.2.1
Stable tag: 1.1.5

IndexTank hosted, realtime search (FTW!)

== Description == 

IndexTank brings nice, instant search to your blog, with automatic suggestions of query terms, and super-fast ajax-style search.

= Features = 

* InstantSearch: See results as you type!
* AutoComplete.
* Works with *ALL* WordPress templates 
* Pagination
* No need to write PHP code

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

* **Q**: Do I need to get an account on indextank.com to use this plugin?
  * **A**: Not really. The plugin can get an account for you, from your dashboard.

* **Q**: Is it FREE?
  * **A**: Yes, until you get 100,000 posts it is. If you ever go over that limit (a lot of blogging!) you may have to upgrade.

* **Q**: Are there any knows issues with other plugins?
  * **A**: Not really. This plugin calls `apply_filters('the_content', $post->post_content)`, so if there's a buggy filter you may see errors while indexing. If that happens, please let us know at **support (at) indextank (dot) com**

== Upgrade Notice ==

You'll get a message on your dashboard, in case you need to upgrade your index. In that case, you need to:

* Go to the 'Tools' -> 'Indextank Searching' menu on your dashboard
* Click the 'Reset Index!' button
* Your index will show as 'STARTING' .. wait a few seconds and go again to the 'Tools' -> 'Indextank Searching' menu on your dashboard. **DON'T HIT THE RELOAD BUTTION!**
* Once your index shows as 'RUNNING', hit the 'Index All posts!' button.
* Great, you're upgraded :) 


== Changelog ==

= 1.1.5 =

* "Index all posts!" will now also index your pages, which were previously only indexed when they were saved.

= 1.1.4 =

* Make sure dependencies are met instead of failing silently.

= 1.1.3 = 

* Bugfix: Only halt on E_ERRORs when indexing. 

= 1.1.2 = 

* Add checkbox to toggle applying filters to 'the_content' before indexing.
* blogsearch.$theme.js is not overwriten on plugin upgrade.
* No need to use *'Magic'* when changing the theme, if you have used it before on the target theme.

= 1.1.1 =

* Admin *Index All Posts* button working for MU 2.8
* *Magic* configuration not working on 2.8, as *home_url()* didn't exist back then. Using site_url instead.

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
