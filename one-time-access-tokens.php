<?php
/**
 * Plugin Name: 0ne time Access Tokens
 * Plugin URI: https://github.com/medizinmedien/one-time-access-tokens
 * Description: Create (lots of) tokens to be supplied for a specific post. The moment you access the post (or page) the token was created for, it initiates a session cookie and becomes invalid instantly. Accessing the post again (without a valid cookie set) causes a configurable redirect.
 * Author: Frank St&uuml;rzebecher
 * Author URI: http://medonline.at
 * Text Domain: otat
 * Domain Path: /lang
 * Version: 0.91
 */

defined( 'ABSPATH' ) || die();

define( 'OTAT_DIR', plugin_dir_path( __FILE__ ) );
define( 'OTAT_VERSION', '0.7' );


add_action( 'init', 'otat_init' );
register_activation_hook( __FILE__, 'otat_load_activation' );

/**
 * Init options. Load logic depending on context.
 */
function otat_init() {
	// Load settings.
	$otat_settings = get_option( 'otat', array() );
	add_option( 'otat', $otat_settings );

	// Dispatch.
	if( ! is_admin() ) {
		load_plugin_textdomain( 'otat-front', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
		include( OTAT_DIR . 'inc/otat-frontend.php' );
	} else {
		load_plugin_textdomain( 'otat', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
		include( OTAT_DIR . 'inc/otat-admin.php' );
	}
}


/**
 * On activation: create plugin tables or - by dbDelta() - modify existing ones
 * if there are any schema changes.
 */
function otat_load_activation( $network_wide ) {
	include( 'inc/otat-activation.php' );
	otat_activation_run( $network_wide );
}


/**
 * Determine if a given post is currently token protected.
 *
 * This function should be used to offtake other plugins's active content
 * protection in case it returns TRUE. OTAT cannot work if it is not allowed to
 * give access to non-public content when valid token requests happen.
 *
 * The function should preferably run on hook "template_redirect".
 *
 * @return bool
 */
function is_otat_protected_post( $post_id = NULL ) {
	if ( is_null( $post_id ) ) {
		global $post;
		if ( ! is_object( $post ) )
			return false;
		$post_id = $post->ID;
	}
	$otat_settings = otat_renew_option_protected_posts();
	$otat_protected_posts = (array)$otat_settings['protected_posts'];

	if ( ! count( $otat_protected_posts ) )
		return false;

	if ( isset( $otat_protected_posts[ $post_id ] ) ) {
		return ( $otat_protected_posts[ $post_id ] > time() );
	}

	return false;
}


/**
 * Create or update 'protected_posts' key of OTAT settings.
 *
 * @return array $otat_settings  As they would returned by "get_option('otat')."
 */
function otat_renew_option_protected_posts() {
	global $wpdb;
	$otat_settings = get_option( 'otat', array() );

	// Reset key 'protected_posts' to get rid of expired entries.
	$otat_settings['protected_posts'] = array();

	$otat_protected_posts = $wpdb->get_results(
		"SELECT post_id, UNIX_TIMESTAMP(otat_valid_until_gmt) as valid_until " .
		"FROM `{$wpdb->prefix}otat_campaigns` " .
		"WHERE otat_valid_until_gmt > NOW();"
	);

	if ( is_array( $otat_protected_posts ) && count( $otat_protected_posts ) ) {
		// When multiple campaigns run on the same post, we need to sort
		// by 'valid_until' to retrieve the latest expiration time for a post.
		usort( $otat_protected_posts, function( $post_a, $post_b ) {
			if ( $post_a->valid_until == $post_b->valid_until )
				return 0;
			return ( $post_a->valid_until < $post_b->valid_until ) ? -1 : 1;
		});
		foreach( $otat_protected_posts as $otat_post ) {
			$otat_settings['protected_posts'][$otat_post->post_id] = $otat_post->valid_until;
		}
	}

	update_option( 'otat', $otat_settings );

	return $otat_settings;
}


// TODO: Create capability to 'manage_ot_access_tokens'.

// TODO: Fix a bug - error messages fire to late and not prior to hook admin_notices

// TODO: Add a functionality check to make sure the plugin will work (see if we
//       can load an otat protected post by curl and read the page contents by
//       programmatically assuming the token is valid). Handle test urls also
//       there, and separate this handling from listing of campaigns.

// TODO: Batch process expensive actions, maybe with class batchProcessHelper.
//       see: https://github.com/INN/wordpress-batch-process-helper/blob/master/batchProcessHelper.php
//       see: http://nerds.investigativenewsnetwork.org/2014/11/07/batch-processing-data-with-wordpress-via-http/

