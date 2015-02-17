<?php
/**
 * File inc/otat-admin-tab-campaigns.php
 *
 * Part of the WordPress plugin One Time Access Tokens.
 * Create, update and delete campaigns and their access tokens.
 */

defined( 'OTAT_DIR' ) || die();

function otat_tab_campaigns() {
	$create_url = add_query_arg( array('page' => 'one-time-access-tokens', 'tab' => 'campaigns', 'action' => 'create_campaign' ) );
	?>

	<h3><a class="add-new-h2" href="<?php echo $create_url; ?>"><?php _e( 'Add New', 'otat' ); ?></a></h3>
	<?php

	global $wpdb;
	$campaigns = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}otat_campaigns ORDER BY ID DESC;" );
	if ( ! $campaigns ): ?>
		<div class="error"><p><?php _e( 'No campaigns found.', 'otat' ); ?></p></div><?php
		return;
	endif; ?>

	<table class="wp-list-table widefat">
		<thead>
		<tr>
			<th><?php _e( 'Campaign title', 'otat' ); ?></th>
			<th><?php _e( 'Expires on', 'otat' ); ?></th>
			<th><?php _e( 'Token-protected Post', 'otat' ); ?></th>
			<th><?php _e( 'Sessions / duration', 'otat' ); ?></th>
			<th><?php _e( 'Redirect after expired access', 'otat' ); ?></th>
			<th><?php _e( 'Token count', 'otat' ); ?></th>
			<th><?php _e( 'Created on/by', 'otat' ); ?></th>
		</tr>
		</thead>
		<tfoot>
		<tr>
			<th><?php _e( 'Campaign title', 'otat' ); ?></th>
			<th><?php _e( 'Expires on', 'otat' ); ?></th>
			<th><?php _e( 'Token-protected Post', 'otat' ); ?></th>
			<th><?php _e( 'Sessions / duration', 'otat' ); ?></th>
			<th><?php _e( 'Redirect after expired access', 'otat' ); ?></th>
			<th><?php _e( 'Token count', 'otat' ); ?></th>
			<th><?php _e( 'Created on/by', 'otat' ); ?></th>
		</tr>
		</tfoot>
		<tbody> <?php

		$alternate_row_color = '';
		foreach( $campaigns as $campaign ):
			$post = get_post( $campaign->post_id );
			$expired_campaign = strtotime( $campaign->otat_valid_until_gmt ) < time();
			$alternate_row_color = ( $alternate_row_color == '' ) ? ' class="alt"' : ''; ?>
			<tr<?php print $alternate_row_color; ?>>
				<td class="post-title page-title column-title"><strong><?php print esc_html( $campaign->otat_campaign_name ); ?></strong>
					<div class="row-actions">
						<a href="<?php print admin_url( 'tools.php?page=one-time-access-tokens&tab=campaigns&action=delete_campaign&cid='.(int)$campaign->ID ); ?>"><?php esc_html_e( 'Delete', 'otat' ); ?></a> |
						<a href="<?php print admin_url( 'tools.php?page=one-time-access-tokens&tab=campaigns&action=update_campaign&cid='.(int)$campaign->ID ); ?>"><?php esc_html_e( 'Edit', 'otat' ); ?></a> |
						<?php if ( $post ): ?>
							<?php if ( $expired_campaign ): ?>
								<em  style="color:#f77;"><?php esc_html_e( 'Campaign expired!', 'otat' ); ?></em>
							<?php else: ?>
								<a href="<?php echo otat_nonce_url( 'create_test_tokens', $campaign->ID ); ?>"><?php esc_html_e( 'Create test links', 'otat' ); ?></a>
							<?php endif; ?>
						<?php else: ?>
							<em  style="color:#f77;"><?php esc_html_e( 'Post ID not valid!', 'otat' ); ?></em>
						<?php endif; ?>
					</div>
				</td>
				<td>
					<?php if ( $expired_campaign ): ?><span style="color: red;"><?php endif; ?>
					<?php print date( 'd.m.Y', strtotime( $campaign->otat_valid_until_gmt ) ); ?>
					<?php if ( $expired_campaign ): ?></span><?php endif; ?>
				</td>
				<td>
					<?php if( $post ): ?>
						<a title="<?php esc_attr_e( 'View', 'otat' ); ?>" href="<?php echo get_permalink( $campaign->post_id ); ?>" target="_blank"><?php echo get_the_title( $campaign->post_id ); ?></a>
						<div class="row-actions">
							<a href="<?php echo get_permalink( $campaign->post_id ); ?>" target="_blank"><?php esc_html_e( 'View', 'otat' ); ?></a> |
							<a href="<?php echo admin_url( 'post.php?post='. absint( $campaign->post_id ) .'&action=edit' ); ?>" target="_blank"><?php esc_html_e( 'Edit', 'otat' ); ?></a>
						</div>
					<?php else: ?>
						<span style="color:red;"><?php esc_html_e( 'Post ID not valid!', 'otat' ); ?></span>
					<?php endif; ?>
				</td>
				<td><?php print intval( $campaign->otat_access_count ); ?> / <?php print esc_html( $campaign->otat_allowed_access_time ); ?> min</td>
				<td><a href="<?php echo esc_url( $campaign->otat_invalid_redirect_to ); ?>" target="_blank">
					<?php print esc_html( $campaign->otat_invalid_redirect_to ); ?></a></td>
				<td><?php print esc_html( $campaign->otat_token_amount ); ?></td>
				<td><?php printf( __( '%s<br>by %s', 'otat' ),
						date( 'd.m.Y', strtotime( $campaign->otat_created_gmt ) ),
						esc_html( get_user_by( 'id', $campaign->otat_campaign_created_by )->data->user_login )
					); ?>
				</td>
			</tr> <?php
		endforeach; ?>
		</tbody>
	</table>
	<hr><br> <?php

	$test_tokens = otat_fetch_unused_test_tokens_from_db(); ?>

	<h3 id="test-section"><?php _e( 'Testing tokenized links', 'otat' ); ?></h3> <?php
	if ( ! count( $test_tokens ) ) { ?>
		<p><?php _e( 'No unused test links found.', 'otat' ); ?></p><?php
		return;
	} else { ?>
		<p><a href="<?php echo otat_nonce_url( 'delete_test_tokens'); ?>"><?php _e( 'Delete all test links (used and unused).', 'otat' ); ?></a></p> <?php
	} ?>
	<div id="poststuff"> <?php

	$prev_campaign_name = '';
	foreach( $test_tokens as $test_token ) {
		$current_campaign_name = otat_get_campaign_name_by_id( $test_token->campaign_id );
		if ( $prev_campaign_name != $current_campaign_name ) {
			echo ( empty( $prev_campaign_name ) ) ? '' : "\t\t\t\t\t</ul>\n\t\t\t\t</div>\n\t\t\t</div>";
			$prev_campaign_name = $current_campaign_name; ?>
			<div class="postbox">
				<h3 class="hndle"><?php _e( 'Unused test links for campaign:', 'otat' ); ?> "<?php echo $current_campaign_name; ?>"</h3>
				<div class="inside">
					<p class="important-hint"><?php _e( 'You have to be <strong>logged out</strong> to make the following test links work:', 'otat' ); ?></p>
					<ul class="otat_test_urls_list inside"> <?php
		} ?>
		<li class="otat_test_link">
			<?php echo esc_url( otat_get_post_permalink_by_campaign_id( $test_token->campaign_id ) . '?otat=' . $test_token->otat_token ); ?>
		</li> <?php
	} ?>
					</ul>
				</div>
			</div> <?php
	//print '<pre>' . print_r( $test_tokens, 1 ) . '</pre>';
}

function otat_nonce_url( $action, $campaign_id = 0 ) {
	$tab = ( isset( $_GET['tab'] ) && in_array( $_GET['tab'], otat_get_provided_tabs() ) ) ? $_GET['tab'] : 'campaigns';
	$otat_url = add_query_arg( array( 'page' => 'one-time-access-tokens', 'tab' => $tab, 'action' => $action, 'cid' => absint( $campaign_id ) ) );
	$nonced_url = wp_nonce_url( $otat_url, 'otat_' . $action . '_' . absint( $campaign_id ) );
	return $nonced_url;
}


function otat_fetch_unused_test_tokens_from_db() {
	global $wpdb;
	return $wpdb->get_results(
		"SELECT campaign_id, otat_token FROM `{$wpdb->prefix}otat_tokens` " .
		"WHERE otat_email = 'user@example.com' AND otat_accessed_gmt = 0 " .
		"ORDER BY campaign_id DESC;"
	);
}


/**
 * Submit handler for action links on tab "Campaigns".
 */
function otat_tab_campaigns_actions() {
	switch ( $_GET['action'] ) {

		case 'create_test_tokens':
			if ( ! isset( $_GET['cid'] ) )
				break;
			$cid = absint( $_GET['cid'] );
			if ( false === otat_create_test_links( $cid ) ) {
				otat_set_message( __('An error occured while trying to create test tokens.', 'otat'), 'error' );
			} else {
				otat_set_message( __('5 tokenized URLs have been created for testing (see <a href="#test-section">below</a>).', 'otat'), 'update' );
				$location = add_query_arg(
					array( 'page' => 'one-time-access-tokens', 'tab' => 'campaigns' ),
					'tools.php'
				);
				// Clear GET params.
				wp_redirect( $location );
				exit;
			}
			break;

		case 'delete_test_tokens':
			if ( false === otat_delete_test_tokens() ) {
				otat_set_message( __('An error occured while trying to delete all test tokens.', 'otat' ), 'error' );
			} else {
				otat_set_message( __('All used and unused test tokens have been deleted successfully.', 'otat' ), 'update' );
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
 * Insert 5 test tokens into the database for a selected campaign and return
 * tokenized links for testing purposes.
 *
 * @param  int  $campaign_id  The campaign ID.
 * @return bool $success      TRUE (success) | FALSE (failure).
 */
function otat_create_test_links( $campaign_id ) {
	if ( ! current_user_can( 'manage_options' ) )
		wp_die( __( 'Your permissions do not allow to do that.', 'otat' ) );

	check_admin_referer( 'otat_create_test_tokens_' . absint( $_GET['cid'] ) );

	global $wpdb;
	$campaign_id = absint( $campaign_id );
	$permalink = get_permalink( $wpdb->get_var( "SELECT post_id FROM `{$wpdb->prefix}otat_campaigns` WHERE ID = $campaign_id;" ) );

	$sql  = "INSERT INTO `{$wpdb->prefix}otat_tokens`";
	$sql .= " (`ID`, `campaign_id`, `otat_email`, `otat_token`, `otat_accessed_gmt`)\nVALUES";

	for( $i = 0; $i < 5; ++$i ) {
		$token = md5( uniqid( mt_rand(), true ) );
		$sql .= " ( NULL, $campaign_id, 'user@example.com', '$token', '0000-00-00 00:00:00' ),";
	}
	$sql = trim( $sql, ',') . ';';
	$success = $wpdb->query( $sql );

	return $success;
}


function otat_delete_test_tokens() {
	if ( ! current_user_can( 'manage_options' ) )
		wp_die( __( 'Your permissions do not allow to do that.', 'otat' ) );

	check_admin_referer( 'otat_delete_test_tokens_0' );

	global $wpdb;
	return $wpdb->query(
		"DELETE FROM `{$wpdb->prefix}otat_tokens` " .
		"WHERE otat_email = 'user@example.com';"
	);
}

function otat_get_campaign_name_by_id( $cid ) {
	global $wpdb;
	return $wpdb->get_var(
		"SELECT otat_campaign_name FROM `{$wpdb->prefix}otat_campaigns` " .
		"WHERE ID = " . absint( $cid ) . ";"
	);
}

function otat_get_campaign_by_id( $cid ) {
	global $wpdb;
	return $wpdb->get_row(
		"SELECT * FROM `{$wpdb->prefix}otat_campaigns` " .
		"WHERE ID = " . absint( $cid ) . ";"
	);
}

function otat_get_post_permalink_by_campaign_id( $cid ) {
	global $wpdb;
	$post_id = $wpdb->get_var(
		"SELECT post_id FROM `{$wpdb->prefix}otat_campaigns` " .
		"WHERE ID = " . absint( $cid ) . ";"
	);
	return get_permalink( absint( $post_id ) );
}

function otat_delete_campaign_confirm( $cid ) {
	$campaign = otat_get_campaign_by_id( $cid );
	?>
	<h3>
		<?php printf( __( 'Campaign "%s" and %d associated access tokens will be deleted!', 'otat' ) . '<br><br>' . __( 'Are you sure?', 'otat' ),
				esc_html( $campaign->otat_campaign_name ),
				esc_html( $campaign->otat_token_amount )
			); // TODO: Make _nx (Plural) ?>
	</h3>
	<form id="otat_campaign_delete_confirm" action="<?php echo admin_url( 'tools.php?page=one-time-access-tokens&tab=campaigns' ); ?>" method="POST">
		<?php wp_nonce_field( 'otat_campaign_'.$cid.'_delete_confirm' ); ?>
		<input type="hidden" id="action" name="action" value="delete_campaign_confirmed">
		<input type="hidden" id="cid" name="cid" value="<?php echo $cid; ?>">
		<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Delete', 'otat' ); ?>"> &nbsp;
			<a href="<?php echo admin_url( 'tools.php?page=one-time-access-tokens&tab=campaigns' ); ?>"><?php _e( 'Cancel', 'otat' ); ?></a>
		</p>
	</form>
	<?php
}

function otat_delete_campaign_confirmed( $cid ) {
	if ( ! current_user_can( 'manage_options' ) )
		wp_die( __( 'Your permissions do not allow to do that.', 'otat' ) );

	check_admin_referer( 'otat_campaign_'. $cid .'_delete_confirm' );

	global $wpdb;
	$sql  = $wpdb->prepare(
		"DELETE FROM `{$wpdb->prefix}otat_tokens` WHERE campaign_id = %d;",
		$cid
	);
	$affected_rows = $wpdb->query( $sql );
	if ( false === $affected_rows ) {
		otat_set_message( __( 'An error happend while trying to delete tokens of this campaign.', 'otat' ), 'error' );
		return;
	}
	$sql  = $wpdb->prepare(
		"DELETE FROM `{$wpdb->prefix}otat_campaigns` WHERE ID = %d;",
		$cid
	);
	$affected_row = $wpdb->query( $sql );
	if ( $affected_row ) {
		otat_set_message( __( 'Campaign successfully deleted', 'otat' ), 'update' );
	} else {
		otat_set_message( __( 'Campaign could not be deleted.', 'otat' ), 'error' );
	}
}

