<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @since 1.0.0
 */
?>

<h2><?php _e('Step 1: Publish to HTML', 'ufclas-matlab'); ?></h2>
<p><?php _e('By default, MATLAB creates a subfolder named <code>html</code>, which contains an HTML file and files for each .png graphic that your code creates.', 'ufclas-matlab'); ?></p>

<h2><?php _e('Step 2: Zip the html Folder', 'ufclas-matlab'); ?></h2>
<p><?php _e('Create a new .zip archive file, making sure that it contains the <code>html</code> folder.', 'ufclas-matlab'); ?></p>
<p><?php _e('The import only supports .html and .png files. Any other file formats within the archive will be deleted.', 'ufclas-matlab'); ?></p>

<h2><?php _e('Step 3: Upload the .zip file', 'ufclas-matlab'); ?></h2>
<p><?php _e('The importer will create a new page under Pages and add the associated images to the Media Library.', 'ufclas-matlab'); ?></p>
<p><?php _e('<strong>Note:</strong> The imported .zip file will be deleted after upload. To upload a permanent copy, use the Media Library.', 'ufclas-matlab'); ?></p>
<p><?php _e('<strong>Note:</strong> This will overwrite any image files in the Media Library with the same name, so make sure your uploaded file names are unique.', 'ufclas-matlab'); ?></p>

<form id="ufclas-matlab-upload" method="post" enctype="multipart/form-data">
	<label for="import" class="screen-reader-text"><?php _e( 'Choose a .zip file:' ); ?></label><br />
	<input type="file" id="import" name="import" />
	<?php wp_nonce_field('ufclas-matlab-import', 'wpnonce_ufclas_matlab_import' ); ?>
	<?php submit_button( __( 'Import File', 'ufclas-matlab' ), 'primary', 'submit' ); ?>
</form>