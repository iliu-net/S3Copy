<?php
$s3url = '';
$s3url .= '<br/>Leave <strong>blank</strong> to disable automatic <em>IMG SRC</em> tag rewriting.';
if (is_multisite()) {
  $s3url .= '<br/>Usually <strong>[BUCKET_URL]/'.get_current_blog_id().'/files</strong>.';
} else {
  $s3url .= '<br/>Usually <strong>[BUCKET_URL]/files</strong>.';
}

?><div class="wrap">
    <h2>S3 Copy</h2>
    <form method="post" action="options.php"> 
        <?php @settings_fields('s3_copy-group'); ?>
        <?php @do_settings_fields('s3_copy-group'); ?>

        <table class="form-table">  
	  <?php foreach ([
		    'S3_END_POINT' => [
		      'S3 End Point',
		      '<br/>Leave <strong>blank</strong> to disable automatic <em>S3</em> uploading.'
		    ],
		    'S3_ACCESS_KEY' => ['S3 Access Key',''],
		    'S3_SECRET_KEY' => ['S3 Secret Key',''],
		    'S3_BUCKET_NAME' => ['S3 Bucket',''],
		    'S3_URL_PATH' => [
		      'S3 URL path',
		      $s3url,
		    ],
		  ] as $i=>$j) {
		    list($j,$k) = $j;
		  
		  ?>

            <tr valign="top">
                <th scope="row"><label for="<?=$i?>"><?=$j?></label></th>
                <td><input type="text" name="<?=$i?>" id="<?=$i?>" value="<?php echo get_option($i); ?>" /><?=$k?></td>
            </tr>
	  <?php } ?>
        </table>

        <?php @submit_button(); ?>
    </form>
</div>