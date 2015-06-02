<?php
/*
 * get_resources Plugin V0.9 
 * by Ronnie Zeiller - www.zeiller.eu
 * @package ResourceSpace
 * 
 * Called by copy_selected_collections.php
 * reads content of progress_file.txt
 *		1. no content -> preparing....
 *		2. content file 1 von x......
 *		3. content = complete when copying and writing meta is ready
 */
include "../../../include/db.php";
include "../../../include/general.php";
//include "get_resources_functions.php";

$anz_bilder = intval($_POST['anz_bilder']);
$progress_file=get_temp_dir(false,'') . "/progress_file.txt";

if (!file_exists($progress_file)){
	touch($progress_file);
}

$content= file_get_contents($progress_file);
if ($content==""){
	echo $lang['preparingzip'];
} else {
	ob_start();
	echo $content;
	ob_flush();
	exit();
}
