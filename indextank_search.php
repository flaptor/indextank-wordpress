<?php

/**
 * @package Indextank Search
 * @author Diego Buthay
 * @version 0.6
 */
/*
   Plugin Name: IndexTank Search
   Plugin URI: http://github.com/flaptor/indextank-wordpress/
   Description: IndexTank makes search easy, scalable, reliable .. and makes you happy :)
   Author: Diego Buthay
   Version: 0.6
   Author URI: http://twitter.com/dbuthay
 */


require_once("indextank_client.php");

// Snippet cache. Populated during indextank_section_ids and queried 
// on indextank_the_excerpt
$indextank_snippet_cache = array();
// total matches count for query
$indextank_results = 0;
// total search time for query
$indextank_search_time = 0;
$indextank_sorted_ids = array();


/**
 * Rewrites the query, so relevance works better.
 */ 
function indextank_rewrite_query($query){
    $query_parts = array();

    // full post, once
    // snippets won't work without the line below.
    $query_parts[]= sprintf("(%s)", $query);

    // post_content, once
    $query_parts[]= sprintf("post_content:(%s)", $query);

    // post title, 5 times
    for ($i = 0; $i < 5; $i++)
        $query_parts[]= sprintf("post_title:(%s)", $query);
    
    // post author, 3 times
    for ($i = 0; $i < 3; $i++)
        $query_parts[]= sprintf("post_author:(%s)", $query);

    // put everything together
    return implode($query_parts, " OR ");

}

function indextank_search_ids($wp_query){
    global $indextank_snippet_cache;
    global $indextank_results;
    global $indextank_search_time;
    global $indextank_sorted_ids;
    if ($wp_query->is_search){
        // clear query	
        $wp_query->query_vars['it_s'] = stripslashes($wp_query->query_vars['s']);
        $wp_query->query_vars['s'] = '';
        $wp_query->query_vars['post__in'] = array(-234234232431); // this number does not exist on the db

        $api_url = get_option("it_api_url");
        $index_name = get_option("it_index_name");

        $client = new ApiClient($api_url); 
        $index = $client->get_index($index_name);

        try {
            $scoring_function = 0;
            if (isset($wp_query->query_vars['orderby']))
            {
                // this requires having 1 scoring function defined for $index.
                // 0 -> age is the default, provided by indextank
                // 1 -> should be d[0]
                // 2 -> should be (relevance+0.05)*(log(max(1, d[0]))-age/1000000)
                // $index->list_functions() can be used to verify this.
                switch($wp_query->query_vars['orderby']){
                    case "age" : 
                    case "time" : $scoring_function = 0; break;
                    case "comments" : $scoring_function = 1; break;
                    case "relevance" : $scoring_function = 2; break;
                }
            }
            $rs = $index->search(indextank_rewrite_query($wp_query->query_vars['it_s']), 0, 10, $scoring_function, "text");

            $ids = array_map(
                    create_function('$doc','return $doc->docid;'),
                    array_values($rs->results)
                    );

            if ($ids) { 
                $wp_query->query_vars['post__in'] = $ids;
                $wp_query->query_vars['post_type'] = "any";
                $indextank_results = $rs->matches;
                $indextank_search_time = $rs->search_time;
                $indextank_sorted_ids = $ids;

                foreach ($rs->results as $doc){
                    $indextank_snippet_cache[$doc->docid] = $doc->snippet_text;
                }
            }

        } catch (InvalidQuery $e) {
            //FIXME 
            //echo "The syntax of the query provided is invalid.";
        } catch (Exception $e) {
            //echo "could not perform the requested query.";
        } 
    }
}

add_action("pre_get_posts","indextank_search_ids");


function indextank_sort_results($posts, $query=NULL){
    global $indextank_sorted_ids;

    // only rewrite search queries
    if ($query && $query->is_search) { 
        $map = array();
        $sorted = array();
        if (sizeof($indextank_sorted_ids) == 0) {
            return $posts;
        }

        // ok, there were posts .. sort them
        // first, cache them
        foreach ($posts as $post) {
            $map[$post->ID] = $post;
        }

        // then, append them to "sorted", in the order
        // they appear on indextank_sorted_ids.
        foreach ($indextank_sorted_ids as $id) {
            $sorted[] = $map[$id];
        }

        return $sorted;
    }

    // default action
    return $posts;
}


add_action("posts_results","indextank_sort_results", 10, 2 );


// HACKY. We use this to make sure the search input boxes get populated, and the templates
// can show queries 
function indextank_restore_query($ignored_parameter){
    global $wp_query;
    if ($wp_query->is_search){
        if ($wp_query->query_vars['s'] == '' and isset($wp_query->query_vars['it_s'])) {
            $wp_query->query_vars['s'] = $wp_query->query_vars['it_s'];
        }
    }
}
add_action("posts_selection","indextank_restore_query");


function indextank_the_excerpt($post_excerpt) {
    global $indextank_snippet_cache, $post, $wp_query, $indextank_sorted_ids;

    if ($wp_query->is_search and in_array($post->ID, $indextank_sorted_ids)) {
        if (isset($indextank_snippet_cache[strval($post->ID)]) and
            !empty($indextank_snippet_cache[strval($post->ID)])){

            return " .. " . $indextank_snippet_cache[strval($post->ID)] . " .. ";
        }

    }
    // return default value
    return $post_excerpt;
}

add_filter("the_excerpt","indextank_the_excerpt", 100);
add_filter("the_content","indextank_the_excerpt", 100);



function indextank_add_post($post_ID){
    $api_url = get_option("it_api_url");
    $index_name = get_option("it_index_name");
    if ($api_url and $index_name) { 
        $client = new ApiClient($api_url);
        $index = $client->get_index($index_name);
        $post = get_post($post_ID);
        indextank_add_post_raw($index,$post);
    }	
}  
add_action("save_post","indextank_add_post");


// add the post, without HTML tags and with entities decoded.
// we want the post content verbatim.
function indextank_add_post_raw($index,$post) {
    $content = array();
    $userdata = get_userdata($post->post_author);
    $content['post_author'] = sprintf("%s %s %s", $userdata->user_login, $userdata->first_name, $userdata->last_name);
    $content['post_content'] = html_entity_decode(strip_tags($post->post_content), ENT_COMPAT, "UTF-8"); 
    $content['post_title'] = $post->post_title;
    $content['timestamp'] = strtotime($post->post_date_gmt);
    $content['text'] = html_entity_decode(strip_tags($post->post_title . " " . $post->post_content . " " . $content['post_author']), ENT_COMPAT, "UTF-8"); # everything together here
        if ($post->post_status == "publish") { 
            $vars = array("0" => $post->comment_count);
            $res = $index->add_document($post->ID,$content, $vars); 
            indextank_boost_post($post->ID);
        }
}


function indextank_delete_post($post_ID){
    $api_url = get_option("it_api_url");
    $index_name = get_option("it_index_name");
    if ($api_url and $index_name) { 
        $client = new ApiClient($api_url);
        $index = $client->get_index($index_name);
        $status = $index->delete_document($post_ID);
        //echo "could not delete $post_ID on indextank.";
    } 
}
add_action("delete_post","indextank_delete_post");
add_action("trash_post","indextank_delete_post");

function indextank_boost_post($post_ID){
    $api_url = get_option("it_api_url");
    $index_name = get_option("it_index_name");
    if ($api_url and $index_name) {
        $client = new ApiClient($api_url); 
        $index = $client->get_index($index_name);
        $queries = get_post_custom_values("indextank_boosted_queries",$post_ID);
        if ($queries) {
            //$queries = implode(" " , array_values($queries));
            foreach($queries as $query) {
                if (!empty($query)) {
                    $res = $index->promote($post_ID,$query);
                    //if ($res->status != 'OK') {
                    //    echo "<b style='color:red'>Could not boost $post_ID for query $query on indextank .. " . $status['status'] . $status['message'] ." </b><br>";
                    //}
                }
            }

        }
    }
}


/**
 * Index all posts on one call. It does not work on large blogs, but we keep it as a fallback
 * for installations without ajax.
 * 
 * known drawbacks:
 * 1. timeouts with lots of posts
 * 2. post count is wrong
 * 
 *
 * @deprecated
 */
function indextank_index_all_posts(){
    $api_url = get_option("it_api_url");
    $index_name = get_option("it_index_name");
    if ($api_url and $index_name) { 
        $client = new ApiClient($api_url);
        $index = $client->get_index($index_name);
        $max_execution_time = ini_get('max_execution_time');
        $max_input_time = ini_get('max_input_time');
        ini_set('max_execution_time', 0);
        ini_set('max_input_time', 0);
        $pagesize = 100;
        $offset = 1;
        $t1 = microtime(true);
        $my_query = new WP_Query();
        $query_res = $my_query->query("post_status=publish&orderby=ID&order=DESC&posts_per_page=1");
        if ($query_res) {
            $count = 0;
            $last_post = $query_res[0];
            $last_id = $last_post->ID;
            for ($id = $last_id; $id > 0; $id--) {
                $post = get_post($id);
                indextank_add_post_raw($index,$post);
                $count += 1;
            }
            $t2 = microtime(true);
            $time = round($t2-$t1,3);
            ?>
                <script>
                function showMessage() {
                    document.getElementById('indexall_message').innerHTML='<b>Indexed <?=$count?> posts in <?=$time?> seconds</b>';
                }

            if (window.attachEvent) {window.attachEvent('onload', showMessage);}
            else if (window.addEventListener) {window.addEventListener('load', showMessage, false);}
            else {document.addEventListener('load', showMessage, false);}

            </script>
                <?php
        }
        ini_set('max_execution_time', $max_execution_time);
        ini_set('max_input_time', $max_input_time);
    }
}


/**
  * Incremental version of indextank_index_all_posts. It is intended to be used by
  * the ajax interface.
  * 
  * @param $offset: offset of first post to be indexed.
  * @param $pagesize: number of posts to index per iteration.
  */
function indextank_index_posts($offset=0, $pagesize=30){
    $api_url = get_option("it_api_url");
    $index_name = get_option("it_index_name");
    if ($api_url and $index_name) { 
        $client = new ApiClient($api_url);
        $index = $client->get_index($index_name);
        $max_execution_time = ini_get('max_execution_time');
        $max_input_time = ini_get('max_input_time');
        ini_set('max_execution_time', 0);
        ini_set('max_input_time', 0);
        $t1 = microtime(true);
        $my_query = new WP_Query();
        $query_res = $my_query->query("post_status=publish&orderby=ID&order=DESC&posts_per_page=$pagesize&offset=$offset");
        if ($query_res) {
            $count = 0;
            foreach ($query_res as $post) {
                try { 
                    indextank_add_post_raw($index,$post);
                    $count += 1;
                } catch (Exception $e) {
                    // skip
                }
            }
            $t2 = microtime(true);
            $time = round($t2-$t1,3);
            // count all posts, even from previous iterations
            $count = $offset + $count;
            // time is counted only for this iteration. sorry.
            $message = "<b>Indexed $count posts in $time seconds</b>";
        }
        ini_set('max_execution_time', $max_execution_time);
        ini_set('max_input_time', $max_input_time);
        return $message;
    }

    return NULL;

}

// TODO allow to delete the index.
// TODO allow to create an index.

/** 
 * Renders the "sort" links. It basically rewrites the query, but changes the "orderby" parameter.
 * 
 * NOTE: 
 *     If you intend to use this, make sure that you have the function '1' defined, and it is
 *     'd[0]'.
 *     If you don't have it, indextank will use the post age, no matter what
 *     If you have another definition for function 1, that will be used (overriding comments count).
 *     You can check it on http://indextank.com/dashboard/.
 * 
 * @param separator. A string to use for sort field separator. Defaults  ','
 */
function the_indextank_sort_links($separator=','){
    global $wp_query;

    // render sort -> age
    ?>
    sort by <a href="/?s=<?php echo get_search_query();?>&orderby=age">time</a><?php   echo $separator; ?>
    <?php
    // render sort -> relevance
    ?>
    <a href="/?s=<?php echo get_search_query();?>&orderby=relevance">relevance</a><?php   echo $separator; ?> 
    <?php
    // render sort -> comments
    ?>
    <a href="/?s=<?php echo get_search_query();?>&orderby=comments">comments</a> 
    <?php
}

/**
 * Renders the query stats. How many results (out of how many total results), and how long it took.
 * @param: $logo: A boolean indicating whether to display the indextank logo, or not. Default = true.
 * @param: $time_format: a format to use on sprintf when printing query time. Default = "%.2f"
 * @param: $strip_leading_zero. A boolean indicating whether to show the leading 0 when queries take less than 1 second. Default = true
 */
function the_indextank_query_stats($logo=true, $time_format="%.2f", $strip_leading_zero=true){
    global $indextank_sorted_ids;
    global $indextank_results;
    global $indextank_search_time;

    $pluralized_results = ( $indextank_results == 1 ) ? "result" : "results";
    echo "<span class='indextank_query_stats'>";
    if ($indextank_results == count($indextank_sorted_ids)){
        echo "$indextank_results $pluralized_results for " ;
    } else {
        echo count($indextank_sorted_ids) . " out of $indextank_results $pluralized_results for ";
    }
    echo "<strong>" . get_search_query() . "</strong> (";
    $formatted_time = sprintf($time_format, $indextank_search_time);
    if ($strip_leading_zero) {
        $formatted_time = str_replace("0.",".", $formatted_time);
    }
    echo $formatted_time;
    echo " seconds)";
    if ($logo){
        echo "<a class='logo' style='float:right' href='http://indextank.com'><img class='logo' src='".plugin_dir_url(__FILE__) ."powered_by_indextank.png' title='Powered by IndexTank'/></a>";
    }
    echo "</span>";
}


function indextank_add_pages() {
    add_management_page( 'Indextank Searching', 'Indextank Searching', 'manage_options', __FILE__, 'indextank_manage_page' );
}
add_action( 'admin_menu', 'indextank_add_pages' );


function indextank_manage_page() {

    if (isset($_POST['update'])) {
        if (isset($_POST['api_url']) && $_POST['api_url'] != '' ) {
            update_option('it_api_url',$_POST['api_url']);
        } 
        if (isset($_POST['index_name']) && $_POST['index_name'] != '') {
            update_option('it_index_name',$_POST['index_name']);
        }
    } 

    if (isset($_POST['index_all'])) {
        indextank_index_all_posts();
    }

    ?>
        <div class="wrap">
            <div id="icon-tools" class="icon32"><br></div>
            <!--<div style="background: url('http://indextank.com/_static/images/small-gray-logo.png')" class="icon32"><br /></div>-->
            <img style="float: right; margin: 10px; opacity: 0.5;" src="http://indextank.com/_static/images/color-logo.png">
            <h2>IndexTank Search Configuration</h2>
            <p style="line-height: 1.7em">
                In order to get IndexTank search running on your blog, you first need to open an IndexTank account.<br>
                You can do it <b><a href="https://indextank.com/get-started/">here</a></b>. There are free plans for you to try out!
            </p>
            <p style="line-height: 1.7em">
                Once you have your account, you'll need to go to your <b><a href="https://indextank.com/dashboard">dashboard</a></b>.<br>
                There you can create a new index, and then copy your API_URL (you'll find it in your dashboard) and your index name in the fields below:
            </p>
            <form METHOD="POST" action="">
                <h3>Index parameters</h3>
                <table class="form-table"> 
                    <tr> 
                        <th><label>API URL</label></th> 
                        <td><input type="text" name="api_url" size="60" value="<?php echo get_option("it_api_url");?>"/></td> 		
                    </tr>
                    <tr>
                        <th><label>Index name</label></th> 
                        <td><input type="text" name="index_name" size="15" value="<?php echo get_option("it_index_name");?>"/></td>
                    </tr>
                    <tr>
                        <td colspan="2"><input type="submit" name="update" value="Save changes"/></td>
                    </tr>
                </table>
            </form>

            <div style="margin-top: 30px; margin-bottom: 10px;">
                <hr>
            </div>

            <div id="icon-edit-pages" class="icon32"><br></div>
            <h2>Indexing your posts</h2>
            <p style="line-height: 1.7em">
                Once your index is running (you can check this in your <a href="https://indextank.com/dashboard">dashboard</a>) you will want to add your existing posts to it.<br>
                The button below will index (or reindex if they were already there) all your posts:
            </p>

            <form METHOD="POST" action="" >
                <input id="indextank_ajax_button" type="submit" name="index_all" value="Index all posts!"/>
                <img id="indextank_ajax_spinner" src="<?php echo admin_url();?>/images/loading.gif" style="display:none"/>
                <br>
                <div id="indexall_message"></div>
            </form>
            <p style="line-height: 1.7em">
                Once you've done this, every new post will get indexed automatically!
            </p>

        </div>
        <?php
}





/** FUNCTIONS RELATED TO AJAX INDEXING ON ADMIN PAGE */
function indextank_set_ajax_button(){
?>
    <script type="text/javascript">

    function indextank_poll_indexer($start){
        $start = $start || 0;
        var data = {
            action: 'indextank_handle_ajax_indexing',
            it_start: $start
        }
        jQuery.post(ajaxurl, data, function(response) {
                // error handling
                if (response == -1 || response == 0) {
                    alert ("some error triggered on the backend. is IndexTank plugin installed properly?");
                    jQuery("#indextank_ajax_spinner").hide();
                } else {
                    if (response.message) {
                        jQuery("#indexall_message").html(response.message);
                    }
                    
                    if (response.start  > 0 ) {
                        indextank_poll_indexer(response.start);
                    } else {
                        jQuery("#indexall_message").append(' .. done!');
                        jQuery("#indextank_ajax_spinner").hide();
                    } 
                }
                }, 'json') ;
    }


    jQuery(document).ready(function(){
        jQuery('#indextank_ajax_button').click(function(){
            jQuery("#indextank_ajax_spinner").show();
            indextank_poll_indexer();
            return false;
        });
    });
    </script>

<?php
}

add_action('admin_head', 'indextank_set_ajax_button');


function indextank_handle_ajax_indexing(){
    $start = isset($_POST['it_start']) ? intval($_POST['it_start']) : 0;
    $step = 30;

    $message = indextank_index_posts($start, $step);
    $start = $start + $step;
    
    if (empty($message)){
        $message = '';
        $start = -1;
    }
    header("Content-Type: application/json");
    # start is the number for the next client polling.
    echo "{\"start\": $start, \"message\" : \"$message\" }";
    die();
}

add_action("wp_ajax_indextank_handle_ajax_indexing", "indextank_handle_ajax_indexing");






function inject_indextank_head_script(){
    # remove the private part of the API URL.
    $private_api_url = get_option("it_api_url", "http://:aoeu@indextank.com/");
    $parts = explode("@", $private_api_url, 2);
    $public_api_url = $parts[1];
    ?>
        <script>
        jQuery(window).load(function(){
                var a = jQuery( "#s" ).autocomplete({
                                        source: function( request, response ) {
                                                jQuery.ajax({
                                                    url:'http://<?php echo $public_api_url; ?>/v1/indexes/<?php echo get_option("it_index_name");?>/autocomplete',
                                                    dataType: "jsonp",
                                                    data: { query: request.term },
                                                    success: function( data ) {
                                                            // create highlighting labels
                                                            var regex = new RegExp("(?![^&;]+;)(?!<[^<>]*)(" + request.term.replace(/([\^\$\(\)\[\]\{\}\*\.\+\?\|\\])/gi, "\\$1") + ")(?![^<>]*>)(?![^&;]+;)", "gi");
                                                            // Indextank returns the possible terms on an array, data.suggestions.
                                                            response( jQuery.map( data.suggestions, function( item ) {
                                                                return {
                                                                    label: item.replace(regex,"<strong>$1</strong>"),
                                                                    value: item,
                                                                };
                                                             }));
                                                    }
                                                });
                                        },
                                        minLength: 2,

                                        // auto submit when selecting
                                        select: function(event, ui){
                                            event.target.form.submit();
                                        },
                                    });

                // render highlighting labels
                a.data( "autocomplete" )._renderItem = function( ul, item ) {
                    return jQuery( "<li></li>" ).data( "item.autocomplete", item ).append( "<a>" + item.label + "</a>" ).appendTo( ul );
                };
        });

        </script>	
<?php
}

add_action('wp_head','inject_indextank_head_script');


/* Include CSS and JS only outside admin pages. jQuery from google CDN conflicts with admin pages. see http://core.trac.wordpress.org/ticket/11526 */
function indextank_include_js_css(){
    // check it's not an admin page
    if (!is_admin()) {
        wp_enqueue_style("jquery-ui","http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.5/themes/flick/jquery-ui.css");
        wp_enqueue_script("jquery-ui","https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.5/jquery-ui.min.js", array("jquery"));
        wp_enqueue_script("jquery");
    }
}

add_action("init", "indextank_include_js_css");



function indextank_boost_box(){
    if( function_exists( 'add_meta_box' )) {
        add_meta_box( 'indextank_section_id','Indextank boost', 'indextank_inner_boost_box', 'post', 'side' );
    } 
}
/* Use the admin_menu action to define the custom boxes */
add_action('admin_menu', 'indextank_boost_box');


function indextank_inner_boost_box(){
    global $post;
    $queries = get_post_custom_values("indextank_boosted_queries",$post->ID);
    if (!$queries) $queries = array();
    $queries = implode(" ", $queries);
    // Use nonce for verification
    echo '<input type="hidden" name="indextank_noncecode" id="indextank_noncecode" value="' . wp_create_nonce( plugin_basename(__FILE__) ) . '" />';

    // The actual fields for data entry
    echo '<label for="indextank_boosted_queries">Queries that will have this Post as first result</label>';
    echo '<textarea name="indextank_boosted_queries" rows="5">'.$queries.'</textarea>'; 
}


function indextank_save_boosted_query($post_id){
    // verify this came from the our screen and with proper authorization,
    // because save_post can be triggered at other times

    if ( !wp_verify_nonce( $_POST['indextank_noncecode'], plugin_basename(__FILE__) )) {
        return $post_id;
    }

    // verify if this is an auto save routine. If it is our form has not been submitted, so we dont want
    // to do anything
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
        return $post_id;


    // Check permissions
    if ( 'page' == $_POST['post_type'] ) {
        if ( !current_user_can( 'edit_page', $post_id ) )
            return $post_id;
    } else {
        if ( !current_user_can( 'edit_post', $post_id ) )
            return $post_id;
    }

    // OK, we're authenticated: we need to find and save the data
    $queries = $_POST['indextank_boosted_queries'];

    update_post_meta($post_id, "indextank_boosted_queries",$queries);
    indextank_boost_post($post_id);
    return $post_id;
}
add_action('save_post','indextank_save_boosted_query');

?>
