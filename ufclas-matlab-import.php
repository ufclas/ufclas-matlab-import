<?php
/*
Plugin Name: UFCLAS MATLAB Import
Plugin URI: https://it.clas.ufl.edu/
Description: Imports a zip file of HTML and images generated by MATLAB and creates a new page
Version: 1.1
Author: Priscilla Chapman (CLAS IT)
Author URI: https://it.clas.ufl.edu/
Text Domain: ufclas-matlab
Build Date: 20170412
*/

define('UFCLAS_MATLAB_PLUGIN_DIR', plugin_dir_path( __FILE__ )); 

/**
 * Add menu item to Settings in the dashboard menu
 *
 * @since 0.0.0
 */
function ufclas_matlab_register_menu(){
	add_management_page('MATLAB Import', 'MATLAB Import', 'manage_options', 'ufclas-matlab-import', 'ufclas_matlab_page');
}
add_action('admin_menu', 'ufclas_matlab_register_menu');

/**
 * Enqueue necessary scripts and styles for the screen
 *
 * @since 0.0.0
 */
function ufclas_matlab_scripts_styles( $hook ){
	if ( $hook == 'tools_page_ufclas-matlab-import' ){
		// Required to use all media JavaScript APIs.
		wp_enqueue_media();
		
		// Add page css
		$custom_css = '#ufclas-matlab {max-width: 960px;}';
		wp_add_inline_style('dashicons', $custom_css);
	}
}
add_action('admin_enqueue_scripts', 'ufclas_matlab_scripts_styles');

/**
 * Enqueue necessary scripts and styles for the screen
 *
 * @since 0.0.0
 */
function ufclas_matlab_init(){
	$upload = ufclas_matlab_handle_upload();
	
	if ( $upload === false ){
		add_action( 'admin_notices', 'ufclas_matlab_admin_notice_error' );
	}
	elseif ( !is_null($upload) ){
		add_action( 'admin_notices', 'ufclas_matlab_admin_notice_success' );
	}
}
add_action('load-tools_page_ufclas-matlab-import', 'ufclas_matlab_init');

/**
 * Display the import screen content
 *
 * @since 0.0.0
 */
function ufclas_matlab_page(){
	?>
	<div class="wrap" id="ufclas-matlab">
		
		<h1><?php _e('MATLAB HTML Import', 'ufclas-matlab'); ?></h1>
		
		<?php 
		// Ensure that the user has permission to upload
		if ( current_user_can( 'manage_options' ) ): 
			
			// Display the form
			ufclas_matlab_admin_display_form();
			
		endif; 
		?>
		
	</div>
	<?php
}

/**
 * Process the file upload
 *
 * @return bool|null
 * @since 0.0.0
 */
function ufclas_matlab_handle_upload(){
	if ( empty($_FILES) ){
		return;
	}
	
	// Test whether the request includes a valid nonce
	check_admin_referer('ufclas-matlab-import', 'wpnonce_ufclas_matlab_import');
	
	// Make sure we have credentials before trying to access the filesystem
	if ( ($access_type = get_filesystem_method() ) != 'direct' ){
		
		// No write access, warn the user with a notice
		return false;
	}
	else {
		$creds = request_filesystem_credentials( admin_url(), '', false, false, array());
		
		// Initialize the API
		if ( ! WP_Filesystem( $creds ) ) {
			return false;
		}
		
		global $wp_filesystem;
		
		$uploaded_file = $_FILES['import'];
		$overrides = array( 'test_form' => false, 'test_type' => false );
		$file = wp_handle_upload( $uploaded_file , $overrides);

		if ( isset($file['error']) ){
			return false;
		}
		
		/**
		 * Get the remote filesystem paths and create matlab-import folder
		 *
		 * $upload_root_path:	path to the site uploads folder
		 * $export_path:		path where zip file will be exported
		 * $import_path:		path where the html and images exist
		 */
		$user_id = get_current_user_id();
		$upload_dir = wp_upload_dir();
		$upload_root_path = $upload_dir['basedir'];
		$upload_root_path = trailingslashit( $upload_root_path ) . 'matlab-import/';
		$export_path = $upload_root_path . $user_id . '-' . wp_hash( $user_id ) . '/';
		$import_path = trailingslashit( $export_path ) . 'html/';
		
		/**
		 * Remove any existing export files, add blank index files
		 */
		$wp_filesystem->delete( $export_path, true );
		$wp_filesystem->touch($upload_root_path . 'index.html');
		$wp_filesystem->touch($export_path . 'index.html');
		$wp_filesystem->touch($import_path . 'index.html');
		
		/**
		 * Unzip the file and check for errors
		 */
		if ( is_wp_error( unzip_file( $file['file'], $export_path ) ) ){
			return false;
		}
		
		/**
		 * Save the uploaded file to the media library temporarily
		 *
		 * @todo Add option to keep the uploaded file
		 */
		$file_args = array(
			'post_title' => sanitize_file_name($file['file']),
			'post_content' => $file['url'],
			'post_mime_type' => $file['type'],
			'guid' => $file['url'],
			'context' => 'import',
			'post_status' => 'private'
		);
		
		$file_id = wp_insert_attachment( $file_args, $file['file'] );
		
		/**
		 * Save the uploaded file to the media library temporarily
		 *
		 * @todo Add option to keep the uploaded file
		 */
		if ( false === ($dirlist = $wp_filesystem->dirlist( $import_path, false, true )) ) {
			return false;
		}
		
		if ( WP_DEBUG ){
			error_log( print_r($dirlist, true) );
		}
		
		foreach ( $dirlist as $file_name => $file_data ){
			if ( $file_data['type'] == 'f' ){
				
				$name = $file_data['name'];
				
				if ( substr( $name, -5 ) == '.html' ){
					$exported_files['html'] = $name;
				}
				elseif ( substr( $name, -4 ) == '.png' ) {
					$exported_files['images'][] = $name;
				}
			}
		}
		
		// Set up the post content
		$html_path = $import_path . '/' . $exported_files['html'];
		$post_content = ufclas_matlab_get_post_content( $wp_filesystem->get_contents( $html_path ) );
		
		$post_args = array(
			'post_title' => sanitize_title( $exported_files['html'] ),
			'post_content' => wp_kses_post( $post_content ),
			'post_type' => 'page',
			'post_author' => get_current_user_id(),
			'post_status' => 'publish'
		);
		$post_id = wp_insert_post( $post_args );
		
		foreach ( $exported_files['images'] as $image ){
			$image_path = $import_path . '/' . $image;
			$image_type = wp_check_filetype( basename( $image_path ) );
			$image_upload_path = $upload_dir['path'] . '/' . sanitize_file_name( $image );
			$image_upload_url = $upload_dir['url'] . '/' . sanitize_file_name( $image );
			
			$wp_filesystem->move( $image_path, $image_upload_path, true );
			
			$attach_args = array(
				'post_title' => sanitize_title( $image ),
				'post_content' => '',
				'post_mime_type' => $image_type['type'],
				'post_status' => 'inherit',
				'guid' => $image_upload_url
			);
			
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
			
			$attach_id = wp_insert_attachment( $attach_args, $image_upload_path, $post_id );
			$attach_data = wp_generate_attachment_metadata( $attach_id, $image_upload_path );
			wp_update_attachment_metadata( $attach_id, $attach_data );
			
			// Update the image url in the page
			$image_url = wp_get_attachment_url( $attach_id, 'full' );
			$post_content = str_replace( $image, $image_upload_url, $post_content );
		}
		
		wp_update_post( array( 'ID' => $post_id, 'post_content' => $post_content ) );
		
		// Save the page ID temporarily for use in the success admin_notice
		ufclas_matlab_save_page_id( $post_id );
		
		// Clean up files
		wp_delete_attachment( $file_id );
		$wp_filesystem->delete( $import_path, true );
		$wp_filesystem->delete( $export_path, true );

		return true;
	}
}


/**
 * Include the view for the admin form
 *
 * @since 1.0.0
 */
function ufclas_matlab_admin_display_form(){
	include 'includes/admin-display-form.php';
}

/**
 * Add an admin notice as a result of the import error
 *
 * @since 0.0.0
 */
function ufclas_matlab_admin_notice_error(){
	?>
    <div class="notice notice-error">
        <p><?php _e( 'There was an error importing. Please try again.', 'ufclas-matlab' ); ?></p>
    </div>
    <?php
}

/**
 * Add an admin notice as a result of successfulimport
 *
 * @since 0.0.0
 */
function ufclas_matlab_admin_notice_success(){
	$message = ufclas_matlab_get_success();
	?>
    <div class="notice notice-success">
        <p><?php echo __( 'Import successful. View the imported page: ', 'ufclas-matlab' ) . $message; ?> </p>
    </div>
    <?php
	
}

/**
 * Get the success message from a transient
 *
 * @since 1.0.0
 */
function ufclas_matlab_get_success(){
	$transient_name = 'ufclas_matlab_success_' . get_current_user_id();
	$page_id = get_transient( $transient_name );
	$title = get_the_title( $page_id );
	$url = get_the_permalink( $page_id );
	delete_transient( $transient_name );
	
	return sprintf( '<a href="%s">%s</a>', $url, $title );
}

/**
 * Set the transient
 *
 * @since 1.0.0
 */
function ufclas_matlab_save_page_id( $page_id ){
	$transient_name = 'ufclas_matlab_success_' . get_current_user_id();
	set_transient( $transient_name, $page_id, MINUTE_IN_SECONDS );
}


/**
 * Get page content from imported HTML file, 
 *
 * Assumes the first div contains all the content for the new page
 *
 * @params string $html_content
 * @return string Post content
 *
 * @since 1.1.0
 */
function ufclas_matlab_get_post_content( $html_content ){
	// Get the contents of the div
	$dom = new DOMDocument();
	$dom->loadHTML( $html_content );
	$node_list = $dom->getElementsByTagName('div');
	
	// Create a new document
	$new_dom = new DOMDocument();
	$new_dom->formatOutput = true;
	
	// Create a new domElement div#matlab-import
	$new_div = $new_dom->createElement('div');
	$new_div->setAttribute( 'id', 'matlab-import' );
	
	// Add the content from the html div
	$new_div->appendChild( $new_dom->importNode($node_list->item(0), true) );
	$new_dom->appendChild( $new_div );
	
	return $new_dom->saveHTML();
}

/**
 * Connect to the filesystem 
 *
 * Assumes the first div contains all the content for the new page
 *
 * @params string $html_content
 * @return bool
 *
 * @since 1.1.0
 */
function ufclas_matlab_connect_fs( $post_url, $type = '', $error = false, $context = '', $fields = null ){
	global $wp_filesystem;
	
	if ( false === ( $credentials = request_filesystem_credentials( $post_url, $type, $error, $context, $fields ) ) ){
		return false;
	}
	
	// Check if creadentials are valid
	if ( ! WP_Filesystem( $credentials ) ) {
		return false;
	}
	
	return true;
}