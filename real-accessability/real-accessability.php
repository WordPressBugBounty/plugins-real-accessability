<?php
/**
 * Plugin Name: Real Accessibility: Accessibility Widget & Toolbar
 * Plugin URI: https://wordpress.org/plugins/real-accessability/
 * Description: An accessibility menu for your website - ready-made profiles, contrast modes, text size and spacing, readable font, reading guide and more, styled in your brand color.
 * Version: 2.0.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: REALMEDIA
 * Author URI: https://realmedia.co.il
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: real-accessability
 * Domain Path: /languages
 *
 * @package Real_Accessability
 *
 * Copyright (C) 2026 REALMEDIA (https://realmedia.co.il)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program (license.txt). If not, see https://www.gnu.org/licenses/.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'REAL_ACCESSABILITY_VERSION', '2.0.1' );
define( 'REAL_ACCESSABILITY_FILE', __FILE__ );
define( 'REAL_ACCESSABILITY_DIR', plugin_dir_path( __FILE__ ) );
define( 'REAL_ACCESSABILITY_URL', plugin_dir_url( __FILE__ ) );

// The plugin version plus the file's change time, so browsers never keep a stale copy of a file.
function real_accessability_asset_version( $file ) {
	$path = REAL_ACCESSABILITY_DIR . $file;
	return REAL_ACCESSABILITY_VERSION . ( file_exists( $path ) ? '.' . filemtime( $path ) : '' );
}

require_once REAL_ACCESSABILITY_DIR . 'includes/class-real-accessability-settings.php';
require_once REAL_ACCESSABILITY_DIR . 'includes/class-real-accessability-i18n.php';
require_once REAL_ACCESSABILITY_DIR . 'includes/class-real-accessability-frontend.php';

if ( is_admin() ) {
	require_once REAL_ACCESSABILITY_DIR . 'includes/class-real-accessability-admin.php';
}

// Load Localization. Kept even though WordPress.org language packs load automatically,
// because the plugin ships its own translations for languages without a language pack.
function real_accessability_load_textdomain() {
	load_plugin_textdomain( 'real-accessability', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'real_accessability_load_textdomain' );

// A new install waits for the owner to turn the menu on from the settings screen.
// Updates do not run this, so sites that already use the plugin keep their menu.
function real_accessability_activate( $network_wide = false ) {
	if ( is_multisite() && $network_wide ) {
		foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
			switch_to_blog( $site_id );
			real_accessability_start_off();
			restore_current_blog();
		}
		return;
	}
	real_accessability_start_off();
}
register_activation_hook( __FILE__, 'real_accessability_activate' );

function real_accessability_start_off() {
	if ( false === get_option( Real_Accessability_Settings::OPTION ) ) {
		Real_Accessability_Settings::save( array( 'live' => 0, 'off_since' => time() ) );
	}
}

// Sites updated from 1.x have no saved settings and run on the defaults, with the menu on.
// Saving them once keeps the menu on if the plugin is later deactivated and activated again.
function real_accessability_keep_updated_sites_on() {
	if ( false === get_option( Real_Accessability_Settings::OPTION ) ) {
		Real_Accessability_Settings::save( array( 'live' => 1 ) );
	}
}
add_action( 'admin_init', 'real_accessability_keep_updated_sites_on' );

Real_Accessability_Frontend::init();

if ( is_admin() ) {
	Real_Accessability_Admin::init();
}

// Add a "Settings" link on the Plugins screen.
function real_accessability_action_links( $links ) {
	$url = admin_url( 'admin.php?page=real-accessability' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'real-accessability' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'real_accessability_action_links' );
