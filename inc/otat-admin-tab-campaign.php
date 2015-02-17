<?php
/**
 * File inc/otat-admin-tab-campaign.php
 *
 * Part of the WordPress plugin One Time Access Tokens.
 * Provide the contents of the admin page's default tab.
 */

defined( 'OTAT_DIR' ) || die();

/**
 * Print the create/ change campaign form onto the screen.
 */
function otat_campaign_form() {
	extract( otat_campaign_form_vars() );
	?>

	<h3><?php echo $heading; ?> <a class="add-new-h2" href="<?php echo $list_campaigns_url; ?>"><?php _e( 'List campaigns', 'otat' ); ?></a></h3>
	<form action="" method="post" id="otat_<?php echo $action; ?>">
		<?php wp_nonce_field( 'otat_campaign_' . $action ); ?>
		<?php if ( isset( $_GET['cid'] ) ): ?>
			<input type="hidden" name="campaign_id" id="campaign_id" value="<?php echo absint( $_GET['cid'] ); ?>">
		<?php endif; ?>
		<input type="hidden" name="action" id="action" value="<?php echo $action; ?>">
		<table class="form-table">
			<tbody>
				<tr>
					<div class="form-field form-required">
						<th scope="row"><label for="otat_campaign_name"><?php _e( 'Name of campaign', 'otat' ); ?>:</label></th>
						<td>
							<input name="otat_campaign_name" type="text" id="otat_campaign_name" value="<?php echo $otat_campaign_name; ?>" class="regular-text" aria-required="true" maxlength="20"<?php print ($action == 'update') ? ' disabled="disabled"' : ''; ?>>
							<p class="description"><?php _e( 'For internal use only. The maximum length is 20 characters.', 'otat' ); ?></p>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="otat_date_until"><?php _e( 'End of campaign on', 'otat' ); ?>:</label></th>
					<td>
						<input type="text" id="otat_date_until" value="<?php echo $otat_date_until; ?>" class="regular-text" name="otat_date_until" aria-required="true">
						<p class="description"><?php _e( 'Date field. Please use this format: "YYYY-MM-DD"', 'otat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="otat_post_id"><?php _e( 'Post ID', 'otat' ); ?>:</label></th>
					<td><input name="otat_post_id" type="number" id="otat_post_id" value="<?php echo $otat_post_id; ?>" aria-required="true">
						<p class="description"><?php _e( 'You\'ll find the ID of the post or page, when you hover over the post\'s edit link with your mouse and look at the URL displayed by your browser.', 'otat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="otat_invalid_redirect_to"><?php _e( 'Expiration redirect', 'otat' ); ?></label></th>
					<td>
						<input name="otat_invalid_redirect_to" type="text" id="otat_invalid_redirect_to" value="<?php echo $otat_invalid_redirect_to; ?>" class="regular-text" aria-required="true">
						<p class="description"><?php _e( 'A root relative (beginning with a slash) or absolute URL. Redirection to this location happens, when the token has been used, then the allowed usage time frame has expired and when the campaign\'s end date has not been reached yet.', 'otat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="otat_access_count"><?php _e( 'Sessions', 'otat' ); ?>:</label></th>
					<td><input name="otat_access_count" type="number" id="otat_access_count" value="<?php echo ( $otat_access_count ) ? $otat_access_count : '1' ; ?>" aria-required="true">
						<p class="description"><?php _e( 'How many times cookie creation is allowed. Defaults to "1" (one-time access).', 'otat' ); ?></p>
					</td>
				</tr>
				<tr>
				<tr>
					<th scope="row"><label for="otat_allowed_access_time"><?php _e( 'Session duration', 'otat' ); ?>:</label></th>
					<td><input name="otat_allowed_access_time" type="number" id="otat_allowed_access_time" value="<?php echo $otat_allowed_access_time; ?>" aria-required="true"> <strong>min</strong>
						<p class="description"><?php _e( 'When this timeframe ends then the cookie will be forced to expire. This is independent of the campaign\'s end.', 'otat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="otat_token_amount"><?php _e( 'Count of tokens to create', 'otat' ); ?>:</label></th>
					<td><input name="otat_token_amount" type="number" id="otat_token_amount" value="<?php echo $otat_token_amount; ?>" aria-required="true"<?php print ($action == 'update') ? ' disabled="disabled"' : ''; ?>>
						<p class="description"><?php _e( '<em>Note:</em> the token count has to match the count of data lines in your CSV export file, which should be: all lines minus 1 (for the expected header line).', 'otat' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php print ($action == 'update') ? __( 'Save Changes', 'otat' ) : __( 'Save', 'otat' ); ?>">
		</p>
	</form> <?php
}


/**
 * Handler of action requests on tab "Campaigns" for updating and creating new campaigns.
 */
function otat_tab_campaigns_form_actions() {
	switch ( $_POST['action'] ) {

		case 'create':
			if ( otat_save_campaign() ) {
				$location = add_query_arg(
					array( 'page' => 'one-time-access-tokens', 'tab' => 'campaigns' ),
					'tools.php'
				);
				// Clear GET params.
				wp_redirect( $location );
				exit;
			}
			break;

		case 'update':
			if ( otat_update_campaign() ) {
				$location = add_query_arg(
					array( 'page' => 'one-time-access-tokens', 'tab' => 'campaigns' ),
					'tools.php'
				);
				// Clear GET params.
				wp_redirect( $location );
				exit;
			}
			break;

		default:

	}
}


/**
 * Provide sanitized form vars to populate the campaign form fields.
 */
function otat_campaign_form_vars() {
	if ( $_GET['action'] == 'create_campaign' ) {
		$vars = otat_populate_campaign_fields_from_post();
		$vars['action'] = 'create';
		$vars['heading'] = __( 'Add New Campaign', 'otat' );
	} elseif ( $_GET['action'] == 'update_campaign' ) {
		$vars = otat_populate_campaign_fields_from_db( $_GET['cid'] );
		$vars['action'] = 'update';
		$vars['heading'] = __( 'Edit Campaign', 'otat' );
	}
	$vars['list_campaigns_url'] = admin_url( 'tools.php?page=one-time-access-tokens&tab=campaigns' );

	return $vars;
}

/**
 * Sanitization of expected form variables from POST.
 */
function otat_populate_campaign_fields_from_post() {
	$vars = array();
	$fields_to_validate = array(
		'otat_date_until', 'otat_campaign_name', 'otat_post_id',
		'otat_invalid_redirect_to', 'otat_allowed_access_time', 'otat_token_amount',
		'otat_access_count'
	);
	foreach ( $fields_to_validate as $field ) {
		$vars[ $field ] = !isset( $_POST[ $field ] ) ? '' : sanitize_text_field( $_POST[ $field ] );
	}
	return $vars;
}

/**
 * Fetch campaign fields for a campaign from the database.
 */
function otat_populate_campaign_fields_from_db( $campaign_id ) {
	global $wpdb;
	$sql  = "SELECT * FROM `{$wpdb->prefix}otat_campaigns` ";
	$sql .= "WHERE ID = %d;";
	$campaign_fields = $wpdb->get_row( $wpdb->prepare( $sql, $campaign_id ) );
//print '<pre>' . print_r($campaign_fields,1); exit;
	return array(
		'otat_date_until' => date( 'Y-m-d', strtotime( substr( $campaign_fields->otat_valid_until_gmt, 0, 10) ) ),
		'otat_campaign_name' => $campaign_fields->otat_campaign_name,
		'otat_post_id' => $campaign_fields->post_id,
		'otat_invalid_redirect_to' => $campaign_fields->otat_invalid_redirect_to,
		'otat_allowed_access_time' => $campaign_fields->otat_allowed_access_time,
		'otat_token_amount' => $campaign_fields->otat_token_amount,
		'otat_access_count' => $campaign_fields->otat_access_count
	);
}


function otat_save_campaign() {
	if ( false === otat_validate_campaign_fields() )
		return false;

	global $wpdb;
	$now = new DateTime();
	$creation_time = $now->format( 'Y-m-d H:i:s' );
	$otat_valid_until_gmt = otat_add_seconds_to_mysql_datetime( $_POST['otat_date_until'], 86399 );
	$user = wp_get_current_user();

	// Insert campaign.
	$sql  = "INSERT INTO `{$wpdb->prefix}otat_campaigns`";
	$sql .= " (`ID`, `post_id`, `otat_campaign_name`, `otat_created_gmt`, `otat_valid_until_gmt`, `otat_invalid_redirect_to`, `otat_token_amount`, `otat_campaign_created_by`, `otat_allowed_access_time`, `otat_access_count` )\nVALUES";
	$sql  = $wpdb->prepare(
		$sql . " ( NULL, %d, %s, %s, %s, %s, %d, %d, %d, %d );",
		$_POST['otat_post_id'],
		$_POST['otat_campaign_name'],
		$creation_time,
		$otat_valid_until_gmt,
		$_POST['otat_invalid_redirect_to'],
		$_POST['otat_token_amount'],
		$user->ID,
		$_POST['otat_allowed_access_time'],
		$_POST['otat_access_count']
	);
	$campaign_created = $wpdb->query( $sql );
	if ( ! $campaign_created ) {
		otat_set_message( __( 'The campaign could not be created due to an unexpected error.', 'otat' ), 'error' );
		return false;
	}

	$campaign_id = $wpdb->get_var( $wpdb->prepare(
		"SELECT ID from `{$wpdb->prefix}otat_campaigns` WHERE `otat_campaign_name` = %s",
		$_POST['otat_campaign_name']
	));
	if ( empty( $campaign_id ) || !( is_numeric( $campaign_id ) && $campaign_id > 0 ) ) {
		otat_set_message(
			sprintf(
				__( 'Campaign ID for "%s" could not be found in database.', 'otat' ),
					$_POST['otat_campaign_name']
			),
			'error'
		);
		return false;
	}

	// Insert tokens.
	$sql  = "INSERT INTO `{$wpdb->prefix}otat_tokens`";
	$sql .= " (`ID`, `campaign_id`, `otat_email`, `otat_token`, `otat_accessed_gmt`)\nVALUES";
	for( $i = 0; $i < (int)$_POST['otat_token_amount']; ++$i ) {
		$token = md5( uniqid( mt_rand(), true ) );
		// We cannot use $wpdb->prepare() here, because applied to thousands of records it's much too slow.
		$sql .= " ( NULL, $campaign_id, '', '$token', '0000-00-00 00:00:00' ),";
	}
	$sql = trim( $sql, ',') . ';';
	$affected_token_rows = $wpdb->query( $sql );

	if ( false === $affected_token_rows ) {
		otat_set_message( __( 'Tokens could not be created due to an unexpected error.', 'otat' ), 'error' );
	}
	if ( $affected_token_rows == (int)$_POST['otat_token_amount'] && $affected_token_rows > 0 ) {
		otat_set_message(
			sprintf(
				_n(
					'The campaign and it\'s %s token have been created.',
					'The campaign and it\'s %s tokens have been created.',
					$_POST['otat_token_amount'],
					'otat'
				),
				$_POST['otat_token_amount']
			),
			'updated'
		);
	}

	return true;
}


function otat_update_campaign() {
	if ( false === otat_validate_campaign_fields() )
		return false;

	global $wpdb;
	$sql  = "UPDATE `{$wpdb->prefix}otat_campaigns` SET ";
	$sql .= "otat_valid_until_gmt = '%s', ";
	$sql .= "post_id = %d, ";
	$sql .= "otat_invalid_redirect_to = '%s', ";
	$sql .= "otat_allowed_access_time = %d, ";
	$sql .= "otat_access_count = %d ";
	$sql .= "WHERE ID = %d;";
	$affected_row = $wpdb->query( $wpdb->prepare( $sql,
		$_POST['otat_date_until'],
		$_POST['otat_post_id'],
		$_POST['otat_invalid_redirect_to'],
		$_POST['otat_allowed_access_time'],
		$_POST['otat_access_count'],
		$_POST['campaign_id']
	) );
//print $affected_row . "<br><pre>".print_r($wpdb,1); exit;
	if ( $affected_row ) {
		otat_set_message( __( 'The campaign has been saved successfully.', 'otat' ), 'updated' );
		return true;
	} else {
		otat_set_message( __( 'The campaign could not be saved due to an unexpected error.', 'otat' ), 'error' );
		return false;
	}
}

/**
 * Take a date in datetime format and add a day. When we say: "until that day"
 * we mean the end (midnight) of that day and not the beginning ("00:00:00").
 *
 * @return  Date in MySQL's datetime format.
 */
function otat_add_seconds_to_mysql_datetime( $date_string, $seconds ) {
	if ( ! strlen( $date_string ) )
		return '';
	$unixtime = strtotime( $date_string ) + $seconds;
	return gmdate( 'Y-m-d H:i:s', absint( $unixtime ) );
}

/**
 * Validation handler for the first tab.
 */
function otat_validate_campaign_fields() {
	$validation = true;

	if ( ! current_user_can( 'manage_options' ) )
		return false;

	check_admin_referer( 'otat_campaign_' . $_POST['action'] );

	if ( ( $_POST['action'] == 'create' && empty( $_POST['otat_campaign_name'] ) )
		|| empty( $_POST['otat_post_id'] ) || empty( $_POST['otat_invalid_redirect_to'] )
		|| ( $_POST['action'] == 'create' && empty( $_POST['otat_token_amount'] ) )
		|| empty( $_POST['otat_date_until'] ) )
	{
		otat_set_message( __( 'All fields are required!', 'otat' ), 'error' );
		$validation = false;
	}

	// When we say "until midnight" we mean until midnight of that day rather than
	// to refer to it's beginning (00:00 a.m.). So we add a DAY_IN_SECONDS to Unix time.
	if ( ! empty( $_POST['otat_date_until'] ) && ( strtotime( $_POST['otat_date_until'] ) + DAY_IN_SECONDS ) < time() ) {
		otat_set_message( __( 'Campaign\'s end date must be after today!', 'otat' ), 'error' );
		$validation = false;
	}

	if ( strlen( $_POST['otat_campaign_name'] ) && $_POST['action'] != 'update' ) {
		global $wpdb;
		$campaign_name_exists = $wpdb->get_var( $wpdb->prepare( 
			"SELECT COUNT(*) FROM `{$wpdb->prefix}otat_campaigns` WHERE `otat_campaign_name` = '%s';", $_POST['otat_campaign_name']
		) );
		if ( $campaign_name_exists ) {
			otat_set_message( __( 'There is a campaign using this name already, please choose another one.', 'otat' ), 'error' );
			$validation = false;
		}
	}

	if ( isset( $_POST['otat_allowed_access_time'] ) && $_POST['otat_allowed_access_time'] != '' && ( absint( $_POST['otat_allowed_access_time'] ) <= 0 ) ) {
		otat_set_message( __( 'The allowed time frame to access a page must be at least 1 minute after using the tokenized URL.', 'otat' ), 'error' );
		$validation = false;
	}

	if ( (int)$_POST['otat_token_amount'] > 99999 ) {
		otat_set_message( __( 'The maximum token count you can create within one campaign is 99.999 tokens.', 'otat' ), 'error' );
		$validation = false;
	}

	if ( (int)$_POST['otat_access_count'] < 1 ) {
		otat_set_message( __( "You have to allow at least 1 session.", 'otat' ), 'error' );
		$validation = false;
	}

	return $validation;
}
