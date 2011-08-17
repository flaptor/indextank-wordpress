
    // THIS CODE WAS AUTO GENERATED BY WP-ITJQ-GENERATOR.
    // YOU SHOULD VERIFY THAT THE FOLLOWING ITEMS ARE OK:
    //  - dates
    //  - urls (comment urls ?)
    //  - author names
    //  - image urls

    jQuery(window).load( function() {
      var fmt = function(item) {
        var d = new Date( item.timestamp * 1000);
        var r = 
	jQuery( '<article/>' ).addClass('post').addClass('type-post').addClass('status-publish').addClass('format-standard').addClass('hentry').addClass('category-uncategorized')
		.append(jQuery( '<header/>' ).addClass('entry-header')
			.append(jQuery( '<h1/>' ).addClass('entry-title')
				.append(jQuery( '<a/>' ).attr('href', item.url ).text( item.post_title )
				)
			)
			.append(jQuery( '<div/>' ).addClass('entry-meta')
				.append(jQuery( '<span/>' ).addClass('sep').text('Posted on ')
				)
				.append(jQuery( '<a/>' ).attr('href', item.url )
					.append(jQuery( '<time/>' ).addClass('entry-date').text( d.toLocaleDateString() )
					)
				)
				.append(jQuery( '<span/>' ).addClass('by-author')
					.append(jQuery( '<span/>' ).addClass('sep').text(' by ')
					)
					.append(jQuery( '<span/>' ).addClass('author').addClass('vcard')
						.append(jQuery( '<a/>' ).addClass('url').addClass('fn').addClass('n').attr('href', item.url ).text(item.post_author)
						)
					)
				)
			)
			.append(jQuery( '<div/>' ).addClass('comments-link')
				.append(jQuery( '<a/>' ).attr('href', item.url )
				)
			)
		)
		.append(jQuery( '<div/>' ).addClass('entry-content')
			.append(jQuery( '<p/>' ).html( item.snippet_post_content || item.post_content.substr(0, 200) ).append('...').prepend('...')
			)
		)
		.append(jQuery( '<footer/>' ).addClass('entry-meta')
			.append(jQuery( '<span/>' ).addClass('cat-links')
				.append(jQuery( '<span/>' ).addClass('entry-utility-prep').addClass('entry-utility-prep-cat-links').text('Posted in')
				)
				.append(jQuery( '<a/>' ).attr('href', item.url ).text('Uncategorized')
				)
			)
			.append(jQuery( '<span/>' ).addClass('sep').text(' | ')
			)
			.append(jQuery( '<span/>' ).addClass('comments-link')
				.append(jQuery( '<a/>' ).attr('href', item.url ).html( "Comments" )
				)
			)
		)
	;
        return r;
    };
      

      var setupContainer = function($el) {
        $el.children().not("#stats, #paginator").detach();
      }

      var afterRender = function($el) {
        // move paginator to the bottom
        var p = jQuery("#paginator").detach();
        $el.append(p);
      }

      // create some placeholders
      var stContainer = jQuery("<div/>").attr("id", "stats").hide();
      stContainer.append( jQuery("<span/>") );
      var sortingContainer = jQuery("<div/>").attr("id","sorting").hide();
      var pContainer  = jQuery("<div/>").attr("id", "paginator").hide();
      jQuery("#content").prepend(pContainer).prepend(stContainer).prepend(sortingContainer);


      var rw = function(q) { return 'post_content:(' + q + ') OR post_title:(' + q + ') OR post_author:(' + q + ')';}
      var r = jQuery('#content').indextank_Renderer({format: fmt, setupContainer: setupContainer, afterRender:afterRender});
      //var st = jQuery('#stats').indextank_StatsRenderer();
      var st = stContainer.indextank_StatsRenderer();
      var p = pContainer.indextank_Pagination({maxPages:5});
      var so = sortingContainer.indextank_Sorting({labels: {"relevance":0, "newest":1}});
      jQuery('#s').parents('form').indextank_Ize(INDEXTANK_PUBLIC_URL, INDEXTANK_INDEX_NAME);
      jQuery('#s').indextank_Autocomplete().indextank_AjaxSearch({ listeners: [r,p,st,so], 
                                                                   fields: 'post_title,post_author,timestamp,url,thumbnail,post_content',
                                                                   snippets:'post_content', 
                                                                   rewriteQuery: rw }).indextank_InstantSearch();
    });
    
