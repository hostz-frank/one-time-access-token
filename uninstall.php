<?php
/**
 * File uninstall.php
 *
 * Part of the WordPress plugin One Time Access Tokens.
 * Provide uninstall routine. Must be called from inside WordPress.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || die();

global $wpdb;

// Delete plugin tables.
$tables  = $wpdb->get_col( "SHOW TABLES LIKE '%\_otat\_campaigns';" );
$tables += $wpdb->get_col( "SHOW TABLES LIKE '%\_otat\_tokens';" );
$sql = '';
foreach( $tables as $table ) {
	$sql .= "DROP TABLE IF EXISTS `$table`; ";
}
$wpdb->query( trim( $sql ) );

// Delete options & transients.
if ( is_multisite() ) {
	$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs};" );
	$main_id = $wpdb->blogid;
	foreach ( $blog_ids as $id ) {
		switch_to_blog( $id );
		otat_delete_options()
	}
	switch_to_blog( $main_id );
} else {
	// Single site mode.
	otat_delete_options();
}

function otat_delete_options() {
	delete_option( 'otat' );
	delete_option( 'otat_upload' );
	delete_transient( 'otat_uploaded_csv_file' );
}

