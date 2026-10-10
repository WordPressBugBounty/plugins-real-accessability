<?php
/**
 * Plugin Name: Real Accessibility
 * Plugin URI: https://wordpress.org/plugins/real-accessability/
 * Description: Adds an accessibility toolbar to your website - font resizer, color inverse, black & white view, link highlighting, readable font and a page reader.
 * Version: 1.1
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Author: REALMEDIA
 * Author URI: https://realmedia.co.il
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: real-accessability
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'REAL_ACCESSABILITY_VERSION', '1.1' );

// Load Localization
function real_accessability_load_textdomain() {
	load_plugin_textdomain( 'real-accessability', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'real_accessability_load_textdomain' );

// Load all the needed scripts
function real_accessability_assets() {
	wp_enqueue_style( 'real-accessability', plugin_dir_url( __FILE__ ) . 'real-accessability.css', array(), REAL_ACCESSABILITY_VERSION );
	wp_enqueue_script( 'real-accessability', plugin_dir_url( __FILE__ ) . 'real-accessability.js', array( 'jquery' ), REAL_ACCESSABILITY_VERSION, true );
	wp_add_inline_script( 'real-accessability', 'jQuery(function(){ jQuery.RealAccessability({ hideOnScroll: false }); });' );
}
add_action( 'wp_enqueue_scripts', 'real_accessability_assets' );

// Read the visitor's saved settings from the cookie
function real_accessability_get_settings() {
	$settings = array(
		'effect'        => null,
		'linkHighlight' => false,
		'regularFont'   => false,
	);

	if ( empty( $_COOKIE['real-accessability'] ) || ! is_string( $_COOKIE['real-accessability'] ) ) {
		return $settings;
	}

	$cookie = json_decode( sanitize_text_field( wp_unslash( $_COOKIE['real-accessability'] ) ), true );

	if ( ! is_array( $cookie ) ) {
		return $settings;
	}

	$effects = array( 'real-accessability-grayscale', 'real-accessability-invert' );

	if ( isset( $cookie['effect'] ) && in_array( $cookie['effect'], $effects, true ) ) {
		$settings['effect'] = $cookie['effect'];
	}

	$settings['linkHighlight'] = ! empty( $cookie['linkHighlight'] );
	$settings['regularFont']   = ! empty( $cookie['regularFont'] );

	return $settings;
}

// Add accessability class to body
function real_accessability_body_class( $classes ) {
	$settings = real_accessability_get_settings();

	$classes[] = 'real-accessability-body';

	if ( $settings['effect'] ) {
		$classes[] = $settings['effect'];
	}

	if ( $settings['linkHighlight'] ) {
		$classes[] = 'real-accessability-linkHighlight';
	}

	if ( $settings['regularFont'] ) {
		$classes[] = 'real-accessability-regularFont';
	}

	return $classes;
}
add_filter( 'body_class', 'real_accessability_body_class' );

// Toolbar HTML
function real_accessability_html() {
	$images = plugin_dir_url( __FILE__ ) . 'images/';
	?>
	<div id="real-accessability">
		<a href="#" id="real-accessability-btn" role="button" aria-expanded="false" aria-controls="real-accessability-panel" aria-label="<?php esc_attr_e( 'Accessibility toolbar', 'real-accessability' ); ?>"><i class="real-accessability-loading" aria-hidden="true"></i><i class="real-accessability-icon" aria-hidden="true"></i></a>
		<div id="real-accessability-panel">
			<ul>
				<li><a href="#" role="button" id="real-accessability-biggerFont"><?php esc_html_e( 'Increase Font', 'real-accessability' ); ?></a></li>
				<li><a href="#" role="button" id="real-accessability-smallerFont"><?php esc_html_e( 'Decrease Font', 'real-accessability' ); ?></a></li>
				<li><a href="#" role="button" aria-pressed="false" id="real-accessability-grayscale" class="real-accessability-effect"><?php esc_html_e( 'Black & White', 'real-accessability' ); ?></a></li>
				<li><a href="#" role="button" aria-pressed="false" id="real-accessability-invert" class="real-accessability-effect"><?php esc_html_e( 'Inverse Colors', 'real-accessability' ); ?></a></li>
				<li><a href="#" role="button" aria-pressed="false" id="real-accessability-linkHighlight"><?php esc_html_e( 'Highlight Links', 'real-accessability' ); ?></a></li>
				<li><a href="#" role="button" aria-pressed="false" id="real-accessability-regularFont"><?php esc_html_e( 'Regular Font', 'real-accessability' ); ?></a></li>
				<li><a href="#" role="button" id="real-accessability-reset"><?php esc_html_e( 'Reset', 'real-accessability' ); ?></a></li>
			</ul>

			<div id="real-accessability-player" hidden>
				<span><?php esc_html_e( 'Page Reader', 'real-accessability' ); ?></span>
				<button type="button" id="real-accessability-play"><img src="<?php echo esc_url( $images . 'play-blue.gif' ); ?>" alt="<?php esc_attr_e( 'Read page content out loud', 'real-accessability' ); ?>"></button>
				<button type="button" id="real-accessability-pause"><img src="<?php echo esc_url( $images . 'pause-gray.gif' ); ?>" alt="<?php esc_attr_e( 'Pause reading', 'real-accessability' ); ?>"></button>
				<button type="button" id="real-accessability-stop"><img src="<?php echo esc_url( $images . 'stop-gray.gif' ); ?>" alt="<?php esc_attr_e( 'Stop reading', 'real-accessability' ); ?>"></button>
				<a href="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'support.html' ); ?>" id="real-accessability-support" target="_blank" rel="noopener nofollow"><img src="<?php echo esc_url( $images . 'user-blue.gif' ); ?>" alt="<?php esc_attr_e( 'Screen reader support (opens in a new tab)', 'real-accessability' ); ?>"></a>
			</div>

			<div id="real-accessability-copyright"><span>Real Accessibility</span></div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'real_accessability_html' );
