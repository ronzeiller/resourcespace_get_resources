<?php
/**
 * get_resources Plugin  config file * 
 * @package ResourceSpace
 * by Ronnie Zeiller - www.zeiller.eu
 */

$write_debuglog = TRUE;

## overrides $debug_direct_download in RS/include/config.php
$debug_direct_download = FALSE;

## permitted user
$permgroup = 't';

## Running Windows?
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
   $running_windows = TRUE;
} else {
   $running_windows = FALSE;
}

## zipfiles for download get "_" instead of " "
$write_zip_for_download = FALSE;

## to activate the function for metadata conversions set to TRUE
$convert_metadata=FALSE;