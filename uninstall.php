<?php
/**
 * Plugin Uninstall Procedure
 */

// Make sure that we are uninstalling
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) exit();

// Leave no trail
$options = ['S3_END_POINT', 'S3_ACCESS_KEY', 'S3_SECRET_KEY', 'S3_BUCKET_NAME' , 'S3_URL_PATH'];

if ( !is_multisite() )  {
  foreach ($options as $option_name) {
    delete_option( $option_name );
  }
} else {
    global $wpdb;
    $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
    $original_blog_id = get_current_blog_id();

    foreach ( $blog_ids as $blog_id ) {
      switch_to_blog( $blog_id );
      foreach ($options as $option_name) {
        delete_option( $option_name );     
        // OR
        // delete_site_option( $option_name );  
      }
    }
    switch_to_blog( $original_blog_id );
}