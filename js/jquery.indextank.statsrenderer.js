(function($){
    if(!$.Indextank){
        $.Indextank = new Object();
    };
    
    $.Indextank.StatsRenderer = function(el, options){
        // To avoid scope issues, use 'base' instead of 'this'
        // to reference this class from internal events and functions.
        var base = this;
        
        // Access to jQuery and DOM versions of element
        base.$el = $(el);
        base.el = el;
        
        // Add a reverse reference to the DOM object
        base.$el.data("Indextank.StatsRenderer", base);
        
        base.init = function(){
            base.options = $.extend({},$.Indextank.StatsRenderer.defaultOptions, options);


            base.$el.bind( "Indextank.AjaxSearch.success", function (event, data) {
                base.$el.show();

                var stats = base.options.format(data);

                var poweredBy = $("<a/>").attr("href", "http://indextank.com/?utm_campaign=poweredby&utm_source=wordpress-plugin").css({'padding-left': '10px'})
                                    .append( $("<img/>").attr("src", "http://indextank.com/_static/images/poweredby/it-powered-by-small.png"));

                stats.append(poweredBy);
               
                base.$el.children().replaceWith(stats);
            });
        };
        
        
        // Run initializer
        base.init();
    };
    
    $.Indextank.StatsRenderer.defaultOptions = {
        format: function (data) {
            var r = $("<div></div>")
                        .append( $("<strong></strong>").text(data.matches) )
                        .append( $("<span></span>").text(" " + (data.matches == 1 ? "result":"results" )+ " for ") )
                        .append( $("<strong></strong>").text(data.query.queryString) )
                        .append( $("<span></span>").text(" in ") )
                        .append( $("<strong></strong>").text(data.search_time) )
                        .append( $("<span></span>").text(" seconds.") );

            return r;
        }
    };
    
    $.fn.indextank_StatsRenderer = function(options){
        return this.each(function(){
            (new $.Indextank.StatsRenderer(this, options));
        });
    };
    
})(jQuery);
