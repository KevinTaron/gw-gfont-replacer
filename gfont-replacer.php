<?php
/*
 Plugin Name: gFont Replace
 Plugin URI: https://gutwerker.de/
 Description: Replace all Google Fonts on your website with local fonts. Plugin downloads and serve the fonts from your server.  
 Version: 0.3.2
 Author: Kevin Taron
 Author URI: https://gutwerker.de
 Text Domain: gw-gfont-replacer
 License: GPLv3 or later
 License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

/*  Copyright 2018 Kevin Taron (email : k.taron@gutwerker.de)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// No direct file access
! defined( 'ABSPATH' ) AND exit;

// Version of the plugin
define('GW_GFONT_REPLACER_CURRENT_VERSION', '0.3.2' );


require 'pluginupdater/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://raw.githubusercontent.com/KevinTaron/gw-gfont-replacer/master/details.json',
	__FILE__, //Full path to the main plugin file or functions.php.
	'gw-gfont-replacer'
);

if ( ! class_exists( 'gw_gefont_replacer' ) ) {

	add_action( 'init', array( 'gw_gefont_replacer', 'instance' ) );

	class gw_gefont_replacer {

		protected static $instance = null;
		
		//Create new instance in order to call outsite class.
		public static function instance() {
			null === self :: $instance AND self :: $instance = new self;
			return self :: $instance;
		}

		public function __construct() {
			add_filter( 'style_loader_src', array( $this,'gfont_replacer_load_cssfiles'), 100000, 2 );	
			add_action('wp_head', array( $this,'gw_gfont_replacer_start_wp_head_buffer'), 0);
			add_action('wp_head', array( $this,'gw_gfont_replacer_end_wp_head_buffer'), PHP_INT_MAX);
		}

		function gw_gfont_replacer_start_wp_head_buffer() {
		    ob_start();
		}

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
					$this->download_css($gfontlink);
		   			$in = $this->gw_gfont_replacer_replace_headcsslinks($in, $gfontlink);
			    }
		    	
		    }


		    echo $in;
		}


		function gw_gfont_replacer_replace_headcsslinks($in, $linkurl) {
			$newfilename = $this->getCSSfileName($linkurl);
			$filelink = preg_match("/(family=)(.*)/", $linkurl, $output_array);
			$in = str_replace( '//fonts.googleapis.com/css?family=', plugins_url( '/gfonts/css/', __FILE__ ), $in );
			$in = str_replace( $output_array[2], $newfilename, $in );
			$in = str_replace( '//fonts.googleapis.com', network_site_url( '/' ), $in );

			return $in;
		}

		function gfont_replacer_load_cssfiles( $src, $handle ) {
			if ( ! preg_match("/fonts.googleapis.com/", $src, $output_array)) {
				return $src;			
			}

			$localurl = $this->download_css($src);
			return $localurl;
		}

		function checkIfGoogleFontsInCss($src) {
			$cssfile = file_get_contents($src);

			if(preg_match("/fonts.googleapis.com/", $cssfile, $output)) {
				return true;
			} 
			return false;
		}

		function download_css($url) {
			$cssfilename = $this->getCSSfileName($url);
			$localurl = plugins_url( '/gfonts/css/' . $cssfilename, __FILE__ );
			$localfile = plugin_dir_path( __FILE__ ) . "gfonts/css/" . $cssfilename; 
			$file_exists = file_exists($localfile);

			if(!$file_exists) {
				$url = 'https:' . $url;
				$url = str_replace('https:https://', 'https://', $url);
				$url = str_replace('https:http://', 'https://', $url);
				$cssfile = file_get_contents($url);

				if(!$cssfile) {
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL,$url);
					$cssfile = curl_exec($ch);
					curl_close($ch);
				}

				$replacefile = $this->download_allfonts($cssfile);

				$fp = fopen($localfile, 'w');
				fwrite($fp, $replacefile);
				fclose($fp);
			}

			return $localurl;
		}

		function download_allfonts($file) {
			preg_match_all("/https:\/\/fonts.gstatic.com\/s\/[^)]*/", $file, $gfontfiles);
			foreach ($gfontfiles[0] as $link) {
				$this->download_single_font($link);
			}
			$file = $this->cssReplaceUrl($file);
			return $file;
		}

		function cssReplaceUrl($file) {
			$pluginurl = plugins_url( '/gfonts/fonts', __FILE__ );
			$file =  preg_replace("/(https:\/\/fonts.gstatic.com\/s)/", $pluginurl ,$file);
			return $file;
		}

		function download_single_font($gfonturl) {
			$fontfile = file_get_contents($gfonturl);

			if(!$fontfile) {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL,$gfonturl);
				$fontfile = curl_exec($ch);
				curl_close($ch);
			}

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


		function getCSSfileName($url) {
			$filelink = preg_match("/(family=)(.*)/", $url, $output_array);
			return md5($output_array[2]) . '.css';
		}


	} // END

} // endif;