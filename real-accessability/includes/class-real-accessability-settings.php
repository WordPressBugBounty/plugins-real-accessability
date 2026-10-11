<?php
/**
 * Settings: defaults, reading and sanitizing.
 *
 * @package Real_Accessability
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Real_Accessability_Settings {

	const OPTION = 'real_accessability_settings';

	public static function devices() {
		return array( 'desktop', 'tablet', 'mobile' );
	}

	public static function anchors() {
		return array( 'top-left', 'top-center', 'top-right', 'middle-left', 'middle-center', 'middle-right', 'bottom-left', 'bottom-center', 'bottom-right' );
	}

	public static function drawer_sides() {
		return array( 'left', 'right', 'bottom' );
	}

	public static function profile_keys() {
		return array( 'seizure', 'vision', 'adhd', 'dys', 'motor', 'screen', 'senior' );
	}

	public static function feature_keys() {
		return array( 'contrast', 'font', 'links', 'headings', 'cursor', 'noimg', 'mag', 'align', 'size', 'lh', 'ls', 'guide', 'motion', 'keys' );
	}

	public static function defaults() {
		$device = array(
			'show'          => 1,
			'anchor'        => 'bottom-right',
			'offset_x'      => 24,
			'offset_y'      => 24,
			'size'          => 52,
			'radius'        => 26, // A circle: half the size.
			'drawer'        => 'left',
			'drawer_radius' => 0,
		);

		return array(
			'color'            => '#003CFF',
			'icon_color'       => '',
			'devices'          => array(
				'desktop' => $device,
				'tablet'  => array_merge( $device, array( 'offset_x' => 20, 'offset_y' => 20 ) ),
				'mobile'  => array_merge(
					$device,
					array(
						'anchor'        => 'middle-right',
						'offset_x'      => 16,
						'offset_y'      => 16,
						'size'          => 48,
						'radius'        => 24,
						'drawer'        => 'bottom',
						'drawer_radius' => 16,
					)
				),
			),
			'profiles'         => array_fill_keys( self::profile_keys(), 1 ),
			'features'         => array_fill_keys( self::feature_keys(), 1 ),
			'hide_button'      => 1,
			'credit'           => 0,
			'credit_asked'     => 0,
			'onboarded'        => 0,
			'live'             => 1,
			'off_since'        => 0,
			'languages'        => array(),
			'default_language' => '',
			'statement_page'   => 0,
			'statement_button' => 1,
			'alt_fix'          => 1,
		);
	}

	// True only while the plugin saves settings it built itself (see save()).
	private static $internal = false;

	/**
	 * Saves settings the plugin built itself. The sanitize callback lets untagged input through
	 * only during this call - anything else posted to options.php must come with its tab.
	 */
	public static function save( $settings ) {
		self::$internal = true;
		update_option( self::OPTION, $settings );
		self::$internal = false;
	}

	public static function is_internal_save() {
		return self::$internal;
	}

	public static function get() {
		$saved = get_option( self::OPTION, array() );
		return self::merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	// Saved values on top of the defaults, keeping only known keys and the default's type.
	private static function merge( $defaults, $saved ) {
		$result = $defaults;
		foreach ( $defaults as $key => $value ) {
			if ( ! array_key_exists( $key, $saved ) ) {
				continue;
			}
			$item = $saved[ $key ];
			if ( 'languages' === $key ) {
				$result[ $key ] = is_array( $item ) ? array_values(
					array_filter(
						$item,
						function ( $row ) {
							return is_array( $row ) && isset( $row['locale'] ) && is_string( $row['locale'] );
						}
					)
				) : array();
			} elseif ( is_array( $value ) ) {
				$result[ $key ] = self::merge( $value, is_array( $item ) ? $item : array() );
			} elseif ( is_int( $value ) ) {
				$result[ $key ] = is_scalar( $item ) ? (int) $item : $value;
			} elseif ( is_string( $value ) ) {
				$result[ $key ] = is_scalar( $item ) ? (string) $item : $value;
			} else {
				$result[ $key ] = $item;
			}
		}
		return $result;
	}

	public static function sanitize_hex( $color, $fallback = '' ) {
		$color = sanitize_hex_color( is_string( $color ) ? trim( $color ) : '' );
		return $color ? strtoupper( $color ) : $fallback;
	}

	private static function int_between( $value, $min, $max ) {
		return max( $min, min( $max, (int) $value ) );
	}

	/**
	 * Sanitize the part of the settings that belongs to the submitted tab
	 * and keep everything else as it is.
	 *
	 * @param array  $input Submitted values.
	 * @param string $tab   Admin tab that was submitted.
	 */
	public static function sanitize( $input, $tab ) {
		$settings = self::get();
		$input    = is_array( $input ) ? $input : array();

		if ( 'button' === $tab ) {
			$settings['color']      = self::sanitize_hex( $input['color'] ?? '', $settings['color'] );
			$settings['icon_color'] = ( isset( $input['icon_mode'] ) && 'custom' === $input['icon_mode'] ) ? self::sanitize_hex( $input['icon_color'] ?? '', '' ) : '';

			foreach ( self::devices() as $device ) {
				$in   = isset( $input['devices'][ $device ] ) && is_array( $input['devices'][ $device ] ) ? $input['devices'][ $device ] : array();
				$size = self::int_between( $in['size'] ?? 52, 40, 72 );

				$settings['devices'][ $device ] = array(
					'show'          => empty( $in['show'] ) ? 0 : 1,
					'anchor'        => in_array( $in['anchor'] ?? '', self::anchors(), true ) ? $in['anchor'] : 'bottom-right',
					'offset_x'      => self::int_between( $in['offset_x'] ?? 0, 0, 400 ),
					'offset_y'      => self::int_between( $in['offset_y'] ?? 0, 0, 400 ),
					'size'          => $size,
					'radius'        => self::int_between( $in['radius'] ?? $size, 0, (int) floor( $size / 2 ) ),
					'drawer'        => in_array( $in['drawer'] ?? '', self::drawer_sides(), true ) ? $in['drawer'] : 'left',
					'drawer_radius' => self::int_between( $in['drawer_radius'] ?? 0, 0, 32 ),
				);
			}
		}

		if ( 'features' === $tab ) {
			foreach ( self::profile_keys() as $key ) {
				$settings['profiles'][ $key ] = empty( $input['profiles'][ $key ] ) ? 0 : 1;
			}
			foreach ( self::feature_keys() as $key ) {
				$settings['features'][ $key ] = empty( $input['features'][ $key ] ) ? 0 : 1;
			}
			$settings['hide_button'] = empty( $input['hide_button'] ) ? 0 : 1;
			$settings['credit']      = empty( $input['credit'] ) ? 0 : 1;
			// Saving the credit switch is an answer to the credit question
			$settings['credit_asked'] = 1;
		}

		if ( 'languages' === $tab ) {
			$available = array_keys( Real_Accessability_I18n::complete_languages() );
			$flags     = array_keys( Real_Accessability_I18n::countries() );
			$languages = array();
			$seen      = array();

			foreach ( (array) ( $input['languages'] ?? array() ) as $row ) {
				$locale = ( is_array( $row ) && isset( $row['locale'] ) ) ? sanitize_text_field( $row['locale'] ) : '';
				if ( ! in_array( $locale, $available, true ) || isset( $seen[ $locale ] ) ) {
					continue;
				}
				$seen[ $locale ] = true;
				$flag        = isset( $row['flag'] ) ? sanitize_key( $row['flag'] ) : '';
				$languages[] = array(
					'locale' => $locale,
					'active' => empty( $row['active'] ) ? 0 : 1,
					'flag'   => in_array( $flag, $flags, true ) ? $flag : Real_Accessability_I18n::default_flag( $locale ),
				);
			}

			$default = isset( $input['default_language'] ) ? sanitize_text_field( $input['default_language'] ) : '';

			$settings['languages']        = $languages;
			$settings['default_language'] = in_array( $default, $available, true ) ? $default : '';
		}

		if ( 'statement' === $tab ) {
			$page                         = isset( $input['statement_page'] ) ? absint( $input['statement_page'] ) : 0;
			$settings['statement_page']   = ( $page && 'page' === get_post_type( $page ) ) ? $page : 0;
			$settings['statement_button'] = empty( $input['statement_button'] ) ? 0 : 1;
		}

		if ( 'alt' === $tab ) {
			$settings['alt_fix'] = empty( $input['alt_fix'] ) ? 0 : 1;
		}

		return $settings;
	}
}
