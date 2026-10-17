<?php
/**
 * Front end: loads the widget and fills missing image ALT text.
 *
 * @package Real_Accessability
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Real_Accessability_Frontend {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_head', array( __CLASS__, 'source_comment' ), 2 );
		add_filter( 'wp_content_img_tag', array( __CLASS__, 'fill_image_alt' ), 10, 3 );
	}

	// A note in the page source only (like the one Yoast SEO adds) - visitors do not see it.
	public static function source_comment() {
		if ( is_feed() || is_embed() || ! self::widget_enabled( Real_Accessability_Settings::get() ) ) {
			return;
		}
		echo "\n<!-- Accessibility menu by Real Accessibility " . esc_html( REAL_ACCESSABILITY_VERSION ) . " (free on WordPress.org: https://wordpress.org/plugins/real-accessability/) - REALMEDIA - https://realmedia.co.il -->\n";
	}

	private static function widget_enabled( $settings ) {
		if ( empty( $settings['live'] ) ) {
			return false;
		}
		foreach ( Real_Accessability_Settings::devices() as $device ) {
			if ( ! empty( $settings['devices'][ $device ]['show'] ) ) {
				return true;
			}
		}
		return false;
	}

	public static function enqueue() {
		if ( is_feed() || is_embed() ) {
			return;
		}

		$settings = Real_Accessability_Settings::get();

		if ( ! self::widget_enabled( $settings ) ) {
			return;
		}

		$version = REAL_ACCESSABILITY_VERSION;
		$url     = REAL_ACCESSABILITY_URL . 'assets/';

		wp_enqueue_style( 'real-accessability-page', $url . 'css/page.css', array(), real_accessability_asset_version( 'assets/css/page.css' ) );

		// Apply the visitor's saved adjustments before the page is painted.
		wp_register_script( 'real-accessability-early', false, array(), $version, false );
		wp_enqueue_script( 'real-accessability-early' );
		wp_add_inline_script( 'real-accessability-early', self::early_script() );

		wp_enqueue_script( 'real-accessability-icons', $url . 'js/icons.js', array(), real_accessability_asset_version( 'assets/js/icons.js' ), array( 'in_footer' => true ) );
		wp_enqueue_script( 'real-accessability-widget', $url . 'js/widget.js', array( 'real-accessability-icons' ), real_accessability_asset_version( 'assets/js/widget.js' ), array( 'in_footer' => true ) );
		wp_add_inline_script( 'real-accessability-widget', 'window.realAccessibility = ' . wp_json_encode( self::config( $settings ), JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );
	}

	private static function config( $settings ) {
		$languages = Real_Accessability_I18n::widget_languages( $settings );

		$devices = array();
		foreach ( Real_Accessability_Settings::devices() as $device ) {
			$d                  = $settings['devices'][ $device ];
			$devices[ $device ] = array(
				'show'         => (bool) $d['show'],
				'anchor'       => $d['anchor'],
				'x'            => (int) $d['offset_x'],
				'y'            => (int) $d['offset_y'],
				'size'         => (int) $d['size'],
				'radius'       => (int) $d['radius'],
				'drawer'       => $d['drawer'],
				'drawerRadius' => (int) $d['drawer_radius'],
			);
		}

		$statement = '';
		if ( $settings['statement_button'] && $settings['statement_page'] && 'publish' === get_post_status( $settings['statement_page'] ) ) {
			$statement = get_permalink( $settings['statement_page'] );
		}

		return array(
			'version'    => REAL_ACCESSABILITY_VERSION,
			'color'      => $settings['color'],
			'iconColor'  => $settings['icon_color'],
			'devices'    => $devices,
			'profiles'   => array_keys( array_filter( $settings['profiles'] ) ),
			'features'   => array_keys( array_filter( $settings['features'] ) ),
			'hideButton' => (bool) $settings['hide_button'],
			'flat'       => ! empty( $settings['flat'] ),
			'statement'  => $statement ? esc_url_raw( $statement ) : '',
			'credit'     => $settings['credit'] ? array(
				'url'  => 'https://wordpress.org/plugins/real-accessability/',
				'logo' => REAL_ACCESSABILITY_URL . 'assets/img/logo.svg',
			) : false,
			'css'        => REAL_ACCESSABILITY_URL . 'assets/css/widget.css?ver=' . rawurlencode( real_accessability_asset_version( 'assets/css/widget.css' ) ),
			'languages'  => $languages,
			'language'   => Real_Accessability_I18n::default_widget_language( $settings, $languages ),
			'i18n'       => Real_Accessability_I18n::dictionaries( $languages ),
		);
	}

	/**
	 * Settings for the menu preview on the Features screen: the desktop layout on every
	 * width (the preview frame is narrow), always shown, and the credit object at hand so
	 * the preview can switch it on and off.
	 */
	public static function preview_config( $settings ) {
		$config  = self::config( $settings );
		$desktop = $config['devices']['desktop'];

		$desktop['show']   = true;
		$config['devices'] = array(
			'desktop' => $desktop,
			'tablet'  => $desktop,
			'mobile'  => $desktop,
		);
		$config['creditInfo'] = array(
			'url'  => 'https://wordpress.org/plugins/real-accessability/',
			'logo' => REAL_ACCESSABILITY_URL . 'assets/img/logo.svg',
		);
		$config['preview']    = true;

		return $config;
	}

	// Kept short and dependency free - it runs in <head> on every page.
	private static function early_script() {
		return <<<'JS'
window.realAccessibilityClasses = function (s) {
	var c = [], on = s.on || {};
	if (s.contrast) c.push(['', 'ra-c-high', 'ra-c-invert', 'ra-c-gray'][s.contrast] || '');
	if (s.align) c.push(['', 'ra-align-center', 'ra-align-right', 'ra-align-left'][s.align] || '');
	if (s.lh) c.push('ra-lh-' + s.lh);
	if (s.ls) c.push('ra-ls-' + s.ls);
	['font', 'links', 'headings', 'cursor', 'noimg', 'motion', 'keys'].forEach(function (k) { if (on[k]) c.push('ra-' + k); });
	return c.filter(Boolean);
};
(function () {
	try {
		var s = JSON.parse(localStorage.getItem('realAccessibility') || 'null');
		if (s) document.documentElement.classList.add.apply(document.documentElement.classList, window.realAccessibilityClasses(s));
	} catch (e) {}
})();
JS;
	}

	/**
	 * Images inserted into content keep the ALT text they had when they were inserted.
	 * When that is empty, use the ALT text saved later in the Media Library.
	 */
	public static function fill_image_alt( $tag, $context, $attachment_id ) {
		static $enabled = null;

		if ( null === $enabled ) {
			$settings = Real_Accessability_Settings::get();
			$enabled  = ! empty( $settings['alt_fix'] );
		}

		// The tag is read with the HTML API (WordPress 6.2+), never with a regex: an "alt=''" written
		// inside another attribute's value must not be taken for the image's own ALT attribute.
		if ( ! $enabled || ! $attachment_id || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $tag;
		}

		$img = new WP_HTML_Tag_Processor( $tag );
		if ( ! $img->next_tag( 'img' ) ) {
			return $tag;
		}

		// Leave images that already have ALT text.
		$current = $img->get_attribute( 'alt' );
		if ( is_string( $current ) && '' !== trim( $current ) ) {
			return $tag;
		}

		$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		if ( '' === $alt ) {
			return $tag;
		}

		$img->set_attribute( 'alt', $alt ); // Escaped by the HTML API
		return $img->get_updated_html();
	}
}
