<?php
/**
 * File inc/otat-activation.php
 *
 * Part of the WordPress plugin One Time Access Tokens.
 * Handles activation tasks, also for multisite: like creating tables on single
 * site or network activation, adding/ removing tables on created/ deleted blogs.
 */

defined( 'OTAT_DIR' ) || die();

function otat_activation_run( $network_wide ) {
	global $wpdb;
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	if ( is_multisite() && $network_wide ) {
		$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs};" );
		$main_id = $wpdb->blogid;

		foreach ( $blog_ids as $id ) {
			switch_to_blog( $id );
			// Read tables inside the loop to renew their prefixes.
			dbDelta( otat_get_tables() );
			otat_update_tables();
		}
		switch_to_blog( $main_id );
	} else {
		// Single site mode.
		dbDelta( otat_get_tables() );
		otat_update_tables();
	}

	otat_set_options();
}


function otat_get_tables() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	// Campaigns
	$sql = "CREATE TABLE {$wpdb->prefix}otat_campaigns (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  post_id bigint(20) unsigned NOT NULL default 0,
  otat_campaign_name varchar(20) NOT NULL DEFAULT '',
  otat_created_gmt datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  otat_valid_until_gmt datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  otat_invalid_redirect_to varchar(255) NOT NULL DEFAULT '',
  otat_token_amount bigint(20) unsigned NOT NULL default 0,
  otat_campaign_created_by bigint(20) unsigned NOT NULL default 0,
  otat_allowed_access_time bigint(20) unsigned NOT NULL default 0,
  otat_access_count bigint(20) unsigned NOT NULL default 0,
  PRIMARY KEY  (ID),
  KEY otat_campaign_name (otat_campaign_name),
  KEY otat_created_gmt (otat_created_gmt)
) $charset_collate;";

	// Tokens
	$sql .= "CREATE TABLE {$wpdb->prefix}otat_tokens (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  campaign_id bigint(20) unsigned NOT NULL default 0,
  otat_email varchar(100) NOT NULL DEFAULT '',
  otat_token varchar(64) NOT NULL DEFAULT '',
  otat_accessed_gmt datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  otat_counter bigint(20) unsigned NOT NULL default 0,
  PRIMARY KEY  (ID),
  KEY otat_token (otat_token),
  KEY otat_email (otat_email)
) $charset_collate;";

	return $sql;
}


function otat_set_options() {
	otat_renew_option_protected_posts();
}

function otat_setup_new_blog( $blog_id ) {
	if ( is_plugin_active_for_network( 'one-time-access-tokens/one-time-access-tokens.php' ) ) {
		switch_to_blog( $blog_id );
		require_once( ABSPATH  . 'wp-admin/includes/upgrade.php' );
		dbDelta( otat_get_tables() );
		restore_current_blog();
	}
}

function otat_update_tables() {
	global $wpdb;

	// Update v0.7 to v0.8: added field "otat_campaigns.otat_access_count"
	$sql  = "UPDATE `{$wpdb->prefix}otat_campaigns` SET ";
	$sql .= "otat_access_count = 1 ";
	$sql .= "WHERE otat_access_count = 0;";
	$affected_rows = $wpdb->query( $sql );

}
