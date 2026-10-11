<?php
/**
 * Remove the plugin's settings and cached data when it is deleted.
 *
 * @package Real_Accessability
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// The settings and cached data of one site
$real_accessability_cleanup = function () {
	global $wpdb;
	delete_option( 'real_accessability_settings' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removing the plugin's cached transients.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_real\_accessability\_%' OR option_name LIKE '\_transient\_timeout\_real\_accessability\_%'" );
};

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $real_accessability_site ) {
		switch_to_blog( $real_accessability_site );
		$real_accessability_cleanup();
		restore_current_blog();
	}
} else {
	$real_accessability_cleanup();
}

// The "menu is off" notice dismissals (users are shared by the whole network)
delete_metadata( 'user', 0, 'real_accessability_off_dismissed', '', true );
