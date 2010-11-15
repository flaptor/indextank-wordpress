<?php

if (!function_exists('add_action'))
{
   require_once("../../../../wp-config.php");
}

?>

function indexAllDocs() {
    var url = "<?php bloginfo('wpurl') ?>/wp-content/plugins/indextank/indextank_ajax.php"
    
    $.ajax( { url: url, dataType: 'json', data: 
}

