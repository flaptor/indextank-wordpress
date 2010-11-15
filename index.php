<?php

require_once("indextank.php");

// Datos de Indextank
$api_key = "<api_key>";
$index_code = "<index_code>";

// Datos de la DB
$database="<database>";
$blog_id="<blog_id>";
$user="<user>";
$pass="<pass>";


$it = new IndexTank($api_key,$index_code);
mysql_connect('localhost',$user,$pass) or die("Could not connect: ".mysql_error());
mysql_select_db($database) or die('Could not select database');

$pagelen = 100;
$offset = 0;

$sql = "select count(*) as c from wp_".$blog_id."_posts where post_status = 'publish'";
$query = mysql_query($sql) or die("Query failed");
$results = mysql_fetch_assoc($query);
print_r($results);
$posts = $results['c'];

while ($posts > 0) {
    $query = "select p.ID, u.display_name as post_author, p.post_title, p.post_content, p.post_date_gmt as fecha, UNIX_TIMESTAMP(p.post_date_gmt) as timestamp from wp_".$blog_id."_posts p, wp_users u where post_status = 'publish' and p.post_author = u.ID order by p.ID desc limit $pagelen offset $offset";
    $result = mysql_query($query) or die("Query failed");
    while ($post = mysql_fetch_array($result, MYSQL_ASSOC)) {
        $id = $post['ID'];
		$date = $post['fecha'];
        unset($post['ID']);
        unset($post['fecha']);
        echo "id: $id    date: $date\n";
        $post['text'] = $post['post_title'].' '.$post['post_content'].' '.$post['post_author'];
        $status = $it->add($id,$post);
        if ($status['status'] == "ERROR") {
            echo "Error indexing post #$id: ".$status['message']."\n";
        }
    }
    echo "Finished.\n";
    $offset += $pagelen;
    $posts -= $pagelen;
}

