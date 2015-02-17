<?php
/**
 * File inc/otat-admin-tab-export.php
 *
 * Part of the WordPress plugin One Time Access Tokens.
 * Provide the contents of the admin page's export tab.
 */

defined( 'OTAT_DIR' ) || die();
?>


<h3><?php _e( 'Append tokens of a campaign to a CSV file', 'otat' ); ?></h3>
<?php

$form_data = false;
if ( ! empty( $_FILES ) )
	otat_save_csv_upload_file();
elseif ( $_POST ) {
	$form_data = otat_validate_tab_export_fields();
	if ( false !== $form_data ) {
		otat_make_tmp_table( $form_data );
	}
}
otat_print_tab_export_form( $form_data );



function otat_print_tab_export_form( $form = array() ) {
?>
<table class="form-table">
	<tbody><?php if ( false === ( $csv_file = get_transient( 'otat_uploaded_csv_file' ) ) || ! file_exists( $csv_file['name'] ) ): ?>
		<tr>
			<th scope="row"><label for="otat_upload_csv"><?php _e( 'CSV file', 'otat' ); ?>:</label></th>
			<td>
				<form action="" method="post" enctype="multipart/form-data" class="wp-upload-form" id="upload-form" name="upload-form">
					<?php wp_nonce_field( 'otat_upload_csv' ); ?>
					<input type="file" id="csv-file" name="csv-file" value="" />
					<?php wp_nonce_field( plugin_basename( __FILE__ ), 'csv-to-prepare' ); ?>
					<input type="submit" name="upload-submit" id="" class="button button-primary" value="<?php _e( 'Upload', 'otat' ); ?>" />
				</form>
				<p class="description"><?php _e( 'Only CSV files can be uploaded.', 'otat' ); ?></p>
			</td>
		</tr>
	</tbody>
</table>
		<?php else : ?>
		<tr>
			<th scope="row"><?php _e( 'Uploaded CSV file', 'otat' ); ?></th>
			<td>
				<p><?php print sprintf( __( '%s contains: <strong>%s</strong> data lines.', 'otat' ), basename( $csv_file['name'] ), $csv_file['numrows'] ); ?></p>
			</td>
		</tr>
		<tr>
			<div class="form-field form-required">
				<th scope="row"><?php _e( 'Select campaign', 'otat' ); ?></th>
				<td>
					<form action="" method="post" id="otat_create">
					<?php wp_nonce_field( 'otat_export' ); ?>
					<select name="otat_campaign_id" id="otat_campaign_id">
						<option value="-1" selected="selected"><?php _e( 'Please select', 'otat' ); ?></option><?php
						global $wpdb;
						$sql  = "SELECT `ID`, `otat_campaign_name`, `otat_token_amount` ";
						$sql .= "FROM `{$wpdb->prefix}otat_campaigns` ";
						$sql .= "WHERE `otat_valid_until_gmt` > NOW() ";
						$sql .= "ORDER BY `otat_created_gmt` DESC;";
						$campaign_list = $wpdb->get_results( $sql );
						foreach( $campaign_list as $campaign ) {
							if ( isset( $csv_file['numrows'] ) && $csv_file['numrows'] != $campaign->otat_token_amount )
								$active = ' disabled';
							else
								$active = ''; ?>
							<option value="<?php esc_attr_e( $campaign->ID ); ?>"<?php print $active; ?>>
								<?php esc_html_e( $campaign->otat_campaign_name ); ?> (<?php esc_html_e( $campaign->otat_token_amount ); ?>)
							</option><?php
						} ?>
					</select>
					<p class="description"><?php _e( 'The count of campaign\'s tokens and file\'s data lines has to match.', 'otat' ); ?></p>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php _e( 'Save email addresses', 'otat' ); ?></th>
			<td><fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Save email addresses from file locally.', 'otat' ); ?></span></legend>
				<label for="otat_assign_email">
					<input name="otat_assign_email" type="checkbox" id="otat_assign_email" value="1">
					<?php _e( 'Save email addresses from file into the local database and assign them to their corresponding token.', 'otat' ); ?>
				</label></fieldset>
				<p class="description"><?php _e( 'Only useful for special use cases.', 'otat' ); ?></p>
			</td>
		</tr>
	</tbody>
</table>
<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Save Changes and Create Download.', 'otat' ); ?>"></p>
</form>
<?php
	endif;
}


/**
 * Form validation
 * @return Array of validated form data || FALSE in case of errors
 */
function otat_validate_tab_export_fields() {
	$form_data = array();

	if ( ! current_user_can( 'manage_options' ) )
		return false;

	if ( ! empty( $_FILES ) )
		check_admin_referer( 'otat_upload_csv' );

	check_admin_referer( 'otat_export' );

	if ( false === $uploaded_csv_file = get_transient( 'otat_uploaded_csv_file' ) ) {
		otat_set_message( __( 'Old version of the file has expired. Please upload again.', 'otat' ), 'error');
		return false;
	}
	$form_data['csv-file'] = $uploaded_csv_file['name'];

	if ( (int)$_POST['otat_campaign_id'] == -1 ) {
		otat_set_message( __( 'Please select a campaign.', 'otat' ), 'error');
		return false;
	}
	$form_data['otat_campaign_id'] = absint( $_POST['otat_campaign_id'] );
	if ( $form_data['otat_campaign_id'] < 1 ) {
		otat_set_message( __( 'This campaign seems not to be valid.', 'otat' ), 'error');
		return false;
	}

	$form_data['otat_assign_email'] = empty( $_POST['otat_assign_email'] ) ? 0 : 1;

	return $form_data;
}


function otat_save_csv_upload_file() {
	if ( ! function_exists( 'wp_handle_upload' ) ) 
		require_once( ABSPATH . 'wp-admin/includes/file.php' );

	$uploadedfile = $_FILES['csv-file'];
	$upload_overrides = array( 'test_form' => false, 'mimes' => array( 'csv' => 'text/csv' ) );
	add_filter( 'upload_dir', 'otat_get_upload_dir');
	$movefile = wp_handle_upload( $uploadedfile, $upload_overrides );
	if ( count( $movefile ) > 1 ) { // In case of errors only the key 'error' will be returned.
		$csv_lines = count( file( $movefile['file'] ) ) - 1 ; // Minus first line which contains field names.
		$csv_file = array( 'name' => $movefile['file'], 'numrows' => $csv_lines );
		set_transient( 'otat_uploaded_csv_file', $csv_file, 300 );
		return true;
	} else {
		print '<div class="error"><p>' . esc_html( $movefile['error'] ) . '</p></div>';
		//TODO: next lines do not work since hook admin_notices was running already
		$otat_file_upload_error_msg = apply_filters( 'otat_file_upload_error_msg', esc_html( $movefile['error'] ) );
		otat_set_message( $otat_file_upload_error_msg, 'error' );
		return false;
	}
}


/**
 * Callback for Hook 'upload_dir'.
 */
function otat_get_upload_dir( $upload ) {
	$otat_settings = get_option( 'otat' );
	if ( false !== $otat_settings ) {
		if ( isset( $otat_settings['upload_dir'] ) )
			return $otat_settings['upload_dir'];
	} else {
		$otat_settings = array();
	}


	$otat_upload_dir = '/otat-upload-' . md5( 'NONCE_SALT' . time() );
	$otat_upload = array(
		'path'    => $upload['basedir'] . $otat_upload_dir,
		'url'     => $upload['baseurl'] . $otat_upload_dir,
		'subdir'  => $otat_upload_dir,
		'basedir' => $upload['basedir'],
		'baseurl' => $upload['baseurl'],
		'error'   => $upload['error']
	);
	$otat_settings['upload_dir'] = $otat_upload;
	update_option( 'otat', $otat_settings );

	return $otat_upload;
}


/**
 * This function handles all functionality. TODO: Split and name it less misleading.
 */
function otat_make_tmp_table( $form_data ) {
	global $wpdb;

	// Create temp table for a UPDATE JOIN with orig table - if needed.
	if ( $form_data['otat_assign_email'] ) {
		// Create a temporary table when it comes to assigning tokens to email addresses.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}TMP_otat_tokens;" );
		$tmp_table = $wpdb->query( "CREATE TABLE {$wpdb->prefix}TMP_otat_tokens LIKE {$wpdb->prefix}otat_tokens;" );
		if ( false === $tmp_table ) {
			//TODO: err_msg
			return false;
		}
	}

	// Read tokens from db.
	$token_list = $wpdb->get_results( $wpdb->prepare(
		"SELECT `otat_token` FROM `{$wpdb->prefix}otat_tokens` WHERE campaign_id = %d;",
		$form_data['otat_campaign_id'] ) );

	// Read data from uploaded file.
	$csv_lines = file( $form_data['csv-file'], FILE_IGNORE_NEW_LINES );

	// Append a token to each file line and, when email address is requested, write
	// each token together with it's assigned email address into a temp table.
	$sql  = "INSERT INTO `{$wpdb->prefix}TMP_otat_tokens`";
	$sql .= " (`ID`, `campaign_id`, `otat_email`, `otat_token`, `otat_accessed_gmt`)\nVALUES";
	$csv_lines[0] .= ',OneTimeAccessToken';
	for( $i = 1; $i < count( $csv_lines ); ++$i ) {
		$csv_lines[$i] .= ",{$token_list[$i - 1]->otat_token}";
		if ( $form_data['otat_assign_email'] ) {
			$fields = explode( ',', $csv_lines[$i] );
			// Again, we do not use $wpdb->prepare() - for performance reasons.
			$email = esc_sql( $fields[0] );
			$sql .= " ( NULL, {$form_data['otat_campaign_id']}, '$email', '{$token_list[$i - 1]->otat_token}', '0000-00-00 00:00:00' ),";
		}
	}
	if ( $form_data['otat_assign_email'] ) {
		$sql = trim( $sql, ',') . ';';
		$affected_token_rows = $wpdb->query( $sql );
	}

	// Write extended file to file system and provide a download link.
	$extended_csv_filename = str_replace( '.csv', '-tokenized.csv', $form_data['csv-file'] );
	$written_new_file = file_put_contents( $extended_csv_filename, implode( "\n", $csv_lines ) );
	if ( false === $written_new_file )
		print '<div class="error"><p>' . __( 'Error while trying to write to CSV file!', 'otat' ) . '</p></div>';
	else {
		$otat_settings = get_option( 'otat' );
		$dir = $otat_settings['upload_dir'];
		$filename = $dir['url'] . '/' . basename( $extended_csv_filename );
		print '<div class="updated"><p>' . sprintf( 
			__( 'Appending tokens to uploaded CSV file was successful: <a href="%s">Download CSV File</a>.', 'otat' ),
			$filename
		) . '</p></div>';
	}

	// Update original token table with email addresses assigned to each token if
	// we need the email address when access happens to a tokenized page.
	if ( $form_data['otat_assign_email'] ) {
		$sql  = "UPDATE `{$wpdb->prefix}otat_tokens` orig ";
		$sql .= "JOIN `{$wpdb->prefix}TMP_otat_tokens` temp ";
		$sql .= "   ON orig.otat_token = temp.otat_token ";
		$sql .= "SET orig.otat_email = temp.otat_email;";
		$updated_rows = $wpdb->query( $sql ); // ca. 5 sec bei 26.600 Records
		if ( $updated_rows == $affected_token_rows ) {
			$wpdb->query( "DROP TABLE `{$wpdb->prefix}TMP_otat_tokens`;" );
		}	elseif ( 0 === $updated_rows ) {
			print '<div class="updated"><p>' . __( 'These email addresses are already in the database and properly assigned to their tokens.', 'otat' ) . '</p></div>';
		} else if ( $updated_rows > 0 ) {
			print '<div class="updated"><p>' . sprintf(
				__( '%d email addresses changed in CSV file and therefore have been updated in the database.', 'otat' ),
				$updated_rows
			) . '</p></div>';
		} else {
			print '<div class="error"><p>' . __( 'It seems that somthing went wrong with writing the email addresses to the database. We recommend to delete the campaign, then to create a new one and try the email addresses import again.', 'otat' ) . '</p></div>';
		}
	}

	return true;
}


//TODO: Make download directly happen with next 2 functions. They must run
//      before any output.
//      otat_download_csv( $dir['path'] . '/' . basename( $extended_csv_filename ) );
function otat_download_csv( $name_of_csv_file ) {
	header( 'Content-Description: File Transfer' );
	header( 'Content-type: text/csv' );
	header( 'Content-Disposition: attachment; filename=' . basename( $name_of_csv_file ) );
	header( 'Content-Length: ' . filesize( $name_of_csv_file ) );
	header( 'Content-Transfer-Encoding: binary');
	header( 'Expires: 0');
	header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header( 'Pragma: public');

	otat_readfile_chunked( $name_of_csv_file );
}

function otat_readfile_chunked( $name_of_csv_file ) {
	$chunksize = 1*(1024*1024); // how many bytes per chunk
	$buffer = '';
	$handle = fopen( $name_of_csv_file, 'rb');

	if ($handle === false) {
		return false;
	}

	while ( ! feof( $handle ) ) {
		$buffer = fread( $handle, $chunksize );
		print $buffer;
	}

	return fclose( $handle ); 
}

