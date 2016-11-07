<?php
/*
Plugin Name: S3Copy
Plugin URI: http://nowhere/
Description: Backup media to an S3 account.  Based on defunct's Photocopy plugin by Ryan Hellyer <ryan@pixopoint.com>
Version: 1.0
Author: Alejandro Liu
Author URI: http://0ink.net
License: GPL2
*/
/*
Copyright 2016 Alejandro Liu (alejandro_liu@hotmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!class_exists('S3_Copy')) {
  if (!class_exists('S3')) require('inc/S3-class.php');
  class S3_Copy {
    public function __construct() {
      // Register actions
      add_action('admin_init', [$this, 'admin_init']);
      add_action('admin_menu', [$this, 'add_menu']);
      add_action('add_attachment', [$this, 'upload_attachment' ] );
      //add_action('image_make_intermediate_size', [$this, 'upload_intermediate_sizes' ] );
      add_action('admin_notices', [ $this, 'warnings'] );
      add_filter('content_save_pre', [ $this, 'fix_attachment_url'] );
      
      if (is_multisite()) add_action('admin_bar_menu',[$this,'show_site_id'],50);
    }
    public function show_site_id($admin_bar) {
      $sid = get_current_blog_id();
      $admin_bar->add_menu([
	'id' => 'site-id',
	'title' => 'SID:'.$sid,
	'href' => '#',
      ]);
    }
    public static function activate() {
    }
    public static function deactivate() {
    }
    public function admin_init() {
      $this->init_settings();
    }
    public function init_settings() {
      // register the settings for this plugin
      register_setting('s3_copy-group', 'S3_END_POINT');
      register_setting('s3_copy-group', 'S3_ACCESS_KEY');
      register_setting('s3_copy-group', 'S3_SECRET_KEY');
      register_setting('s3_copy-group', 'S3_BUCKET_NAME');
      register_setting('s3_copy-group', 'S3_URL_PATH');
    }
    public function warnings() {
      $warning = '';
      if (get_option('S3_URL_PATH')) {
	if (!get_option('S3_END_POINT')) $warning .= '<strong>Warning:</strong> You have not defined your S3 End Point<br />';
      }
      if (get_option('S3_END_POINT')) {
	if (!get_option('S3_ACCESS_KEY')) $warning .= '<strong>Warning:</strong> You have not added your S3 Access key<br />';
	if (!get_option('S3_SECRET_KEY')) $warning .= '<strong>Warning:</strong> You have not added your S3 Secret key<br />';
	if (!get_option('S3_BUCKET_NAME')) $warning .= '<strong>Warning:</strong> You have not added your S3 bucket name<br />';
      }
      if ( '' == $warning ) return;
      // Add warning wrapper (makes it appear red and important in the WP admin panel)
      $warning = '<div class="error"><p>' . $warning;
      $warning .= '</p></div>';
      echo $warning;
    }   
    /**
     * add a menu
     */     
    public function add_menu() {
      add_options_page('S3copy Settings', 'S3 Copy', 'manage_options', 's3copy', [&$this, 'plugin_settings_page']);
    }

    /**
     * Menu Callback
     */     
    public function plugin_settings_page() {
      if(!current_user_can('manage_options')) {
	wp_die(__('You do not have sufficient permissions to access this page.'));
      }

      // Render the settings template
      include(sprintf("%s/templates/settings.php", dirname(__FILE__)));
    }
    /*
     * Process full-sized file during upload process
     *
     * @author Ryan Hellyer <ryan@pixopoint.com>
     * @since 1.0
     * @param integer $post_id The ID of the attachment
     */
    public function upload_attachment( $post_id ) {
      if (!preg_match('/^image\//',get_post_mime_type($post_id))) return; // Filter out non-images
      if (!get_option('S3_END_POINT')) return; // If no S3 End-point defined, skip this!

      $file_dir = get_attached_file( $post_id ); // Grab file path
      $file = $this->_get_file_name( $file_dir ); // Grab array with file path and name

      // w00p w00p! Sending file to S3 :)
      $this->_send_to_s3( $file['path'], $file['name'] );
      
      // Mark as uploaded...
      $file['id'] = $post_id;
      $this->mark_as_sent($file);
      
      return $post_id;
    }

    /*
     * Process intermediate-sized files during upload process
     *
     * @author Ryan Hellyer <ryan@pixopoint.com>
     * @since 1.0
     * @param string $file_path The path to the file
     */
    public function upload_intermediate_sizes( $file_path ) {
      if (!get_option('S3_END_POINT')) return; // If no S3 End-point defined, skip this!
      $file = $this->_get_file_name( $file_path );
      $this->_send_to_s3( $file['path'], $file['name'] );
      $this->mark_as_sent($file);

      return $file_path;
    }
    
    public function attachment_id_from_file($file) {
      global $wpdb;
      return $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_value = '$file' LIMIT 1");	
    }  
    public function mark_as_sent($file) {
      if (!isset($file['id'])) {
	$file['id'] = $this->attachment_id_from_file($file["name"]);
      }
      update_post_meta($file['id'], "is_on_s3", 1);
    }

    /*
     * Get file name for sending to S3
     * Note: Amazon S3 file names also include the full directory path
     *
     * @author Ryan Hellyer <ryan@pixopoint.com>
     * @since 1.0
     * @return $array File name and file path
     * @param string $file_location The file location, may be either the file path or file URL
     * @access private
     */
    private function _get_file_name( $file_location ) {
      // Grab the uploads folder
      $uploads_location = wp_upload_dir();
      $upload_dir = $uploads_location['basedir'] . '/';
      $upload_url = $uploads_location['baseurl'] . '/';

      // If URL, then convert to path
      $initial = substr( $file_location, 0, 7 );
      if ( 'http://' == $initial || 'https://' == $initial ) {
	$file_path = str_replace( $upload_url, $upload_dir, $file_location ); // Convert URL to path
      } else {
	$file_path = $file_location;
      }

      // Create file name for Amazon S3
      $file_name = str_replace( $upload_dir, '', $file_path ); // File for upload (note: S3 file names include the directory structure)

      // Add blog ID for multi-site setups
      if ( is_multisite() ) {
	global $blog_id; // Blog ID for multi-site
	$file_name = $blog_id . '/files/' . $file_name;
      }

      // return an array with both file name and file path
      return [
	'name' => $file_name,
	'path' => $file_path,
      ];
    }

    /*
     * Send file to S3
     *
     * @author Ryan Hellyer <ryan@pixopoint.com>
     * @since 1.0
     * @param string $file_path The path to the file being sent
     * @param string $file_name The new location of the file on Amazon S3
     * @access private
     */
    private function _send_to_s3( $file_path, $file_name ) {
      // Instantiate the class
      $s3 = new S3( get_option('S3_ACCESS_KEY'), get_option('S3_SECRET_KEY'), TRUE, get_option('S3_END_POINT') );
      $s3->putObjectFile( $file_path, get_option('S3_BUCKET_NAME'), $file_name, S3::ACL_PUBLIC_READ );
    }
   function fix_attachment_url($inp) {
      if (!preg_match('/<img\s+/',$inp)) return $inp;
      if (!get_option('S3_URL_PATH')) return $inp;

      //$cutter = '<!----- CUT HERE ------->';
      //if (($i=strpos($inp,$cutter)) !== FALSE) {
      //  $inp = substr($inp,0,$i);
      //}
      //$xxx = PHP_EOL.$cutter.PHP_EOL;
      
      $baseurl = wp_upload_dir();
      $baseurl = trailingslashit($baseurl['baseurl']);
      $newurl = get_option('S3_URL_PATH');
      if (!$newurl) return $inp;     
      $newurl = trailingslashit($newurl);

      $out = '';
      $off = 0;
      while (preg_match('/<img\s+>/',$inp, $mv, PREG_OFFSET_CAPTURE, $off)) {
	$out .= substr($inp,$off, $mv[0][1]-$off);
	$off = $mv[0][1]+strlen($mv[0][0]);
	
	$attrs = ['src'=>'','width' => '', 'height'=>''];
	$otag = preg_replace('!\s*/\s*$!','',$mv[1][0]);
	$tag = ' '.stripslashes($otag).' ';
	
	foreach (['src','width','height'] as $k) {
	  foreach (['/\s'.$k.'="([^"]+)"\s/','/\s'.$k.'=(\S+)\s/'] as $re) {
	    if (preg_match($re,$tag,$mv)) {
	      $attrs[$k] = $mv[1];
	      $tag = preg_replace($re,' ',$tag);
	      break;
	    }
	  }
	}
		
	$tag = trim($tag);
	if (substr($attrs['src'],0,strlen($baseurl)) == $baseurl) {
	  $fname = substr($attrs['src'],strlen($baseurl));

	  if ($attrs['width'] && $attrs['height']) {
	    $re = '/-'.$attrs['width'].'x'.$attrs['height'].'\.(png|jpg|jpeg)$/i';
	    if (preg_match($re,$fname,$mv)) {
	      $fname = preg_replace($re,'.'.$mv[1],$fname);
	    }
	  }
	  // $fname is the right filename... check if it is on S3...
	  // Figure out what attachment we are using and what is the new URL
	  $id = $this->attachment_id_from_file($fname);
	  if ($id) {
	    $is_on_s3 = get_post_meta($id,'is_on_s3',TRUE);
	    if ($is_on_s3) {	
	      $q = '?';
	      $otag = 'src="'.$newurl.$fname;
	      if ($attrs['width']) {
		$otag .= $q.'scale.width='.$attrs['width'];
		$q = '&';
	      }
	      if ($attrs['height']) {
	        $otag .= $q.'scale.height='.$attrs['height'];
		$q = '&';
	      }
	      $otag .= '"';
	      if ($attrs['width']) $otag .= ' width="'.$attrs['width'].'"';
	      if ($attrs['height']) $otag .= ' height='.$attrs['height'].'"';
	      
	      $tag = preg_replace('/(\s)wp-image-(\d+)/','${1}s3_image-${2}',$tag);
	      
	      $otag .= ' '.$tag;
	      $otag = addslashes($otag);
	    }
	  }
	}

	$out .=  '<img '.$otag.' />'; //'[img src=XYZ '.$tag.' ('.$otag.')]';
      }
      $out .= substr($inp,$off);
      
      //return $inp.$xxx."\n===\n\n".$out;
      return $out;
	
    }
  }
}
if (class_exists('S3_Copy')) {
  register_activation_hook(__FILE__,['S3_Copy','activate']);
  register_deactivation_hook(__FILE__,['S3_Copy','deactivate']);
  $s3copy = new S3_Copy();
}

