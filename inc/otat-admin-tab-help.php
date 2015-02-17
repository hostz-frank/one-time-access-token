<?php
/**
 * File inc/otat-admin-tab-help.php
 *
 * Part of the WordPress plugin One Time Access Tokens.
 * Provide help.
 */

defined( 'OTAT_DIR' ) || die();

function otat_tab_help() { ?>
	<h3><?php _e( 'Help', 'otat' ); ?></h3>
	<p><a href="http://vimeo.com/115852955">Vimeo-Video: Einmal-Zugriffstoken für nicht öffentliche Beiträge</a></p>
	<iframe src="//player.vimeo.com/video/115852955" width="500" height="281" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe><?php
}

function otat_tab_help_actions() {
	otat_tab_help();
}

