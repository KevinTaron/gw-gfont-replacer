<?php
/*
 Plugin Name: gFont Replace
 Plugin URI: https://gutwerker.de/
 Description: Replace Google Fonts with local Font. 
 Version: 0.1
 Author: Kevin Taron
 Author URI: https://gutwerker.de
 Text Domain: gfont-replacere
 */

/*  Copyright 2018 Kevin Taron (email : k.taron@gutwerker.de)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Version of the plugin
define('GW_GFONT_REPLACER_CURRENT_VERSION', '0.1' );


function gw_gfont_replacer_start_wp_head_buffer() {
    ob_start();
}
add_action('wp_head','gw_gfont_replacer_start_wp_head_buffer',0);

function gw_gfont_replacer_end_wp_head_buffer() {
    $in = ob_get_clean();

    $search = "<link.*href=.*(fonts.googleapis.com/css\?family=.*)>";
    $mymatch = preg_match_all($search, $in, $output_array);

    if($mymatch) {
	    $findlink = "/href=\"([^\"]*)\"/";
	    $linkurl = preg_match_all($findlink, $output_array[0][0], $linkarray);


	    if(!$linkurl) {
	    	$findlink = "/href='([^']*)'/";
	    	$linkurl = preg_match_all($findlink, $output_array[0][0], $linkarray);
	    }

	    if($linkurl) {
	    	$gfontlink = $linkarray[1][0];
			gw_gfont_replacer_download_css($gfontlink);
   			$in = gw_gfont_replacer_replace_headcsslinks($in, $gfontlink);
	    }
    	
    }


    echo $in;
}
add_action('wp_head','gw_gfont_replacer_end_wp_head_buffer', PHP_INT_MAX);


function gw_gfont_replacer_replace_headcsslinks($in, $linkurl) {
	$newfilename = gw_gfont_replacer_getCSSfileName($linkurl);
	$filelink = preg_match("/(family=)(.*)/", $linkurl, $output_array);
	$in = str_replace( '//fonts.googleapis.com/css?family=', plugins_url( '/gfonts/css/', __FILE__ ), $in );
	$in = str_replace( $output_array[2], $newfilename, $in );
	$in = str_replace( '//fonts.googleapis.com', network_site_url( '/' ), $in );

	return $in;
}




function gw_gfont_replacer_download_css($url) {
	$cssfilename = gw_gfont_replacer_getCSSfileName($url);
	$localurl = plugins_url( '/gfonts/css/' . $cssfilename, __FILE__ );
	$localfile = plugin_dir_path( __FILE__ ) . "gfonts/css/" . $cssfilename; 
	$file_exists = file_exists($localfile);

	if(!$file_exists) {
		$cssfile = file_get_contents('https:' . $url);
		$replacefile = gw_gfont_replacer_download_gfonts($cssfile);

		$fp = fopen($localfile, 'w');
		fwrite($fp, $replacefile);
		fclose($fp);
	}

	return $localurl;
}


function gw_gfont_replacer_getCSSfileName($url) {
	$filelink = preg_match("/(family=)(.*)/", $url, $output_array);
	return md5($output_array[2]) . '.css';
}

function gw_gfont_replacer_download_gfonts($file) {
	preg_match_all("/https:\/\/fonts.gstatic.com\/s\/[^)]*/", $file, $gfontfiles);
	foreach ($gfontfiles[0] as $link) {
		gw_gfont_replacer_download_single_gfont($link);
	}
	$file = gw_gfont_replacer_cssReplaceUrl($file);
	return $file;
}

function gw_gfont_replacer_cssReplaceUrl($file) {
	$pluginurl = plugins_url( '/gfonts/fonts', __FILE__ );
	$file =  preg_replace("/(https:\/\/fonts.gstatic.com\/s)/", $pluginurl ,$file);
	return $file;
}

function gw_gfont_replacer_download_single_gfont($gfonturl) {
	$fontfile = file_get_contents($gfonturl);
	$localfile = preg_replace("/https:\/\/fonts.gstatic.com\/s\//", plugin_dir_path( __FILE__ ) . "gfonts/fonts/", $gfonturl);
	$localfilename = preg_match("/[^\/]*$/", $localfile, $localfilenamearray);
	$localfilname = $localfilenamearray[0];
	$localdir = str_replace($localfilname, '', $localfile);


	if (!is_dir($localdir)) {
	    mkdir($localdir, 0777, true); // true for recursive create
	}

	$fp = fopen($localfile, 'w');
	fwrite($fp, $fontfile);
	fclose($fp);
}