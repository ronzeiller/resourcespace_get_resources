<?php
/**
 * get_resources Plugin  functions * 
 * @package ResourceSpace
 * by Ronnie Zeiller - www.zeiller.eu
 * 
 * 4.6.2015
 *		new:	config file with bool $write_debuglog
 *				function convert_to_server_charset()
 */

if (file_exists(dirname(__FILE__)."/../inc/convert_metadata.php")){
	include_once 'convert_metadata.php';
}

function update_log_file($note, $param = 'w') {
	global $log_file,$write_debuglog;
	## $param kann a oder w sein, bei w wird ein neues file erzeugt
	if ($write_debuglog) {
		$fp = fopen($log_file, $param);
		$filedata = $note;
		fwrite($fp, $filedata."\n");
		fclose($fp);
	}
}

function convert_to_server_charset($txt) {
	global $running_windows,$server_charset;
	
	if ($running_windows) {
		## set $to_charset allways to iso-8859-15 regardless RS config
		$to_charset = 'iso-8859-15';
	} else {
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
	}
	$txt = mb_convert_encoding($txt, $to_charset, 'UTF-8');
	return $txt;		
}

function message_alert($msg) {
	?>
	<script type="text/javascript">
		alert('<?php echo $msg; ?>');
	</script>
	<?php
}
## writes Meta-Data from the dbase into the file specified by $path and $ref
## similar function as function write_metadata($path, $ref, $uniqid="") found in resource_functions
## but without copying to tmp-folder

function schreibe_metadata($path, $ref) {

	global $copyrightFlag, $convert_metadata, $exiftool_remove_existing, $storagedir, $exiftool_write, $exiftool_no_process, $mysql_charset, $exiftool_write_omit_utf8_conversion;

	# Fetch file extension and resource type.
	$resource_data = get_resource_data($ref);
	$extension = $resource_data["file_extension"];
	$resource_type = $resource_data["resource_type"];

	$exiftool_fullpath = get_utility_path("exiftool");

	# Check if an attempt to write the metadata shall be performed.
	if (($exiftool_fullpath != false) && ($exiftool_write) && !in_array($extension, $exiftool_no_process)) {
		
		$filename = pathinfo($path,PATHINFO_FILENAME);	// Liefert Informationen über einen Dateipfad
		//$filename = $filename['basename'];	// Liefert den Dateinamen ohne Pfad

		
		# Add the call to exiftool and some generic arguments to the command string.
		# Argument -overwrite_original: Now that we have already copied the original file, we can use exiftool's overwrite_original on the tmpfile.
		# Argument -E: Escape values for HTML. Used for handling foreign characters in shells not using UTF-8.
		# Arguments -EXIF:all= -XMP:all= -IPTC:all=: Remove the metadata in the tag groups EXIF, XMP and IPTC.
		$command = $exiftool_fullpath . " -m -overwrite_original -E ";
		if ($exiftool_remove_existing) {
			$command.= "-EXIF:all= -XMP:all= -IPTC:all= ";
		}

		$write_to = get_exiftool_fields($resource_type);
		# Returns an array of exiftool fields for the particular resource type, which are basically fields with an 'exiftool field' set.

		for ($i = 0; $i < count($write_to); $i++) { # Loop through all the found fields.
			$fieldtype = $write_to[$i]['type'];
			$writevalue = "";
			
			# Formatting and cleaning of the value to be written - depending on the RS field type.
			switch ($fieldtype) {
				case 2:
					# Check box list: remove initial comma if present
					if (substr(get_data_by_field($ref, $write_to[$i]['ref']), 0, 1) == ",") {
						$writevalue = substr(get_data_by_field($ref, $write_to[$i]['ref']), 1);
					} else {
						$writevalue = get_data_by_field($ref, $write_to[$i]['ref']);
					}
					break;
				case 3:
					# Drop down list: remove initial comma if present
					if (substr(get_data_by_field($ref, $write_to[$i]['ref']), 0, 1) == ",") {
						$writevalue = substr(get_data_by_field($ref, $write_to[$i]['ref']), 1);
					} else {
						$writevalue = get_data_by_field($ref, $write_to[$i]['ref']);
					}
					break;
				case 4:
				case 6:
					# Date / Expiry Date: write datetype fields in exiftool preferred format
					$datecheck = get_data_by_field($ref, $write_to[$i]['ref']);
					if ($datecheck != "") {
						$writevalue = date("Y:m:d H:i:sP", strtotime($datecheck));
					}
					break;
				case 9:
					# Dynamic Keywords List: remove initial comma if present
					if (substr(get_data_by_field($ref, $write_to[$i]['ref']), 0, 1) == ",") {
						$writevalue = substr(get_data_by_field($ref, $write_to[$i]['ref']), 1);
					} else {
						$writevalue = get_data_by_field($ref, $write_to[$i]['ref']);
					}
					break;
				default:
					# Other types
					$writevalue = get_data_by_field($ref, $write_to[$i]['ref']);
			}

			# Add the tag name(s) and the value to the command string.
			$group_tags = explode(",", $write_to[$i]['exiftool_field']); # Each 'exiftool field' may contain more than one tag.
			foreach ($group_tags as $group_tag) {
				
				$group_tag = strtolower($group_tag); # E.g. IPTC:Keywords -> iptc:keywords
				if (strpos($group_tag, ":") === false) {
					$tag = $group_tag;
				} # E.g. subject -> subject
				else {
					$tag = substr($group_tag, strpos($group_tag, ":") + 1);
				} # E.g. iptc:keywords -> keywords

				switch ($tag) {
					case "filesize":
						# Do nothing, no point to try to write the filesize.
						break;
					case "keywords":
						# Keywords shall be written one at a time and not all together.
						$keywords = explode(",", $writevalue); # "keyword1,keyword2, keyword3" (with or with spaces)
						if (implode("", $keywords) == "") {
							# If no keywords set, write empty keyword field
							$command.= escapeshellarg("-" . $group_tag . "=") . " ";
						} else {
							# Only write non-empty keywords
							foreach ($keywords as $keyword) {
								$keyword = trim($keyword);
								if ($keyword != "") {
									# Convert the data to UTF-8 if not already.
									if (!$exiftool_write_omit_utf8_conversion && (!isset($mysql_charset) || (isset($mysql_charset) && strtolower($mysql_charset) != "utf8"))) {
										$keyword = mb_convert_encoding($keyword, 'UTF-8');
									}
									$command.= escapeshellarg("-" . $group_tag . "=" . htmlentities($keyword, ENT_QUOTES, "UTF-8")) . " ";
								}
							}
						}
						break;
					default:
						# Convert the data to UTF-8 if not already.
						if (!$exiftool_write_omit_utf8_conversion && (!isset($mysql_charset) || (isset($mysql_charset) && strtolower($mysql_charset) != "utf8"))) {
							$writevalue = mb_convert_encoding($writevalue, 'UTF-8');
						}

						## own conversions and adds to written metadata
						if (function_exists('convert_metadata') &&($convert_metadata)) {
							$writevalue = convert_metadata($writevalue, $group_tag);
						}

						$command.= escapeshellarg("-" . $group_tag . "=" . htmlentities($writevalue, ENT_QUOTES, "UTF-8")) . " ";
				}
			}
			
		}
		if (isset($copyrightFlag) && ($copyrightFlag==TRUE)) {
				$command.= escapeshellarg("-copyrightflag=true")." ";
		}
		# Add the filename to the command string.
		$command.= " " . escapeshellarg($path);

		//update_log_file($command,'a');
		# Perform the actual writing - execute the command string.
		$output = run_command($command);
		if (!$output) {return FALSE;} else {	return TRUE;}
	} else {
		return TRUE;
	}
}