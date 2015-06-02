<?php
/**
 * get_resources Plugin  starting page V0.9 * 
 * @package ResourceSpace
 */
include dirname(__FILE__)."/../../../include/db.php";
include dirname(__FILE__)."/../../../include/authenticate.php";if (!checkperm('t')) {exit ($lang['error-permissiondenied']);}
include dirname(__FILE__)."/../../../include/general.php";
include_once dirname(__FILE__)."/../../../include/research_functions.php";
include_once dirname(__FILE__)."/../../../include/resource_functions.php";
include_once dirname(__FILE__)."/../../../include/search_functions.php";
include_once dirname(__FILE__)."/../../../include/collections_functions.php";
include_once dirname(__FILE__)."/../inc/get_resources_functions.php";

global $baseurl;
include dirname(__FILE__)."/../../../include/header.php";

## getting all collections but not 'My Collection'
$collections = sql_query("SELECT ref, name FROM `collection` WHERE name <> 'My Collection' ORDER BY name asc");
//var_dump($collections);


function update_copy_progress_file($note) {
	global $progress_file;
	$fp = fopen($progress_file, 'w');
	$filedata = $note;
	fwrite($fp, $filedata);
	fclose($fp);
}

## Variablen
$id = "";
$uniqid = "";
$amount_images_total = 0;

$collection = array();
$makeZip = FALSE;
$copyrightFlag = FALSE;
$deleteFolders = array();
$deletion_array = array();

## read $_GET / $_POST is empty
$submitted = getvalescaped("submitted", "");
$selectedCollection = getvalescaped("selectedCollection", "");
$makeZip = getvalescaped("makeZip", "");
$copyrightFlag = getvalescaped("copyrightFlag", "");
//var_dump($_GET);
$log_file = get_temp_dir(false, $id)."/logfile.txt";

if ($makeZip) {
	$settings_id = getvalescaped("settings","");
	// set the time limit to unlimited, default 300 is not sufficient here.
	set_time_limit(0);
	$archiver_fullpath = get_utility_path("archiver");

	if (!isset($zipcommand) && !$use_zip_extension) {
		
		if ($archiver_fullpath == false) {
			exit($lang["archiver-utility-not-found"]);
		}
		if (!isset($collection_download_settings)) {
			exit($lang["collection_download_settings-not-defined"]);
		} else if (!is_array($collection_download_settings)) {
			exit($lang["collection_download_settings-not-an-array"]);
		}
		if (!isset($archiver_listfile_argument)) {
			exit($lang["listfile-argument-not-defined"]);
		}
	}

	$archiver = ($archiver_fullpath != false) && (isset($archiver_listfile_argument)) && (isset($collection_download_settings) ? is_array($collection_download_settings) : false);

}


## start copying collections and images
if ($submitted != "") {
	## progress file with counts of total and copied resources
	$progress_file=get_temp_dir(false,'') . "/progress_file.txt";
	if (!file_exists($progress_file)){
			touch($progress_file);
	}
	update_copy_progress_file('');
	update_log_file($lang["get_resources_plugin_name"] . " " . nicedate(date("Y-m-d H:i:s"), true, true) . "\r\n".$lang['collection_found'].count($selectedCollection). "\r\n\r\n");

	## at first write all data concerning collection ref und name into array $collection
	## and we count the total amount of images to download
	## if there is no image in an collection we do not make any folder and cancel this collection 
	$i=0;
	foreach ($selectedCollection as $key => $value) {
		$qry = "SELECT ref, name FROM `collection` WHERE ref='".$value."' ORDER BY name ASC";
		$result =  sql_query($qry);
		// var_dump($qry);
		// var_dump($result);
		
		if (count($result)!=0) {
			## Read all images to this collection
			$qry2 = "select distinct r.ref, r.file_extension from resource r  "
				. "join collection_resource c on r.ref=c.resource   "
				. "where c.collection='".$result[0]['ref']."' "
				. "and (archive not in ('-2','-1') or created_by='1') "
				. "and r.ref>0 group by r.ref order by c.sortorder asc,c.date_added desc,r.ref asc";
			// $images = do_search("!collection" . $result[0]['ref']);
			$images = sql_query($qry2);

			if (count($images)>0) {
				$collection[$i]['name']=trim($result[0]['name']);
				$collection[$i]['ref']=$result[0]['ref'];
				$amount_images_total = $amount_images_total + count($images);

				## get all necessary informations to the images and write them into array $collection[$i]
				for ($n = 0; $n < count($images); $n++) {
					$collection[$i]['images_ref'][] = $images[$n]["ref"];

					## check availability of original file
					## kompletter Pfad inkl. Dateiname
					$collection[$i]['images_path'][] = get_resource_path($images[$n]["ref"], true, "", false, $images[$n]["file_extension"]);
				}
				$i++;

			} else {
				## Write Message to Logfile
				update_log_file("\r\n".$lang["collectionname"].": ". trim($result[0]['name']) . $lang["noresourcefound"]. "\r\n",'a');
			}
		}
	}
	update_log_file( "\r\n\r\n". $lang['amount_images_total']. $amount_images_total, 'a');

	$actual_image=0;
	for ($i=0;$i<count($collection);$i++) {

		if (!is_dir(get_temp_dir(false, '')."/".$collection[$i]['name'])) {
			mkdir ( get_temp_dir(false, '')."/".$collection[$i]['name'] );
			$deleteFolders[] = get_temp_dir(false, '')."/".$collection[$i]['name'];
		}

		update_log_file("\r\n". $lang["collectionname"].': '.$collection[$i]['name'],'a');

		if ($makeZip) {
			# Define the archive file
			$zipfile = str_replace(" ", "_",get_temp_dir(false, $id) . "/".$collection[$i]['name']);

			if ($use_zip_extension) {
				//$progress_file = $usertempdir . "/progress_file.txt";
				$zipfile.= ".zip";
				$zip = new ZipArchive();
				$zip->open($zipfile, ZIPARCHIVE::CREATE);
				update_log_file("zipped ----> ".$zipfile,'a');
			}
		}

		for ($n=0;$n<count($collection[$i]['images_path']);$n++) {
			$actual_image++;
			$db_image_pathname = $collection[$i]['images_path'][$n];
			$db_image_ref = $collection[$i]['images_ref'][$n];

			if (file_exists($db_image_pathname)) {
				# Retrieve the original file name
				$orig_filename = '';
				$orig_filename = get_data_by_field($db_image_ref, $filename_field);

				update_log_file($orig_filename,'a');	// var_dump($orig_filename);
				$newpath = get_temp_dir(false, '') . "/" . $collection[$i]['name']. "/" . $orig_filename;
				# Convert $filename to the charset used on the server.
				if (!isset($server_charset)) {
					$to_charset = 'UTF-8';
				} else {
					if ($server_charset != "") {
						$to_charset = $server_charset;
					} else {
						$to_charset = 'UTF-8';
					}
				}

				$newpath = mb_convert_encoding($newpath, $to_charset, 'UTF-8');

				## Copy Original Resource to the original filename in a folder called 
				## by the name of the Collection (at the moment in filestore/tmp)
				//update_log_file("$db_image_pathname -> $newpath", 'a');
				copy($db_image_pathname, $newpath);
				$deletion_array[] = $newpath;
				$metadata_write_ok = schreibe_metadata($newpath, $db_image_ref);
				
				if ($metadata_write_ok == FALSE) {
					update_log_file($lang['metadatawrite_wrong'] . $orig_filename, 'a');
				}
				update_copy_progress_file('file '.$actual_image.' von '.$amount_images_total);

				if (($makeZip) && ($use_zip_extension)) {
					$zip->addFile($newpath,$orig_filename);
				}
			} else {
				?>
				<script type="text/javascript">
					alert('<?php echo $lang["nodownloadcollection"]; ?>');
					history.go(-1);
				</script>
				<?php
				exit();
			}
		}
		if (($makeZip) && ($use_zip_extension)) {
			$wait = $zip->close();
		}
	}
	update_copy_progress_file('complete '.$amount_images_total);	// . file_get_contents($log_file)
	
	if ($makeZip) {
		# Remove temporary files.
		foreach ($deletion_array as $tmpfile) {
			if(file_exists($tmpfile)){unlink ($tmpfile);}
		}
		foreach ($deleteFolders as $tmpdir) {
			rmdir($tmpdir);
		}	
	}
}
?>

<div class="BasicsBox" >
	<script>

		jQuery( document ).ready(function($) {
		
			$('#downloadInProgress').hide();
			$('#selectAllList').click(function() {
			  var checkedStatus = this.checked;
			  $("#collectionCheckboxes input[type='checkbox']").each(function(){
					$(this).prop('checked', checkedStatus);
				});
			});
			$("#collectionCheckboxes input[type='checkbox']").click(function() {
				//showValues();
				$('input[name=selectAll]').prop('checked', 0);
			});
		});
		
		function ajax_download() {
			document.getElementById('downloadInProgress').style.display = 'block';
			document.getElementById('progress').innerHTML = '';
			document.getElementById('progress3').style.display = 'block';
			document.getElementById('progressdiv').style.display = 'block';
			
			document.getElementById('allCollectionCheckboxes').style.display = 'none';
			document.getElementById('downloadbuttondiv').style.display = 'none';


			jQuery('#text').attr('disabled', 'disabled');
			jQuery('#archivesettings').attr('disabled', 'disabled');
			var ifrm = document.getElementById('downloadiframe');
			var amount_images_total = 0;
			
			ifrm.src = "<?php echo $baseurl_short ?>plugins/get_resources/pages/copy_selected_collections.php?submitted=true&" + jQuery('#myform').serialize();

			progress = jQuery("progress3").PeriodicalUpdater("<?php echo $baseurl_short ?>plugins/get_resources/inc/copy_progress.php", {
							method: 'post', // method; get or post
							data: {anz_bilder: amount_images_total},
							minTimeout: 500, // starting value for the timeout in milliseconds
							maxTimeout: 2000, // maximum length of time between requests
							multiplier: 1.5, // the amount to expand the timeout by if the response hasn't changed (up to maxTimeout)
							type: 'text'           // response type - text, xml, json, etc.  

				}, function (remoteData, success, xhr, handle) {
					if (remoteData.indexOf("file ") != -1) {	// file 1 von 10
						var res = remoteData.split(" ",4);
						amount_images_total = parseInt(res[3]);
						var numfiles = parseInt(res[1]);
						if (numfiles == 1) {
							var message = numfiles + ' <?php echo $lang['filetocopy'] ?>';
						} else {
							var message = numfiles + ' <?php echo $lang['filestocopy'] ?>';
						}
						
						var status = Math.round(numfiles /amount_images_total * 10000)/100 + "%";
						//console.log(status);
						document.getElementById('progress2').innerHTML = status + "<br />" + message + ' <?php echo $lang['of']; ?> ' + amount_images_total;
					}
					else if (remoteData.indexOf("complete") != -1) {
						amount_images_total = remoteData.replace("complete ", "");
						document.getElementById('progress2').innerHTML = "<?php echo $lang['finished']; ?><br />" +amount_images_total+"<?php echo $lang['finished_copy_to'] . get_temp_dir(false,''); ?><br />";
						progress.stop();
					}
				});
		}
	</script>
	<h1><?php echo htmlspecialchars($lang["get_resources_plugin_name"]).$lang["to"].get_temp_dir(false,'');?></h1>
	<h2><?php echo htmlspecialchars($lang["select_collections"]);?></h2>

	<form id='myform' >
		
		<iframe id="downloadiframe" <?php if (!$debug_direct_download) { ?>style="display:none;"<?php } ?>></iframe>
		
		<div class="Question">
			<div id="makeZipCheckbox">
				<input  type="checkbox" name="makeZip" id="makeZip" /><?php echo $lang['makeZip']; ?>
			</div>
		</div>
		
		<div class="Question">
			<div id="makeCopyrightFlag">
				<input  type="checkbox" name="copyrightFlag" id="copyrightFlag" checked/><?php echo $lang['copyrightFlag']; ?>
			</div>
		</div>
		
		<div class="Question">
			<div id="allCollectionCheckboxes">
				
				<input  type="checkbox" name="selectAll" id="selectAllList" /><?php echo $lang['select_all']; ?><br />
				<hr>
				<div id='collectionCheckboxes'>
					<?php
					for ($i=0;$i<count($collections);$i++){
						//echo $i."<br>";
						echo "<input type='checkbox' name='selectedCollection[$i]' id='selectedCollection".$collections[$i]['ref']."' value='".$collections[$i]['ref']."' >".$collections[$i]['name']."<br>";
					}
					?>
				</div>
				<div class="clearerleft"></div>
			</div>
		</div>

		<div class="QuestionSubmit" id="downloadbuttondiv"> 
			<label for="download"> </label>
			<input type="button" onclick="ajax_download();" value="&nbsp;&nbsp;<?php echo $lang["submitbutton"]?>&nbsp;&nbsp;" />

			<div class="clearerleft"> </div>
		</div>
		<div id="progress"></div>

		<div id="downloadInProgress">
			<h2><?php echo $lang['progress'] ?></h2>

			<div class="Question" id="progressdiv" style="display:none;border-top:none;"> 

				<div class="Fixed" id="progress2" ></div><div class="Fixed" id="progress3" ></div>
				<div class="clearerleft"></div>
			</div>

		</div>

		<p><tt id="results"></tt></p>
	</form>
</div>

<?php
include dirname(__FILE__)."/../../../include/footer.php";