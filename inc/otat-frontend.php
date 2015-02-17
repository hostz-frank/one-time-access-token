<?php
/**
 * File inc/otat-frontend.php
 *
 * Part of the WordPress plugin One Time Access Tokens.
 * Provide frontend functionality like access checks and redirecting away from
 * tokenized posts in case a token has been used already and is not valid any more.
 */

defined( 'OTAT_DIR' ) || die();

if ( empty( $_GET['otat'] ) && empty( $_COOKIE['otat'] ) ) {
	add_action( 'template_redirect', 'otat_redirect_unauthorized', 0 );
} else {
	add_action( 'template_redirect', 'otat_auth_access', 0 );
}


/**
 * Handler of maybe tokenized posts without incoming otat vars from GET or COOKIE.
 */
function otat_redirect_unauthorized() {
	admin_debug( 'NO TOKEN GIVEN BY URL OR COOKIE.<br>' );
	if ( is_user_logged_in() ) {
		admin_debug( 'USER IS LOGGED IN.<br>RETURN.<br>' );
		return;
	}
	admin_debug( 'NOT A LOGGED IN USER.<br>' );
	if ( ! is_otat_protected_post() ) {
		admin_debug( 'THIS IS NOT A OTAT PROTECTED POST<br>RETURN<br>' );
		return;
	}
	admin_debug( 'ABOUT TO VIEW AN OTAT PROTECTED POST.<br>' );

	if ( is_singular() ) {
		admin_debug( 'IS SINGULAR.<br>' );
		global $post, $wpdb;
		$otat_post_id = absint( $post->ID );
		$maybe_relative_path = $wpdb->get_var(
			"SELECT otat_invalid_redirect_to " .
			"FROM `{$wpdb->prefix}otat_campaigns` " .
			"WHERE post_id = $otat_post_id AND otat_valid_until_gmt > NOW();"
		);
		$redirect_page = otat_build_sanitized_redirect_url( $maybe_relative_path );
		admin_debug( 'REDIRECTING TO <a href="' . $redirect_page . '">' . $redirect_page . '</a>', true );
		wp_redirect( $redirect_page );
		exit;
	} else {
		admin_debug( 'IS NOT A SINGLE PAGE.<br>REPLACE CONTENT WITH A NON-AUTHORIZED MESSAGE.<br>' );
		// A tokenized post in archive listings.
		add_filter( 'the_content', 'otat_replace_content');
		add_filter( 'the_excerpt', 'otat_replace_content');
	}
}

/**
 * Compose full path for feeding wp_redirect().
 */
function otat_build_sanitized_redirect_url( $maybe_path_only ) {
	$redirect_page = trim( $maybe_path_only );

	if ( 0 !== strpos( $redirect_page, 'http' ) ) {
		// Relative URL, maybe even without leading slash.
		if ( 0 === strpos( $redirect_page, '/' ) ) {
			$redirect_page = esc_url( home_url() . $redirect_page );
		} else {
			$redirect_page = esc_url( home_url() . '/' . $redirect_page );
		}
	}

	return $redirect_page;
}

/**
 * Handler of token related GET or COOKIE requests.
 */
function otat_auth_access () {
	admin_debug( 'TOKEN GIVEN BY URL OR COOKIE.<br>' );
	if ( is_user_logged_in() ) {
		admin_debug( 'USER IS LOGGED IN.<br>RETURN<br>' );
		return;
	}

	if ( ! is_otat_protected_post() ) {
		admin_debug( 'NOT VIEWING TOKEN PROTECTED CONTENT.<br>RETURN.<br>' );
		return;
	}

	// Cookie first: no check for valid $_GET['otat'] when $_COOKIE['otat'][$post->ID] is set.
	global $post;
	if ( isset( $_COOKIE['otat'] ) && isset( $_COOKIE['otat'][$post->ID] ) ) {
		admin_debug( 'TOKEN COMES FROM COOKIE.<br>' );
		$otat = sanitize_text_field( $_COOKIE['otat'][$post->ID] );
		$access_count_check = '';
	} elseif ( isset( $_GET['otat'] ) ) {
		admin_debug( 'TOKEN COMES FROM URL.<br>' );
		$otat = sanitize_text_field( $_GET['otat'] );

		// Cookie not set: force check for allowed times to set an access cookie.
		$access_count_check = ' AND c.otat_access_count > t.otat_counter';

	} else {
		admin_debug( 'NOT A TOKEN FOR THIS POST => DYING WITH MESSAGE: "Sorry: this link is not valid."<br>', true );
		wp_die( __( 'Sorry: this link is not valid.', 'otat-front' ) );
	}


	global $wpdb;
	$sql  = "SELECT * FROM `{$wpdb->prefix}otat_tokens` AS t ";
	$sql .= "INNER JOIN `{$wpdb->prefix}otat_campaigns` AS c ";
	$sql .= "ON t.campaign_id = c.ID ";
	$sql  = $wpdb->prepare( $sql . "WHERE t.otat_token = '%s'$access_count_check;", $otat );
	$token_info = $wpdb->get_row( $sql );

	if ( empty( $token_info ) ) {
		admin_debug( 'TOKEN NOT FOUND IN DB => DYING WITH MESSAGE: "Sorry: this link is not valid."<br>', true );
		// Token not in database, maybe an old tokenized link of a meanwhile deleted campaign.
		wp_die( __( 'Sorry: this link is not valid.', 'otat-front' ) );
		exit;
	} elseif ( isset( $token_info->post_id ) && ( (int)$token_info->post_id != $post->ID ) && is_otat_protected_post( $post->ID ) ) {
		admin_debug( 'A TOKEN WAS FOUND IN DB WHILE IT DOES NOT BELONG TO THIS POST.<br>THIS POST IS ALSO TOKENIZED: REPLACING CONTENT WITH NON-AUTHORIZED MESSAGE.<br>RETURN<br>' );
		// Token from Cookie has not been created for this post.
		add_filter( 'the_content', 'otat_replace_content');
		add_filter( 'the_excerpt', 'otat_replace_content');
		return;
	}

	$now = new DateTime();
	$now_gmt = $now->format( 'Y-m-d H:i:s' );
	$allowed_timeframe_sec = $token_info->otat_allowed_access_time * MINUTE_IN_SECONDS;
	$not_expired = ( strtotime( $token_info->otat_accessed_gmt ) + $allowed_timeframe_sec ) > strtotime( $now_gmt );

	if ( isset( $_COOKIE['otat'][$post->ID] ) ) {
		if ( $not_expired ) {
			$left = strtotime( $token_info->otat_accessed_gmt ) + $allowed_timeframe_sec - strtotime( $now_gmt );
			admin_debug( 'COOKIE NOT EXPIRED, VALID UNTIL ' . date('d.m.Y H:i', time() + $allowed_timeframe_sec) . '<br>PROVIDE TOKEN INFO FOR OTHER PLUGINS AND LET THEM ACT.<br>RETURN.<br>' );
			// Provide $token_info for other plugins to take action based on it.
			// Do not change; action used already here: https://github.com/medizinmedien/medonline-bright-customizations/commit/e5ab7cace5364b3a8b697c68a20b4f2ff944afb7
			do_action( 'otat_authenticated', $token_info );
			return;
		} elseif ( $token_info->post_id == $post->ID ) {
			admin_debug( 'COOKIE IS EXPIRED.<br>THIS IS THE POST THE EXPIRED TOKEN BELONGS TO.<br>UNSETTING COOKIE.<br>' );
			otat_unset_cookie( $token_info, $post->ID );
		}
	} else {
		// Cookie not set yet, token comes from GET.

		// Set cookie, update db.
		admin_debug( 'IT IS THE ' . $token_info->otat_counter + 1 . '. ACCESS.<br>' );
		if ( $token_info->otat_valid_until_gmt > $now_gmt ) {
			admin_debug( 'TOKEN NOT EXPIRED: SETTING COOKIE & UPDATING DB.<br>PROVIDE TOKEN INFO FOR OTHER PLUGINS AND LET THEM ACT.<br>RETURN.<br>' );
			otat_set_cookie( $token_info, $post->ID );
			$sql  = $wpdb->prepare( "UPDATE `{$wpdb->prefix}otat_tokens` SET `otat_accessed_gmt` = '$now_gmt', `otat_counter` = `otat_counter` + 1 WHERE otat_token = '%s'", $otat );
			$update = $wpdb->query( $sql );
			if ( ! $update ) {
				error_log( 'Values "otat_accessed_gmt" and/or "otat_counter" could not be updated in db while accessing token no.: ' . $otat );
			}
			// Provide information about the otat token object for other plugins.
			// Do not change; action used already here: https://github.com/medizinmedien/medonline-bright-customizations/commit/e5ab7cace5364b3a8b697c68a20b4f2ff944afb7
			do_action( 'otat_authenticated', $token_info );
			return;
		}
	}
	$redirect_url = otat_build_sanitized_redirect_url( $token_info->otat_invalid_redirect_to );
	admin_debug( 'REDIRECT TO <a href="' . $redirect_url . '">' . $redirect_url . '</a>.<br>', true );
	wp_redirect( $redirect_url );
	exit;
}


function otat_set_cookie( $token_info, $post_id ) {
	$post_id = absint( $post_id );
	$name = "otat[$post_id]";
	$value = $token_info->otat_token;
	$time = time() + ( $token_info->otat_allowed_access_time * MINUTE_IN_SECONDS );
	$https_only = apply_filters( 'otat_force_https_cookie', false );
	$is_cookie_set = setcookie( $name, $value, $time, '/', '', $https_only, true );
	if ( false === $is_cookie_set ) {
		error_log( 'otat cookie could not be set with:' . print_r( $token_info, 1 ) );
	}
}

function otat_unset_cookie( $token_info, $post_id ) {
	$post_id = absint( $post_id );
	$name = "otat[$post_id]";
	$value = NULL;
	$time = time() - YEAR_IN_SECONDS;
	$cookie_unset = setcookie( $name, $value, $time, '/' );
	if ( false === $cookie_unset ) {
		error_log( 'otat cookie could not be unset with:' . print_r( $token_info, 1 ) );
	}
}

/*
EXAMPLE: stdClass Object $token_info
(
    [ID] => 7
    [campaign_id] => 7
    [otat_email] => user@example.com
    [otat_token] => 31abc9957a9ff47a608f6c71c2e84999
    [otat_accessed_gmt] => 0000-00-00 00:00:00
    [post_id] => 12
    [otat_campaign_name] => tmp
    [otat_created_gmt] => 2014-11-22 01:58:31
    [otat_valid_until_gmt] => 2014-12-01 00:00:00
    [otat_invalid_redirect_to] => /failure
    [otat_token_amount] => 26607
    [otat_campaign_created_by] => 1
    [otat_allowed_access_time] => 30
)
*/


/**
 * Should run on filter 'the_content'.
 */
function otat_replace_content( $content ) {
	if ( !is_user_logged_in() && is_otat_protected_post() && !otat_matches_current_post() )
		return __( 'Sorry, this post is available for registered users only.', 'otat-front' );
	return $content;
}

function otat_matches_current_post( $post_id = NULL ) {
	$token = otat_get_guest_token();
	if ( ! $token )
		return false;

	if ( is_null( $post_id ) )
		$post_id = get_the_ID();

	if ( otat_verify_db_token( $token, $post_id ) )
		return true;
	else
		return false;
}


/**
 * Retrieves the token from GET or COOKIE.
 *
 * @return string Token | boolean FALSE
 */
function otat_get_guest_token() {
	global $post;
	if ( isset( $_COOKIE['otat'][$post->ID] ) ) {
		return sanitize_text_field( $_COOKIE['otat'][$post->ID] );
	} elseif ( isset( $_GET['otat'] ) )
		return sanitize_text_field( $_GET['otat'] );
	else
		return false;
}

/**
 * Verifiy retrieved token from guest user against db. Make sure, the token
 * was made for the current post.
 * Check if the campaign is still active not done here (comes from settings). ???
 * Check for valid time frame also not done here. ???
 *
 * @return integer Amount of valid rows from db call. "0" => FALSE; "1" => TRUE.
 */
function otat_verify_db_token( $guest_token, $current_post_id ) {
	global $wpdb;

	$query_expansion = isset( $_COOKIE['otat'][$current_post_id] ) ? '' : ' AND t.otat_accessed_gmt = 0';

	$sql  = "SELECT * FROM `{$wpdb->prefix}otat_tokens` AS t ";
	$sql .= "INNER JOIN `{$wpdb->prefix}otat_campaigns` AS c ";
	$sql .= "ON t.campaign_id = c.ID ";
	$sql  = $wpdb->prepare( $sql . "WHERE t.otat_token = '%s' " .
		"AND c.post_id = %d$query_expansion;", $guest_token, $current_post_id );

	return $wpdb->query( $sql );
}

/**
 * With a special cookie it is possible to follow the applied front end logic
 * for debugging purposes.
 */
function admin_debug( $msg, $exit_before_redirect = false ) {
	if ( isset( $_COOKIE['otat_debug'] ) ) {
		print $msg;
		if ( $exit_before_redirect )
			exit;
	}
}
