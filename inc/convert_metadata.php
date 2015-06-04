<?php
/* 
This is for own conversions of written metadata
To activate set 
$convert_metadata=TRUE;
in the config.php

Examples for group_tag:
				category
				country
				locationcreatedcountryname
				city
				locationcreatedcity
				copyrightnotice
				rights
				credit
				DateTimeOriginal
				imagedescription
				description
				caption-abstract
				JobID
				keywords
				subject
				Model
				objectname
				title
				People
				source
				artist
				by-line
				creator
 */
## Just as an example
function convert_metadata($writevalue, $group_tag) {
	
	if (($group_tag=='copyrightnotice')|| ($group_tag=='rights')) {
		$writevalue=str_replace('(c)','©',$writevalue);
		if (strpos($writevalue, "©") === false) {
					$writevalue = '©'.$writevalue;
		}
	}
	
	return $writevalue;
}
