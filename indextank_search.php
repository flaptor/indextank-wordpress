<?php

/**
 * @package Indextank Search
 * @author Diego Buthay
 * @version 0.2
 */
/*
Plugin Name: IndexTank Search
Plugin URI: http://indextank.com/
Description: IndexTank makes search easy, scalable, reliable .. and makes you happy :)
Author: Diego Buthay
Version: 0.3
Author URI: http://facebook.com/dbuthay
*/


require_once("indextank_client.php");


// Indextank does not have snippets on responses yet, this plugin fakes them.
// There's some code commented out regarding $indextank_snippet_cache, which should be 
// uncommented when Indextank implements snippets on responses.



//$indextank_snippet_cache = array();
$indextank_results = 0;
$indextank_sorted_ids = array();

function indextank_search_ids($wp_query){
//	global $indextank_snippet_cache;
    global $indextank_results;
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
		
        //SHOULD DO TRY
		echo "<!-- " . $wp_query->query_vars['it_s'] . "-->";
		
		try {
		  $rs = $index->search($wp_query->query_vars['it_s']);

			$ids = array_map(
				create_function('$doc','return $doc->docid;'),
				array_values($rs->results)
				);
			echo "<!--" . $wp_query->query_vars['it_s']. "  " .print_r($ids,true) . "-->";
			if ($ids) { 
				$wp_query->query_vars['post__in'] = $ids;
				$wp_query->query_vars['post_type'] = "any";
	                    $indextank_results = $rs->response->matches;
	                    $indextank_sorted_ids = $ids;
			
				/*
				foreach ($rs['results']['docs'] as $doc){
					$indextank_snippet_cache[$doc['docid']] = $doc['text'];
				}
				*/
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


function indextank_sort_results($posts){
    global $indextank_sorted_ids;
    if (sizeof($indextank_sorted_ids) == 0) {
        return $posts;
    }
    foreach ($posts as $post) {
        $map[$post->ID] = $post;
    }
    foreach ($indextank_sorted_ids as $id) {
        $sorted[] = $map[$id];
    }
    return $sorted;
}

add_action("posts_results","indextank_sort_results");


// HACKY. 
function indextank_restore_query($ignored_parameter){
	global $wp_query;
	if ($wp_query->is_search){
		if ($wp_query->query_vars['s'] == '' and isset($wp_query->query_vars['it_s'])) {
			$wp_query->query_vars['s'] = $wp_query->query_vars['it_s'];
		}
	}
}
add_action("posts_selection","indextank_restore_query");


// the following from scott yang - http://scott.yang.id.au/code/search-excerpt/

function highlight_excerpt($keys, $text) {
    $text = strip_tags($text);
    $text = " " . $text . " " ; 

    for ($i = 0; $i < sizeof($keys); $i ++)
        $keys[$i] = preg_quote($keys[$i], '/');

    $workkeys = $keys;

    // Extract a fragment per keyword for at most 4 keywords.  First we
    // collect ranges of text around each keyword, starting/ending at
    // spaces.  If the sum of all fragments is too short, we look for
    // second occurrences.
    $ranges = array();
    $included = array();
    $length = 0;
    while ($length < 256 && count($workkeys)) {
        foreach ($workkeys as $k => $key) {
            if (strlen($key) == 0) {
                unset($workkeys[$k]);
                continue;
            }
            if ($length >= 256) {
                break;
            }
            // Remember occurrence of key so we can skip over it if more
            // occurrences are desired.
            if (!isset($included[$key])) {
                $included[$key] = 0;
            }

            // NOTE: extra parameter for preg_match requires PHP 4.3.3
            if (preg_match('/\b'.$key.'\b/iu', $text, $match,
                           PREG_OFFSET_CAPTURE, $included[$key]))
            {
                $p = $match[0][1];
                $success = 0;
                if (($q = strpos($text, ' ', max(0, $p - 60))) !== false && $q < $p) {
                    $end = substr($text, $p, 80);
                    if (($s = strrpos($end, ' ')) !== false && $s > 0) {
                        $ranges[$q] = $p + $s;
                        $length += $p + $s - $q;
                        $included[$key] = $p + 1;
                        $success = 1;
                    }
                }

                if (!$success) {
                    unset($workkeys[$k]);
			/*	
                    // for the case of asian text without whitespace
                    $q = _jamul_find_1stbyte($text, max(0, $p - 60));
                    $q = _jamul_find_delimiter($text, $q);
                    $s = _jamul_find_1stbyte_reverse($text, $p + 80, $p);
                    $s = _jamul_find_delimiter($text, $s);
                    if (($s >= $p) && ($q <= $p)) {
                        $ranges[$q] = $s;
                        $length += $s - $q;
                        $included[$key] = $p + 1;
                    } else {
                        unset($workkeys[$k]);
                    }
		*/
                }
            } else {
                unset($workkeys[$k]);
            }
        }
    }

    
    // If we didn't find anything, return the beginning.
    if (sizeof($ranges) == 0)
        return substr($text, 0, 256) . '&nbsp;&#8230;';
     

    // Sort the text ranges by starting position.
    ksort($ranges);

    // Now we collapse overlapping text ranges into one. The sorting makes
    // it O(n).
    $newranges = array();
    foreach ($ranges as $from2 => $to2) {
        if (!isset($from1)) {
            $from1 = $from2;
            $to1 = $to2;
            continue;
        }
        if ($from2 <= $to1) {
            $to1 = max($to1, $to2);
        } else {
            $newranges[$from1] = $to1;
            $from1 = $from2;
            $to1 = $to2;
        }
    }
    $newranges[$from1] = $to1;

    // Fetch text
    $out = array();
    foreach ($newranges as $from => $to)
        $out[] = substr($text, $from, $to - $from);

    $text = (isset($newranges[0]) ? '' : '&#8230;&nbsp;').
        implode('&nbsp;&#8230;&nbsp;', $out).'&nbsp;&#8230;';
    $text = preg_replace('/(\b'.implode('\b|\b', $keys) .'\b)/iu',
                         '<strong class="search-excerpt">\0</strong>',
                         $text);
    return $text;
}



function indextank_the_excerpt($post_excerpt) {
	global $indextank_snippet_cache, $post, $wp_query, $indextank_sorted_ids;
	
	if ($wp_query->is_search and isset($indextank_sorted_ids[$post->ID])) {
		
		$query = $wp_query->query_vars['it_s'];
		$content =  wp_specialchars( strip_tags( $post->post_content ) );
		$excerpt = highlight_excerpt(explode(" ", $query) , $content);
		return $excerpt;
	}	

	/*
	if (isset($indextank_snippet_cache[strval($post->ID)])) { 
		return $indextank_snippet_cache[strval($post->ID)];
	}
	*/


	// return default value
	return $post_excerpt;
}

add_filter("the_excerpt","indextank_the_excerpt");
add_filter("the_content","indextank_the_excerpt");



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


function indextank_add_post_raw($index,$post) {
	$content = array();
	$content['post_author'] = $post->post_author;
	$content['post_content'] = $post->post_content;
	$content['post_title'] = $post->post_title;
	$content['timestamp'] = strtotime($post->post_date_gmt);
	$content['text'] = $post->post_title . " " . $post->post_content . " " . $post->post_author; # everything together here
	if ($post->post_status == "publish") { 
		$res = $index->add_document($post->ID,$content); 
		indextank_boost_post($post->ID);
	}
}


function indextank_delete_post($post_ID){
	$api_url = get_option("it_api_url");
	$index_name = get_option("it_index_name");
	if ($api_url and $index_name) { 
		$client = new ApiClient($api_url);
        $index = $client->get_index($index_name);
        $status = $index->del($post_ID);
      	//echo "could not delete $post_ID on indextank.";
	} 
}
add_action("delete_post","indextank_delete_post");

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
      <?
		}
		ini_set('max_execution_time', $max_execution_time);
		ini_set('max_input_time', $max_input_time);
	}
}

// TODO allow to delete the index.
// TODO allow to create an index.


function indextank_add_pages() {
//    add_management_page( 'Indextank Searching', 'Indextank Searching', 'indextank_options', 'indextank_search', 'indextank_manage_page' );
    add_management_page( 'Indextank Searching', 'Indextank Searching', 'edit_post', __FILE__, 'indextank_manage_page' );
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
     <h3>Index parameters</hr>
		  <table class="form-table"> 
			   <tr> 
	         <th><label>API URL</label></th> 
           <td>
              <input type="text" name="api_url" size="60" value="<?php echo get_option("it_api_url");?>"/> 
           </td> 		
		     </tr>
			   <tr>
		       <th><label>Index name</label></th> 
			   <td>
              <input type="text" name="index_name" size="15" value="<?php echo get_option("it_index_name");?>"/>
         </td>
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
	   <input type="submit" name="index_all" value="Index all posts!"/> 
	   <br>
	   <div id="indexall_message"></div>
	</form>
  <p style="line-height: 1.7em">
     Once you've done this, every new post will get indexed automatically!
  </p>

	</div>
	<?php
}


function inject_indextank_head_script(){
?>
<style>
.autocomplete-w1 { background:url(img/shadow.png) no-repeat bottom right; position:absolute; top:0px; left:0px; margin:6px 0 0 6px; /* IE6 fix: */ _background:none; _margin:1px 0 0 0; }
.autocomplete { border:1px solid #999; background:#FFF; cursor:default; text-align:left; max-height:350px; overflow:auto; margin:-6px 6px 6px -6px; /* IE6 specific: */ _height:350px;  _margin:0; _overflow-x:hidden; }
.autocomplete .selected { background:#F0F0F0; }
.autocomplete div { padding:2px 5px; white-space:nowrap; overflow:hidden; }
.autocomplete strong { font-weight:normal; color:#3399FF; }
</style>


	<script>
        jQuery(window).load(function(){
            var options, a;
            jQuery(function(){
            options = {
                serviceUrl:'http://api.it-test.flaptor.com/api/v0/search/complete',
                params: {index_name:'<?php echo get_option("it_index_name");?>'}
//                serviceUrl:'api.indextank.com/api/v0/search/complete',
//                params: {index_name:'<?php echo get_option("it_index_name")?>'}
            };
            a = jQuery('#s').autocomplete(options);
            });
        });
        </script>	
<?php
}

add_action('wp_head','inject_indextank_head_script');

wp_enqueue_script("indextank_autocomplete","/jquery.autocomplete.js", array("jquery"));
wp_enqueue_script("jquery");



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
