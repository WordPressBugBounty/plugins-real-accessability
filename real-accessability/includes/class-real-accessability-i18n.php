<?php
/**
 * Widget languages: the string catalog, which translations are complete,
 * and the per-language dictionaries the widget switches between.
 *
 * Translations come from the regular WordPress files - a language pack in
 * wp-content/languages/plugins or the .mo files bundled in /languages.
 *
 * @package Real_Accessability
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Real_Accessability_I18n {

	const DOMAIN = 'real-accessability';

	const MAX_COUNT = 14;

	private static $countries = null;

	private static $complete = null;

	/**
	 * Every string the widget shows. Keep these as literal __() calls so the
	 * translation tools can find them.
	 */
	public static function catalog() {
		$strings = array(
			'title'          => __( 'Accessibility', 'real-accessability' ),
			'menuLabel'      => __( 'Accessibility menu', 'real-accessability' ),
			'open'           => __( 'Open accessibility menu', 'real-accessability' ),
			'close'          => __( 'Close accessibility menu (Esc)', 'real-accessability' ),
			'menuLanguage'   => __( 'Menu language', 'real-accessability' ),
			'chooseLanguage' => __( 'Choose language', 'real-accessability' ),
			'noActive'       => __( 'No adjustments active', 'real-accessability' ),
			'profiles'       => __( 'Ready-made profiles', 'real-accessability' ),
			'seizure'        => __( 'Seizure safe', 'real-accessability' ),
			'seizureDesc'    => __( 'Stops animations and flashing and dims colors', 'real-accessability' ),
			'vision'         => __( 'Vision impaired', 'real-accessability' ),
			'visionDesc'     => __( 'High contrast, large text and emphasized headings', 'real-accessability' ),
			'adhd'           => __( 'ADHD and focus', 'real-accessability' ),
			'adhdDesc'       => __( 'Fewer distractions, reading guide and line spacing', 'real-accessability' ),
			'dys'            => __( 'Dyslexia', 'real-accessability' ),
			'dysDesc'        => __( 'Readable font, letter spacing and reading guide', 'real-accessability' ),
			'motor'          => __( 'Motor impaired', 'real-accessability' ),
			'motorDesc'      => __( 'Keyboard navigation, large cursor and larger text', 'real-accessability' ),
			'screen'         => __( 'Screen reader', 'real-accessability' ),
			'screenDesc'     => __( 'Keyboard navigation and emphasized links and headings', 'real-accessability' ),
			'senior'         => __( 'Seniors', 'real-accessability' ),
			'seniorDesc'     => __( 'Large text, large cursor and clear links', 'real-accessability' ),
			'display'        => __( 'Display', 'real-accessability' ),
			'contrast'       => __( 'Contrast', 'real-accessability' ),
			'contrastHigh'   => __( 'High contrast', 'real-accessability' ),
			'contrastInvert' => __( 'Inverted contrast', 'real-accessability' ),
			'contrastGray'   => __( 'Grayscale', 'real-accessability' ),
			'font'           => __( 'Readable font', 'real-accessability' ),
			'links'          => __( 'Highlight links', 'real-accessability' ),
			'headings'       => __( 'Highlight headings', 'real-accessability' ),
			'cursor'         => __( 'Large cursor', 'real-accessability' ),
			'noimg'          => __( 'Hide images', 'real-accessability' ),
			'mag'            => __( 'Magnifier', 'real-accessability' ),
			'align'          => __( 'Text align', 'real-accessability' ),
			'alignCenter'    => __( 'Align center', 'real-accessability' ),
			'alignRight'     => __( 'Align right', 'real-accessability' ),
			'alignLeft'      => __( 'Align left', 'real-accessability' ),
			'text'           => __( 'Text', 'real-accessability' ),
			'size'           => __( 'Text size', 'real-accessability' ),
			'lh'             => __( 'Line spacing', 'real-accessability' ),
			'ls'             => __( 'Letter spacing', 'real-accessability' ),
			'regular'        => _x( 'Regular', 'spacing level', 'real-accessability' ),
			/* translators: %s: setting name, for example "Text size". */
			'decrease'       => __( 'Decrease %s', 'real-accessability' ),
			/* translators: %s: setting name, for example "Text size". */
			'increase'       => __( 'Increase %s', 'real-accessability' ),
			'reading'        => __( 'Reading and navigation', 'real-accessability' ),
			'guide'          => __( 'Reading guide', 'real-accessability' ),
			'guideDesc'      => __( 'A highlight bar that follows the pointer', 'real-accessability' ),
			'motion'         => __( 'Stop animations', 'real-accessability' ),
			'motionDesc'     => __( 'Stops motion, flashing and video', 'real-accessability' ),
			'keys'           => __( 'Keyboard navigation', 'real-accessability' ),
			'keysDesc'       => __( 'A prominent focus frame on every move', 'real-accessability' ),
			'reset'          => __( 'Reset', 'real-accessability' ),
			'statement'      => __( 'Accessibility statement', 'real-accessability' ),
			'hide'           => __( 'Hide', 'real-accessability' ),
			'credit'         => __( 'Accessibility plugin', 'real-accessability' ),
		);

		$strings['active'] = array();
		for ( $n = 1; $n <= self::MAX_COUNT; $n++ ) {
			/* translators: %d: number of active accessibility adjustments. */
			$strings['active'][ $n ] = sprintf( _n( '%d adjustment active', '%d adjustments active', $n, 'real-accessability' ), $n );
		}

		return $strings;
	}

	/**
	 * Run the catalog with our gettext calls answered from a given MO file
	 * (or in English when $mo is null).
	 */
	private static function catalog_from( $mo ) {
		$single = function ( $translation, $text, $domain ) use ( $mo ) {
			return self::DOMAIN === $domain ? ( $mo ? $mo->translate( $text ) : $text ) : $translation;
		};
		$context = function ( $translation, $text, $ctx, $domain ) use ( $mo ) {
			return self::DOMAIN === $domain ? ( $mo ? $mo->translate( $text, $ctx ) : $text ) : $translation;
		};
		$plural = function ( $translation, $text, $plural_text, $number, $domain ) use ( $mo ) {
			if ( self::DOMAIN !== $domain ) {
				return $translation;
			}
			if ( $mo ) {
				return $mo->translate_plural( $text, $plural_text, $number );
			}
			return 1 === (int) $number ? $text : $plural_text;
		};

		add_filter( 'gettext', $single, PHP_INT_MAX, 3 );
		add_filter( 'gettext_with_context', $context, PHP_INT_MAX, 4 );
		add_filter( 'ngettext', $plural, PHP_INT_MAX, 5 );

		$strings = self::catalog();

		remove_filter( 'gettext', $single, PHP_INT_MAX );
		remove_filter( 'gettext_with_context', $context, PHP_INT_MAX );
		remove_filter( 'ngettext', $plural, PHP_INT_MAX );

		return $strings;
	}

	// The keys (msgid, with context) of every catalog string, as they appear in a .mo file.
	private static function catalog_keys() {
		$keys   = array();
		$single = function ( $translation, $text, $domain ) use ( &$keys ) {
			if ( self::DOMAIN === $domain ) {
				$keys[ $text ] = true;
			}
			return $translation;
		};
		$context = function ( $translation, $text, $ctx, $domain ) use ( &$keys ) {
			if ( self::DOMAIN === $domain ) {
				$keys[ $ctx . "\4" . $text ] = true;
			}
			return $translation;
		};
		$plural = function ( $translation, $text, $plural_text, $number, $domain ) use ( &$keys ) {
			if ( self::DOMAIN === $domain ) {
				$keys[ $text ] = true;
			}
			return $translation;
		};

		add_filter( 'gettext', $single, PHP_INT_MAX, 3 );
		add_filter( 'gettext_with_context', $context, PHP_INT_MAX, 4 );
		add_filter( 'ngettext', $plural, PHP_INT_MAX, 5 );

		self::catalog();

		remove_filter( 'gettext', $single, PHP_INT_MAX );
		remove_filter( 'gettext_with_context', $context, PHP_INT_MAX );
		remove_filter( 'ngettext', $plural, PHP_INT_MAX );

		return array_keys( $keys );
	}

	// Translation files for our text domain: a language pack first, then the bundled file.
	private static function translation_files() {
		$files = array();
		$dirs  = array( WP_LANG_DIR . '/plugins', REAL_ACCESSABILITY_DIR . 'languages' );

		foreach ( $dirs as $dir ) {
			foreach ( (array) glob( $dir . '/' . self::DOMAIN . '-*.mo' ) as $file ) {
				if ( ! $file ) {
					continue;
				}
				$locale = substr( basename( $file, '.mo' ), strlen( self::DOMAIN ) + 1 );
				if ( preg_match( '/^[a-z]{2,3}(_[A-Za-z0-9]+)*$/', $locale ) ) {
					$files[ $locale ][] = $file;
				}
			}
		}

		return $files;
	}

	private static function signature( $files ) {
		$parts = array( REAL_ACCESSABILITY_VERSION );
		foreach ( $files as $locale => $list ) {
			foreach ( $list as $file ) {
				$parts[] = $file . ':' . filemtime( $file );
			}
		}
		return md5( implode( '|', $parts ) );
	}

	private static function load_mo( $file ) {
		if ( ! class_exists( 'MO' ) ) {
			require_once ABSPATH . WPINC . '/pomo/mo.php';
		}
		$mo = new MO();
		return $mo->import_from_file( $file ) ? $mo : null;
	}

	private static function mo_is_complete( $mo, $keys ) {
		foreach ( $keys as $key ) {
			if ( empty( $mo->entries[ $key ] ) || empty( $mo->entries[ $key ]->translations ) || '' === (string) $mo->entries[ $key ]->translations[0] ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Languages with a complete translation on this site, plus English.
	 *
	 * @return array locale => path of the complete .mo file ('' for English).
	 */
	public static function complete_languages() {
		if ( null !== self::$complete ) {
			return self::$complete;
		}

		$files     = self::translation_files();
		$cache_key = 'real_accessability_langs_' . self::signature( $files );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			self::$complete = $cached;
			return $cached;
		}

		$keys     = self::catalog_keys();
		$complete = array( 'en_US' => '' );

		foreach ( $files as $locale => $list ) {
			foreach ( $list as $file ) {
				$mo = self::load_mo( $file );
				if ( $mo && self::mo_is_complete( $mo, $keys ) ) {
					$complete[ $locale ] = $file;
					break;
				}
			}
		}

		set_transient( $cache_key, $complete, WEEK_IN_SECONDS );
		self::$complete = $complete;

		return $complete;
	}

	/**
	 * The widget strings in one language.
	 */
	public static function dictionary( $locale ) {
		$complete = self::complete_languages();

		if ( ! isset( $complete[ $locale ] ) ) {
			return null;
		}

		$mo = '' === $complete[ $locale ] ? null : self::load_mo( $complete[ $locale ] );

		return self::catalog_from( $mo );
	}

	/**
	 * Native name, region, default flag and direction of a locale.
	 */
	public static function locale_info( $locale ) {
		$known = self::known_locales();
		$lang  = strtolower( strtok( $locale, '_' ) );

		if ( isset( $known[ $locale ] ) ) {
			list( $name, $region, $flag ) = $known[ $locale ];
		} else {
			$name   = $locale;
			$region = '';
			$flag   = '';

			$translations = get_site_transient( 'available_translations' );
			if ( is_array( $translations ) && isset( $translations[ $locale ]['native_name'] ) ) {
				$name = $translations[ $locale ]['native_name'];
			}
		}

		return array(
			'locale' => $locale,
			'code'   => strtoupper( $lang ),
			'name'   => $name,
			'region' => $region,
			'flag'   => $flag ? $flag : self::default_flag( $locale ),
			'dir'    => in_array( $lang, array( 'ar', 'he', 'fa', 'ur', 'ps', 'ckb', 'ug', 'yi', 'dv', 'sd', 'azb', 'haz', 'arq', 'ary' ), true ) ? 'rtl' : 'ltr',
		);
	}

	public static function default_flag( $locale ) {
		$known = self::known_locales();
		if ( isset( $known[ $locale ] ) ) {
			return $known[ $locale ][2];
		}

		$countries = self::countries();
		$parts     = explode( '_', strtolower( $locale ) );

		if ( isset( $parts[1] ) && isset( $countries[ $parts[1] ] ) ) {
			return $parts[1];
		}

		return isset( $countries[ $parts[0] ] ) ? $parts[0] : '';
	}

	public static function countries() {
		if ( null === self::$countries ) {
			self::$countries = include REAL_ACCESSABILITY_DIR . 'includes/countries.php';
		}
		return self::$countries;
	}

	public static function flag_url( $flag ) {
		return $flag ? REAL_ACCESSABILITY_URL . 'assets/flags/' . $flag . '.svg' : '';
	}

	/**
	 * All complete languages in the admin order, with the saved active state and flag.
	 */
	public static function language_rows( $settings ) {
		$complete = self::complete_languages();
		$rows     = array();

		if ( ! empty( $settings['languages'] ) ) {
			foreach ( $settings['languages'] as $row ) {
				if ( is_array( $row ) && isset( $row['locale'] ) && isset( $complete[ $row['locale'] ] ) ) {
					$rows[ $row['locale'] ] = $row;
				}
			}
		}

		// Languages not saved yet: every complete language is listed. The site language and the five
		// main languages come first and are on; the rest are off until the owner turns them on.
		$auto    = empty( $settings['languages'] );
		$default = self::site_language( $complete );
		$main    = array( 'en_US', 'es_ES', 'pt_BR', 'ar', 'ru_RU' );
		$on      = array_merge( array( $default ), $main );
		$order   = array_unique( array_merge( $on, array( 'he_IL' ), array_keys( self::known_locales() ), array_keys( $complete ) ) );

		foreach ( $order as $locale ) {
			if ( isset( $complete[ $locale ] ) && ! isset( $rows[ $locale ] ) ) {
				$rows[ $locale ] = array(
					'locale' => $locale,
					'active' => ( $auto && in_array( $locale, $on, true ) ) ? 1 : 0,
					'flag'   => self::default_flag( $locale ),
				);
			}
		}

		return array_values( $rows );
	}

	/**
	 * The menu language that matches the site: the same locale, else the same language
	 * (es_MX -> es_ES), else English.
	 */
	private static function site_language( $complete ) {
		$site = get_locale();
		if ( isset( $complete[ $site ] ) ) {
			return $site;
		}
		$language = strtok( $site, '_' );
		foreach ( array_keys( $complete ) as $locale ) {
			if ( strtok( $locale, '_' ) === $language ) {
				return $locale;
			}
		}
		return 'en_US';
	}

	/**
	 * Languages shown in the widget's language picker, in order.
	 */
	public static function widget_languages( $settings ) {
		$languages = array();

		foreach ( self::language_rows( $settings ) as $row ) {
			if ( empty( $row['active'] ) ) {
				continue;
			}
			$info = self::locale_info( $row['locale'] );
			if ( $row['flag'] !== $info['flag'] ) {
				$info['region'] = '';
				$info['flag']   = $row['flag'];
			}
			$info['flagUrl'] = self::flag_url( $info['flag'] );
			$languages[]     = $info;
		}

		if ( ! $languages ) {
			$complete = self::complete_languages();
			$info     = self::locale_info( isset( $complete[ get_locale() ] ) ? get_locale() : 'en_US' );

			$info['flagUrl'] = self::flag_url( $info['flag'] );
			$languages[]     = $info;
		}

		return $languages;
	}

	/**
	 * The language the widget opens in: the page language when it is offered,
	 * otherwise the default chosen in the settings, otherwise the first one.
	 */
	public static function default_widget_language( $settings, $languages ) {
		$locales = wp_list_pluck( $languages, 'locale' );
		$current = determine_locale();

		if ( in_array( $current, $locales, true ) ) {
			return $current;
		}

		foreach ( $languages as $language ) {
			if ( strtolower( strtok( $current, '_' ) ) === strtolower( $language['code'] ) ) {
				return $language['locale'];
			}
		}

		if ( $settings['default_language'] && in_array( $settings['default_language'], $locales, true ) ) {
			return $settings['default_language'];
		}

		return $locales[0];
	}

	/**
	 * Dictionaries for the widget languages, cached until a translation file changes.
	 */
	public static function dictionaries( $languages ) {
		$locales   = wp_list_pluck( $languages, 'locale' );
		$cache_key = 'real_accessability_i18n_' . md5( self::signature( self::translation_files() ) . implode( ',', $locales ) );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$dictionaries = array();
		foreach ( $locales as $locale ) {
			$dictionary = self::dictionary( $locale );
			if ( $dictionary ) {
				$dictionaries[ $locale ] = $dictionary;
			}
		}

		set_transient( $cache_key, $dictionaries, WEEK_IN_SECONDS );

		return $dictionaries;
	}

	// Native language name, native region name and default flag for common locales.
	private static function known_locales() {
		return array(
			'en_US'        => array( 'English', 'United States', 'us' ),
			'en_GB'        => array( 'English', 'United Kingdom', 'gb' ),
			'en_CA'        => array( 'English', 'Canada', 'ca' ),
			'en_AU'        => array( 'English', 'Australia', 'au' ),
			'he_IL'        => array( 'עברית', 'ישראל', 'il' ),
			'ar'           => array( 'العربية', 'الإمارات', 'ae' ),
			'ru_RU'        => array( 'Русский', 'Россия', 'ru' ),
			'uk'           => array( 'Українська', 'Україна', 'ua' ),
			'am'           => array( 'አማርኛ', 'ኢትዮጵያ', 'et' ),
			'fr_FR'        => array( 'Français', 'France', 'fr' ),
			'fr_CA'        => array( 'Français', 'Canada', 'ca' ),
			'de_DE'        => array( 'Deutsch', 'Deutschland', 'de' ),
			'de_DE_formal' => array( 'Deutsch (Sie)', 'Deutschland', 'de' ),
			'es_ES'        => array( 'Español', 'España', 'es' ),
			'es_MX'        => array( 'Español', 'México', 'mx' ),
			'it_IT'        => array( 'Italiano', 'Italia', 'it' ),
			'pt_BR'        => array( 'Português', 'Brasil', 'br' ),
			'pt_PT'        => array( 'Português', 'Portugal', 'pt' ),
			'nl_NL'        => array( 'Nederlands', 'Nederland', 'nl' ),
			'pl_PL'        => array( 'Polski', 'Polska', 'pl' ),
			'tr_TR'        => array( 'Türkçe', 'Türkiye', 'tr' ),
			'fa_IR'        => array( 'فارسی', 'ایران', 'ir' ),
			'ur'           => array( 'اردو', 'پاکستان', 'pk' ),
			'el'           => array( 'Ελληνικά', 'Ελλάδα', 'gr' ),
			'bg_BG'        => array( 'Български', 'България', 'bg' ),
			'ro_RO'        => array( 'Română', 'România', 'ro' ),
			'sv_SE'        => array( 'Svenska', 'Sverige', 'se' ),
			'da_DK'        => array( 'Dansk', 'Danmark', 'dk' ),
			'nb_NO'        => array( 'Norsk bokmål', 'Norge', 'no' ),
			'fi'           => array( 'Suomi', 'Suomi', 'fi' ),
			'cs_CZ'        => array( 'Čeština', 'Česko', 'cz' ),
			'sk_SK'        => array( 'Slovenčina', 'Slovensko', 'sk' ),
			'hu_HU'        => array( 'Magyar', 'Magyarország', 'hu' ),
			'hr'           => array( 'Hrvatski', 'Hrvatska', 'hr' ),
			'sr_RS'        => array( 'Српски', 'Србија', 'rs' ),
			'ka_GE'        => array( 'ქართული', 'საქართველო', 'ge' ),
			'hy'           => array( 'Հայերեն', 'Հայաստան', 'am' ),
			'az'           => array( 'Azərbaycan dili', 'Azərbaycan', 'az' ),
			'ja'           => array( '日本語', '日本', 'jp' ),
			'zh_CN'        => array( '简体中文', '中国', 'cn' ),
			'zh_TW'        => array( '繁體中文', '台灣', 'tw' ),
			'ko_KR'        => array( '한국어', '대한민국', 'kr' ),
			'hi_IN'        => array( 'हिन्दी', 'भारत', 'in' ),
			'th'           => array( 'ไทย', 'ประเทศไทย', 'th' ),
			'vi'           => array( 'Tiếng Việt', 'Việt Nam', 'vn' ),
			'id_ID'        => array( 'Bahasa Indonesia', 'Indonesia', 'id' ),
		);
	}
}
