<?php

/**
 * @package Indextank Search
 * @author Diego Buthay
 * @version 1.0
 */
/*
   Plugin Name: IndexTank Search
   Plugin URI: http://github.com/flaptor/indextank-wordpress/
   Description: IndexTank makes search easy, scalable, reliable .. and makes you happy :)
   Author: Diego Buthay
   Version: 1.0
   Author URI: http://twitter.com/dbuthay
 */


require_once("indextank.php");

function indextank_add_post($post_ID){
    $api_url = get_option("it_api_url");
    $index_name = get_option("it_index_name");
    if ($api_url and $index_name) { 
        $client = new Indextank_Api($api_url);
        $index = $client->get_index($index_name);
        $post = get_post($post_ID);
        indextank_add_post_raw($index,$post);
    }	
}  
add_action("save_post","indextank_add_post");


// add the post, without HTML tags and with entities decoded.
// we want the post content verbatim.
function indextank_add_post_raw($index,$post) {
    if ($post->post_status == "publish") {
        $data = indextank_post_as_array($post);
        $res = $index->add_document($data['docid'], $data['fields'], $data['variables']); 
        indextank_boost_post($post->ID);
    }
}

function indextank_batch_add_posts($index, $posts = array()){
    $data = array();
    foreach($posts as $post){
       	if ($post-> post_status == "publish") {
            $data[] = indextank_post_as_array($post);
        } 
    }
 
    $results = $index->add_documents($data);

    foreach($results as $i => $res){
        if (!$res->added){
            // TODO do something about this error
        } else {
            indextank_boost_post($posts[$i]->ID);
        } 
    }
    
}

function indextank_post_as_array($post) {
    $content = array();
    $userdata = get_userdata($post->post_author);
    $content['post_author'] = sprintf("%s %s", $userdata->first_name, $userdata->last_name);
    $content['post_content'] = html_entity_decode(strip_tags($post->post_content), ENT_COMPAT, "UTF-8"); 
    $content['post_title'] = $post->post_title;
    $content['timestamp'] = strtotime($post->post_date_gmt);
    $content['text'] = html_entity_decode(strip_tags($post->post_title . " " . $post->post_content . " " . $content['post_author']), ENT_COMPAT, "UTF-8"); # everything together here
    $content['url'] = get_permalink($post->ID);


    // grab thumbnail
    $content['thumbnail'] =  wp_get_attachment_image_src( get_post_thumbnail_id($post->ID));
    if ($content['thumbnail'] == NULL) { 
        unset($content['thumbnail']);
    } else { 
        $content['thumbnail'] = $content['thumbnail'][0];
    }  

    $vars = array("0" => $post->comment_count);

    return array("docid" => $post->ID, "fields" => $content, "variables" => $vars);
}


function indextank_delete_post($post_ID){
    $api_url = get_option("it_api_url");
    $index_name = get_option("it_index_name");
    if ($api_url and $index_name) { 
        $client = new Indextank_Api($api_url);
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
        $client = new Indextank_Api($api_url); 
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
        $client = new Indextank_Api($api_url);
        $index = $client->get_index($index_name);
        $max_execution_time = ini_get('max_execution_time');
        $max_input_time = ini_get('max_input_time');
        ini_set('max_execution_time', 0);
        ini_set('max_input_time', 0);
        $t1 = microtime(true);
        $my_query = new WP_Query();
        $query_res = $my_query->query("post_status=publish&orderby=ID&order=DESC&posts_per_page=$pagesize&offset=$offset");
        if ($query_res) {
            try { 
                indextank_batch_add_posts($index, $query_res);
            } catch (Exception $e) {
                return print_r($e, true);
                // skip
            }
            $t2 = microtime(true);
            $time = round($t2-$t1,3);
            // count all posts, even from previous iterations
            $count = $offset + count($query_res);
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

    if (isset($_POST['provision'])) {
        indextank_provision_account();
    } 
    
    if (isset($_POST['create-itjq'])) {
        indextank_create_itjq_configuration();
    } 

    if (isset($_POST['index_all'])) {
        indextank_index_all_posts();
    }


    $it_api_url = get_option("it_api_url");
    $it_index_name = get_option("it_index_name");

    ?>
        <div class="wrap">
            <div id="icon-tools" class="icon32"><br></div>
            <a href="http://indextank.com/" title="indextank.com" style="float:right; border: 0px;" ><img style="margin: 10px; opacity: 0.5;" src="<?php echo plugins_url('/images/color-logo.gif', __FILE__);?>"></a>
            
            <h2>IndexTank Search Configuration</h2>
            <?php
                if ( ( $it_api_url     == false ) && 
                     ( $it_index_name  == false ) ) { 
                ?>
           
                <form METHOD="POST" action="" style="float:left">
                <p style="line-height: 1.7em">
                    In order to get IndexTank search running on your blog, you first need to open an IndexTank account.<br>
                    If you don't have one, you can <input type="submit" name="provision" value="Get one!"/>
               </p>
               </form>

                <div style="clear:both"></div>
                <?php
                }
                ?>
 
            <form METHOD="POST" action="" style="float:left">
                <h3>Index parameters</h3>
                <table class="form-table"> 
                    <tr> 
                        <th><label>API URL</label></th> 
                        <td><input type="text" name="api_url" size="60" value="<?php echo get_option("it_api_url", "");?>"/></td> 		
                    </tr>
                    <tr>
                        <th><label>Index name</label></th> 
                        <td><input type="text" name="index_name" size="15" value="<?php echo get_option("it_index_name", "");?>"/></td>
                    </tr>
                    <tr>
                        <td colspan="2"><input type="submit" name="update" value="Save changes"/></td>
                    </tr>
                </table>
            </form>

            <div style="margin-top: 30px; margin-bottom: 10px; clear: both">
                <hr>
            </div>


            <?php 
                if ( ( $it_api_url     != false ) && 
                     ( $it_index_name  != false ) ) { 
                ?>


            <div id="icon-edit-pages" class="icon32"><br></div>
            <h2>Indexing your posts</h2>
            <p style="line-height: 1.7em">
            <?php

                $client = new Indextank_Api($it_api_url);
                $index = $client->get_index($it_index_name);

                if ($index->has_started()) {

            ?> Your index is <b>RUNNING</b>.
            The button below will index (or reindex if they were already there) all your posts:
            </p>
            <form METHOD="POST" action="" >
                <input id="indextank_ajax_button" type="submit" name="index_all" value="Index all posts!"/>
                <img id="indextank_ajax_spinner" src="<?php echo admin_url();?>/images/loading.gif" style="display:none"/>
                <br>
                <div id="indexall_message"></div>
            </form>

            <p style="line-height: 1.7em">
                New post will get indexed automatically!
            </p>

            <?php
                        } else {

            ?>Your index is <b>STARTING</b> .. Please wait a minute and hit 'refresh' on your browser.
            </p>
            <?php
                        } 
            ?>
            <div style="margin-top: 30px; margin-bottom: 10px;">
                <hr>
            </div>

            <div id="icon-edit-pages" class="icon32"><br></div>
            <h2>Look and Feel</h2>
            <form METHOD="POST" action="">
            <p style="line-height: 1.7em">
                Indextank is compatible with most Wordpress plugins out-of-the-box. If posts are not rendered nicely, you can try reconfiguring it with
                <input type="submit" name="create-itjq" value="Magic!">
            </p>
            </form>
            <p style="line-height: 1.7em">
                If you're still having issues (magic isn't always the solution), you may want to edit <b>'indextank/js/blogsearch.js'</b>.
            </p>

            <?php
            } // endif it_api_url && it_index_name
            ?>

        </div>
        <?php
}



function indextank_provision_account() { 
    $TOKEN = "3iH42aWmu5qDKM";
    $provision_url = "http://wordpress:".$TOKEN."@provision.api.indextank.com/v1/public/resources";
    
    $current_user = wp_get_current_user();
    $email = $current_user->user_email;

    if ( ( get_option("it_api_url")    == false ) && 
         ( get_option("it_index_name") == false ) ){

        $args = array(
                "email" => $email,
                "plan" => "FREE"
        );
        $args = json_encode($args);

        $session = curl_init($provision_url);
        curl_setopt($session, CURLOPT_CUSTOMREQUEST, "POST"); // Tell curl to use POST
        curl_setopt($session, CURLOPT_POSTFIELDS, $args); // Tell curl that this is the body of the POST
        curl_setopt($session, CURLOPT_HEADER, false); // Tell curl not to return headers
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true); // Tell curl to return the response
        curl_setopt($session, CURLOPT_HTTPHEADER, array('Expect:')); //Fixes the HTTP/1.1 417 Expectation Failed

        // execute the query
        $response = curl_exec($session);
        $http_code = curl_getinfo($session, CURLINFO_HTTP_CODE);
        curl_close($session);

        if ( ($http_code / 100) != 2 ) {
            return false;
        }

        $config = json_decode($response);
        $config = $config->config;


        update_option("it_api_url", $config->INDEXTANK_PRIVATE_API_URL);
        // the index name is ALWAYS idx for public provisioning
        update_option("it_index_name", "idx");

        return true;
    }

    // failed
    return false;
}


function indextank_create_itjq_configuration() {
    $rss_url = get_bloginfo('rss2_url');
    $blog_url = site_url();
    $theme = get_current_theme();
    

    $params = http_build_query( array(
        "feed" => $rss_url,
        "site" => $blog_url,
        "theme" => $theme
        )
    );
    $session = curl_init("http://wp-it-jq.cloudfoundry.com/generate?".$params);
        
        
    curl_setopt($session, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($session, CURLOPT_HEADER, false); // Tell curl not to return headers
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true); // Tell curl to return the response
    curl_setopt($session, CURLOPT_HTTPHEADER, array('Expect:')); //Fixes the HTTP/1.1 417 Expectation Failed

    // execute the query
    $response = curl_exec($session);
    $http_code = curl_getinfo($session, CURLINFO_HTTP_CODE);
    curl_close($session);

    if ( ($http_code / 100) != 2 ) {
        return false;
    }


    $target_filename = dirname(__FILE__) . '/js/blogsearch.js';

    // keep the old blogsearch.js, if existed
    if (file_exists($target_filename)) {
        rename ($target_filename, $target_filename . '.' . time());
    }

    // write the new one :)
    file_put_contents($target_filename, $response);

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
    $public_api_url = "http://" . $parts[1];
    ?>
        <script>

            var INDEXTANK_PUBLIC_URL = "<?php echo $public_api_url;?>";
            var INDEXTANK_INDEX_NAME = "<?php echo get_option("it_index_name");?>";

        </script>


        <style>

        #paginator {
            font-size: 1.4em;
        }

        .pagination {
            float: left;
            margin-left: 3px;
        }

        .pagination li {
            float: left;
            margin-left: 3px;
            list-style: none;
            cursor: pointer;
        }

        .pagination .selected {
            font-weight: bolder;
        }

        .pagination li:hover {
            text-decoration: underline;
        } 
        </style>

<?php
}

add_action('wp_head','inject_indextank_head_script');


/* Include CSS and JS only outside admin pages. jQuery from google CDN conflicts with admin pages. see http://core.trac.wordpress.org/ticket/11526 */
function indextank_include_js_css(){
    // check it's not an admin page
    if (!is_admin()) {
        wp_enqueue_style("jquery-ui","http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.5/themes/flick/jquery-ui.css");
        wp_enqueue_script("itwpsearch", plugins_url( "js/blogsearch.js", __FILE__), array("instantsearch"));
        wp_enqueue_script("instantsearch", plugins_url( "js/jquery.indextank.instantsearch.js", __FILE__), array("ize"));
        wp_enqueue_script("autocomplete", plugins_url( "js/jquery.indextank.autocomplete.js", __FILE__), array("ize"));
        wp_enqueue_script("statsrenderer", plugins_url( "js/jquery.indextank.statsrenderer.js", __FILE__), array("ize"));
        wp_enqueue_script("renderer", plugins_url( "js/jquery.indextank.renderer.js", __FILE__), array("ize"));
        wp_enqueue_script("pagination", plugins_url( "js/jquery.indextank.pagination.js", __FILE__), array("ize"));
        wp_enqueue_script("ajaxsearch", plugins_url( "js/jquery.indextank.ajaxsearch.js", __FILE__), array("ize"));
        wp_enqueue_script("querybuilder", plugins_url( "js/querybuilder.js", __FILE__), array("ize"));
        wp_enqueue_script("ize", plugins_url( "js/jquery.indextank.ize.js", __FILE__) , array("jquery"));
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
