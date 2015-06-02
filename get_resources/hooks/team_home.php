<?php
/**
 * get_resources Plugin shows in Team Home V0.9 
 * by Ronnie Zeiller - www.zeiller.eu
 * @package ResourceSpace
 */
function HookGet_resourcesTeam_homeCustomteamfunction() {
	global $baseurl, $lang;
	
    if (checkperm("o"))
		{
		
		?><li><a href="<?php echo $baseurl ?>/plugins/get_resources/pages/copy_selected_collections.php"><?php echo $lang["get_resources_plugin_name"]?></a></li>
		<?php
		}
}