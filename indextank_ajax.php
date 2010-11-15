<?php

if (!function_exists('add_action'))
{
   require_once("../../../../wp-config.php");
}

if (isset($dl_pluginSeries)) {
    $dl_pluginSeries->showComments();
}



function indextank_index_all_posts(){
    $api_key = get_option("it_api_key");
    $index_code = get_option("it_index_code");
    if ($api_key and $index_code) { 
        $it = new IndexTank($api_key,$index_code);
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
                indextank_add_post_raw($it,$post);
                $count += 1;
            }
            $t2 = microtime(true);
            $time = round($t2-$t1,3);
            echo "<ul><li>Indexed $count posts in $time seconds</li></ul>";
        }
        ini_set('max_execution_time', $max_execution_time);
        ini_set('max_input_time', $max_input_time);
    }
}


?>