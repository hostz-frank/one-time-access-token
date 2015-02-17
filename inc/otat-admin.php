<?php
/**
 * File inc/otat-admin.php
 *
 * Part of the WordPress plugin One Time Access Tokens
 * Provide backend functionality like creating lists of tokens.
 * Loaded by Hook "init".
 */

defined( 'OTAT_DIR' ) || die();

/**
 * Care for tables and settings when network blogs are created or deleted.
 */
if ( is_multisite() ) {
	$plugins = get_site_option( 'active_sitewide_plugins');
	if ( isset( $plugins['one-time-access-tokens/one-time-access-tokens.php'] ) ) {
		add_action( 'wpmu_new_blog',    'otat_create_tables_on_new_blog', 10, 6 );
		add_filter( 'wpmu_drop_tables', 'otat_on_delete_blog' );
	}
}

/**
 * Create tables on new blogs when network-activated.
 */
function otat_create_tables_on_new_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
	include_once( OTAT_DIR . 'inc/otat-activation.php' );
	otat_setup_new_blog( $blog_id );
}

/**
 * Delete tables whenever a blog is deleted.
 */
function otat_on_delete_blog( $tables ) {
	global $wpdb;
	$tables[] = "{$wpdb->prefix}otat_tokens";
	$tables[] = "{$wpdb->prefix}otat_campaigns";
	return $tables;
}


/**
 * Provide menu entry; below "Tools".
 */
add_action( 'admin_menu', 'otat_admin_menu' );
function otat_admin_menu() {
	add_submenu_page(
		'tools.php',
		__( 'OT Access Tokens', 'otat' ),
		__( 'OT Access Tokens', 'otat' ),
		'manage_options', // admin rights for now
		OTAT_DIR,
		'otat_admin_page'
	);
}


/**
 * Define the admin page consisting of tabs.
 */
function otat_admin_page() {

	extract( otat_tab_navigation_vars() );
	?>

	<div class="wrap">
		<h2><?php _e( 'One-Time Access Tokens for Premium Content', 'otat' ); ?></h2>

		<h2 class="nav-tab-wrapper">
			<a class="nav-tab<?php print $campaigns_active_class; ?>" href="<?php print $campaigns_url; ?>"><?php _e( 'Campaigns', 'otat' ); ?></a>
			<a class="nav-tab<?php print $export_active_class; ?>" href="<?php print $export_url; ?>"><?php _e( 'Token Export', 'otat' ); ?></a>
			<a class="nav-tab<?php print $help_active_class; ?>" href="<?php print $help_url; ?>"><?php _e( 'Help', 'otat' ); ?></a>
		</h2>
		<?php

		switch ( $current_tab ) {
			case 'export':
				include( OTAT_DIR . 'inc/otat-admin-tab-export.php' );
				break;

			case 'help':
				include_once( OTAT_DIR . 'inc/otat-admin-tab-help.php' );
				call_user_func( apply_filters( 'otat_tab_help', 'otat_tab_help' ) );
				break;

			case 'campaigns':
			default:
				if ( empty( $_GET['action'] ) ) {
					include_once( OTAT_DIR . 'inc/otat-admin-tab-campaigns.php' );
					call_user_func( apply_filters( 'otat_tab_campaigns', 'otat_tab_campaigns' ) );
				} elseif ( in_array( $_GET['action'], array( 'create_campaign', 'update_campaign' ) ) ) {
					include_once( OTAT_DIR . 'inc/otat-admin-tab-campaign.php' );
					call_user_func( apply_filters( 'otat_campaign_form', 'otat_campaign_form' ) );
				} elseif ( $_GET['action'] == 'delete_campaign' ) {
					include_once( OTAT_DIR . 'inc/otat-admin-tab-campaigns.php' );
					otat_delete_campaign_confirm( absint( $_GET['cid'] ) );
				}
		} ?>
	</div><?php
}


/**
 * Provide some useful template vars depending on requested tab.
 */
function otat_tab_navigation_vars() {
	$nav_vars = array();
	$allowed_tabs = otat_get_provided_tabs();
	$current_tab = ( isset( $_GET['tab'] ) && in_array( $_GET['tab'], $allowed_tabs ) ) ? $_GET['tab'] : 'campaigns';

	foreach( $allowed_tabs as $otat_tab ) {
		$nav_vars[$otat_tab . '_active_class'] = ( $otat_tab == $current_tab ) ? ' nav-tab-active' : '';
		$nav_vars[$otat_tab . '_url'] = admin_url( 'tools.php?page=one-time-access-tokens&tab=' . $otat_tab );
	}
	$nav_vars['current_tab'] = $current_tab;

	return $nav_vars;
}


/**
 * Return a list of expected vars to identify the tab which comes in via GET.
 */
function otat_get_provided_tabs() {
	return array( 'campaigns', 'export', 'help' );
}


/**
 * Load jQuery datepicker for date field on the edit campaign form.
 */
if ( ! empty( $_GET['page'] ) && $_GET['page'] == 'one-time-access-tokens' &&
	   ( empty( $_GET['tab']  ) || $_GET['tab']  == 'campaigns' ) )
	add_action( 'admin_enqueue_scripts', 'otat_enqueue_date_picker_on_edit_campaign' );

function otat_enqueue_date_picker_on_edit_campaign() {
	wp_enqueue_script(
		'field-date-js', 
		plugins_url( 'js/field_date.js', dirname(__FILE__) ),
		array( 'jquery', 'jquery-ui-core', 'jquery-ui-datepicker' ),
		OTAT_VERSION,
		true
	);
	wp_enqueue_style(
		'otat-jquery-style',
		plugins_url( '/css/jquery-ui.css', dirname( __FILE__ ) ),
		array(),
		OTAT_VERSION
	);
}


/**
 * Load jAlert for jQuery.
 */
if ( ! empty( $_GET['page'] ) && $_GET['page'] == 'one-time-access-tokens' )
	add_action( 'admin_enqueue_scripts', 'otat_enqueue_jalert' );

function otat_enqueue_jalert() {
	wp_enqueue_script(
		'jalert', 
		plugins_url( 'js/jquery.alerts.js', dirname(__FILE__) ),
		array( 'jquery' ),
		OTAT_VERSION,
		true
	);
	wp_enqueue_style(
		'jalert',
		plugins_url( '/css/jquery.alerts.css', dirname( __FILE__ ) ),
		array(),
		OTAT_VERSION
	);
}


/**
 * Save a campaign and its tokens to the database, after input validation.
 */
add_action( 'load-tools_page_one-time-access-tokens', 'otat_route_actions');

/**
 * Route actions by loading tab based includes.
 */
function otat_route_actions() {
	if ( ! isset( $_REQUEST['action'] ) ) {
		return;
	}

	switch ( $_REQUEST['action'] ) {
		case 'help_access':
			include_once( OTAT_DIR . 'inc/otat-admin-tab-help.php' );
			otat_tab_help_actions();
			break;
		case 'create_test_tokens':
		case 'delete_test_tokens':
			include_once( OTAT_DIR . 'inc/otat-admin-tab-campaigns.php' );
			otat_tab_campaigns_actions();
			break;
		case 'delete_campaign_confirmed':
			include_once( OTAT_DIR . 'inc/otat-admin-tab-campaigns.php' );
			otat_delete_campaign_confirmed( $_POST['cid'] );
			break;
		case 'create_campaign': // GET
		case 'create': // POST overrules GET
		case 'update_campaign':
		case 'update': // POST overrules GET
			include_once( OTAT_DIR . 'inc/otat-admin-tab-campaign.php' );
			otat_tab_campaigns_form_actions();
		default:
			
	}
}


/**
 * Helper function to print a formatted message on top of admin screens.
 *
 * @param string $message
 * @param string $class  One of: updated (default), error, update-nag.
 */
function otat_set_message( $message, $class = 'updated' ) {
	if ( ! strlen( $message ) ) {
		return;
	}
	if ( ! in_array( $class, array( 'updated', 'error', 'update-nag' ) ) ) {
		$class = 'updated';
	}

	$settings = get_option('otat');
	$user = 'user_' . wp_get_current_user()->ID;

	if ( ! isset( $settings['messages'] ) )
		$settings['messages'] = array();
	if ( ! isset( $settings['messages'][$user] ) )
		$settings['messages'][$user] = array();
	if ( ! isset( $settings['messages'][$user]['error'] ) )
		$settings['messages'][$user]['error'] = array();
	if ( ! isset( $settings['messages'][$user]['updated'] ) )
		$settings['messages'][$user]['updated'] = array();
	if ( ! isset( $settings['messages'][$user]['update-nag'] ) )
		$settings['messages'][$user]['update-nag'] = array();

	$settings['messages'][$user][$class][] = $message;

	update_option( 'otat', $settings );
}

add_action( 'admin_notices', 'otat_print_messages' );

function otat_print_messages() {
	$settings = get_option('otat');
	$user = 'user_' . wp_get_current_user()->ID;
	$output = '';
	if ( isset( $settings['messages'][$user] ) && is_array( $settings['messages'][$user] ) ) {
		foreach( $settings['messages'][$user] as $class => $msgs ) {
			$output .= ( count( $msgs ) ) ? "<div class=\"$class\">" : '';
			if ( count( $msgs ) > 1 ) {
				$output .= '<ul>';
				foreach( $msgs as $msg ) {
					$output .= "<li class=\"admin-notice\">$msg</li>";
				}
				$output .= '</ul>';
			} elseif ( count( $msgs ) == 1 ) {
				$output .= "<p>$msgs[0]</p>";
			}
			$output .= ( count( $msgs ) ) ? '</div>' : '';
		}
		unset( $settings['messages'][$user] );
		update_option( 'otat', $settings );
	}
	print $output;
}

