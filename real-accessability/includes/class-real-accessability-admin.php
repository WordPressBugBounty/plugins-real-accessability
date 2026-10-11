<?php
/**
 * Admin screens.
 *
 * @package Real_Accessability
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Real_Accessability_Admin {

	const PAGE = 'real-accessability';

	private static $hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_real_accessability_credit', array( __CLASS__, 'save_credit_choice' ) );
		add_action( 'admin_post_real_accessability_activate', array( __CLASS__, 'activate_menu' ) );
		add_action( 'admin_post_real_accessability_power', array( __CLASS__, 'toggle_menu' ) );
		add_action( 'admin_notices', array( __CLASS__, 'plugins_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'menu_off_notice' ) );
		add_action( 'wp_ajax_real_accessability_dismiss_off', array( __CLASS__, 'dismiss_off_notice' ) );
		add_action( 'wp_ajax_real_accessability_audit', array( __CLASS__, 'audit_request' ) );
	}

	/**
	 * The credit stays off until the site owner turns it on (WordPress.org guideline 10).
	 * The settings screen asks once - "Approve" or "No thanks" - and never again after an answer.
	 */
	public static function save_credit_choice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'real-accessability' ) );
		}
		check_admin_referer( 'real_accessability_credit' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared against a fixed value.
		$approved                 = isset( $_POST['choice'] ) && 'yes' === wp_unslash( $_POST['choice'] );
		$settings                 = Real_Accessability_Settings::get();
		$settings['credit']       = $approved ? 1 : 0;
		$settings['credit_asked'] = 1;
		Real_Accessability_Settings::save( $settings );

		wp_safe_redirect( add_query_arg( $approved ? array( 'page' => self::PAGE, 'credit' => 'on' ) : array( 'page' => self::PAGE ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// Turns the menu on, with or without the credit - the owner picks in the activation popup.
	public static function activate_menu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'real-accessability' ) );
		}
		check_admin_referer( 'real_accessability_activate' );

		$settings              = Real_Accessability_Settings::get();
		$settings['live']      = 1;
		$settings['onboarded'] = 1;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared against a fixed value.
		$settings['credit']       = ( isset( $_POST['credit'] ) && 'yes' === wp_unslash( $_POST['credit'] ) ) ? 1 : 0;
		$settings['credit_asked'] = 1;
		Real_Accessability_Settings::save( $settings );

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'activated' => $settings['credit'] ? 'credit' : '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// The on/off switch next to the logo.
	public static function toggle_menu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'real-accessability' ) );
		}
		check_admin_referer( 'real_accessability_power' );

		$settings              = Real_Accessability_Settings::get();
		$settings['live']      = empty( $settings['live'] ) ? 1 : 0;
		$settings['onboarded'] = 1;
		if ( ! $settings['live'] ) {
			$settings['off_since'] = time();
		}
		Real_Accessability_Settings::save( $settings );

		$tab  = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'button';
		$args = array(
			'page' => self::PAGE,
			'tab'  => array_key_exists( $tab, self::tabs() ) ? $tab : 'button',
		);
		if ( $settings['live'] ) {
			$args['activated'] = '1';
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function power_switch( $settings, $tab ) {
		$on = ! empty( $settings['live'] );
		?>
		<form class="ra-power" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="real_accessability_power">
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
			<?php wp_nonce_field( 'real_accessability_power' ); ?>
			<span class="ra-power-label" id="ra-power-label"><?php esc_html_e( 'Accessibility menu on the site', 'real-accessability' ); ?></span>
			<button type="submit" class="ra-power-switch<?php echo $on ? ' is-on' : ''; ?>" role="switch" aria-checked="<?php echo $on ? 'true' : 'false'; ?>" aria-labelledby="ra-power-label"<?php echo $on ? '' : ' data-ask="1"'; ?>>
				<span class="ra-power-track" aria-hidden="true"><span class="ra-power-knob"></span></span>
				<span class="ra-power-state"><?php echo $on ? esc_html__( 'On', 'real-accessability' ) : esc_html__( 'Off', 'real-accessability' ); ?></span>
			</button>
		</form>
		<?php
	}

	/**
	 * While the menu is off, say so on every admin page.
	 * On the plugin's own screen it stays; elsewhere it can be dismissed (guideline 11)
	 * until the next time the menu is turned off, and it goes away once the menu is on.
	 */
	public static function menu_off_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = Real_Accessability_Settings::get();
		if ( ! empty( $settings['live'] ) ) {
			return;
		}

		$screen      = get_current_screen();
		$plugin_page = $screen && $screen->id === self::$hook;
		$off_since   = (int) $settings['off_since'];

		$dismissed = get_user_meta( get_current_user_id(), 'real_accessability_off_dismissed', true );
		if ( ! $plugin_page && '' !== $dismissed && (int) $dismissed === $off_since ) {
			return;
		}
		?>
		<div class="notice notice-warning ra-off-notice<?php echo $plugin_page ? '' : ' is-dismissible'; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'real_accessability_dismiss_off' ) ); ?>">
			<p>
				<strong style="color:#d63638;text-decoration:underline;text-underline-offset:3px"><?php esc_html_e( 'The accessibility menu is off.', 'real-accessability' ); ?></strong>
				<?php esc_html_e( 'Visitors do not see it.', 'real-accessability' ); ?>
				<a class="ra-off-activate" style="margin-inline-start:6px;font-weight:600" href="<?php echo esc_url( add_query_arg( 'page', self::PAGE, admin_url( 'admin.php' ) ) . '#ra-activate' ); ?>"><?php esc_html_e( 'Turn on the accessibility menu', 'real-accessability' ); ?></a>
			</p>
		</div>
		<?php
		if ( ! $plugin_page ) {
			// Remembers the dismissal through admin-ajax (printed in the footer, after the notice)
			wp_register_script( 'real-accessability-off-notice', false, array( 'jquery' ), REAL_ACCESSABILITY_VERSION, true );
			wp_enqueue_script( 'real-accessability-off-notice' );
			wp_add_inline_script(
				'real-accessability-off-notice',
				"jQuery( function ( $ ) { $( document ).on( 'click', '.ra-off-notice .notice-dismiss', function () { $.post( window.ajaxurl, { action: 'real_accessability_dismiss_off', nonce: $( this ).closest( '.ra-off-notice' ).data( 'nonce' ) } ); } ); } );"
			);
		}
	}

	public static function dismiss_off_notice() {
		check_ajax_referer( 'real_accessability_dismiss_off', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		$settings = Real_Accessability_Settings::get();
		update_user_meta( get_current_user_id(), 'real_accessability_off_dismissed', (int) $settings['off_since'] );
		wp_send_json_success();
	}

	// The first visit to the settings screen ends the notice on the Plugins screen.
	public static function mark_onboarded() {
		$settings = Real_Accessability_Settings::get();
		if ( empty( $settings['onboarded'] ) && current_user_can( 'manage_options' ) ) {
			$settings['onboarded'] = 1;
			Real_Accessability_Settings::save( $settings );
		}
	}

	// After installing or updating, point the owner to the settings once - until the credit question is answered.
	public static function plugins_notice() {
		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = Real_Accessability_Settings::get();

		if ( empty( $settings['live'] ) ) {
			return;
		}

		if ( ! empty( $settings['onboarded'] ) ) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<?php esc_html_e( 'Real Accessibility 2.0 is ready. Set the brand color and the button position, and choose whether to show the plugin credit.', 'real-accessability' ); ?>
				<a class="button button-primary" style="margin-inline-start:8px" href="<?php echo esc_url( add_query_arg( 'page', self::PAGE, admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Open Real Accessibility', 'real-accessability' ); ?></a>
			</p>
		</div>
		<?php
	}

	private static function welcome_card() {
		?>
		<form class="ra-welcome" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="real_accessability_credit">
			<?php wp_nonce_field( 'real_accessability_credit' ); ?>
			<p><?php esc_html_e( 'Show a small credit with a link to the plugin page on WordPress.org at the bottom of the accessibility menu? It helps keep the plugin free.', 'real-accessability' ); ?></p>
			<button type="submit" name="choice" value="yes" class="button button-primary"><?php esc_html_e( 'Approve', 'real-accessability' ); ?></button>
			<button type="submit" name="choice" value="no" class="button"><?php esc_html_e( 'No thanks', 'real-accessability' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Real Media banner under the preview - an image bundled with the plugin
	 * (assets/img/promo-banner.png, .jpg or .webp, 1120x400 shown at 560x200).
	 * Settings screen only (guideline 11), a plain link without tracking.
	 * Nothing is shown until the image file exists. Hebrew admin only - the services are for Israeli sites.
	 */
	private static function promo_banner() {
		if ( 0 === strpos( get_user_locale(), 'he' ) ) {
			foreach ( array( 'png', 'jpg', 'webp' ) as $ext ) {
				if ( file_exists( REAL_ACCESSABILITY_DIR . 'assets/img/promo-banner.' . $ext ) ) {
					?>
					<a class="ra-promo-banner" href="https://realmedia.co.il/" target="_blank" rel="noopener">
						<img src="<?php echo esc_url( REAL_ACCESSABILITY_URL . 'assets/img/promo-banner.' . $ext ); ?>" width="560" height="200" alt="<?php esc_attr_e( 'Real Media - website accessibility services', 'real-accessability' ); ?>">
					</a>
					<?php
					break;
				}
			}
		}
		self::partner_banner();
	}

	/**
	 * Cloudways affiliate banner: the image loads from cloudways.com while this settings screen is open,
	 * with no impression counter (clicks are credited through the link). Disclosed in the readme.
	 */
	private static function partner_banner() {
		// Stores get the WooCommerce hosting banner
		if ( class_exists( 'WooCommerce' ) ) {
			$page   = 'woocommerce-hosting.php';
			$banner = '58da17cf';
			$alt    = __( 'Load WooCommerce stores in 249ms', 'real-accessability' );
		} else {
			$page   = 'wordpress-hosting.php';
			$banner = '4869f424';
			$alt    = __( 'Load WordPress sites in as fast as 37ms', 'real-accessability' );
		}
		$query = 'id=419747&a_bid=' . $banner . '&chan=realaccessibility';
		?>
		<div class="ra-partner-banner">
			<a href="<?php echo esc_url( 'https://www.cloudways.com/en/' . $page . '?' . $query ); ?>" target="_blank" rel="sponsored noopener">
				<img src="<?php echo esc_url( 'https://www.cloudways.com/affiliate/accounts/default1/banners/' . $banner . '.jpg' ); ?>" alt="<?php echo esc_attr( 'Cloudways: ' . $alt ); ?>" width="728" height="90">
				<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'real-accessability' ); ?></span>
			</a>
		</div>
		<?php
	}

	/* ---------- Accessibility audit requests ---------- */

	/**
	 * Where audit requests go: a Make (make.com) webhook that passes them on to REALMEDIA.
	 * Nothing is sent until the site owner fills in the form and clicks the button.
	 */
	const AUDIT_ENDPOINT = 'https://hook.eu1.make.com/paymw6ahvaabflnp7w2g0rx52pdqa89l';

	// Hebrew admins see the Israeli offer, everyone else the international one
	private static function audit_offer() {
		$israel = 0 === strpos( get_user_locale(), 'he' );
		return array(
			'israel' => $israel,
			'price'  => $israel ? __( 'From ₪2,000 + VAT', 'real-accessability' ) : __( 'Starting from $500', 'real-accessability' ),
			'offer'  => $israel ? 'ILS 2,000 + VAT' : 'USD 500',
		);
	}

	private static function audit_sent() {
		return (bool) get_transient( 'real_accessability_audit_sent' );
	}

	// The check mark in a rounded square, before each point of the survey pitch
	private static function audit_check_icon() {
		return '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false"><path fill-rule="evenodd" clip-rule="evenodd" d="m16.6889 4.29768c-.871-.28508-2.0573-.29768-4.6889-.29768-1.4115 0-2.42006.00054-3.21486.05477-.78583.05361-1.2894.15635-1.69856.32583-1.22514.50747-2.19851 1.48084-2.70598 2.70598-.16948.40916-.27221.91273-.32583 1.69855-.05423.79481-.05477 1.80337-.05477 3.21487s.00054 2.4201.05477 3.2149c.05362.7858.15635 1.2894.32583 1.6985.50747 1.2252 1.48084 2.1985 2.70598 2.706.40916.1695.91273.2722 1.69856.3258.7948.0543 1.80336.0548 3.21486.0548s2.4201-.0005 3.2149-.0548c.7858-.0536 1.2894-.1563 1.6985-.3258 1.2252-.5075 2.1985-1.4808 2.706-2.706.1695-.4091.2722-.9127.3258-1.6985.0543-.7948.0548-1.8034.0548-3.2149 0-.7658 0-1.4162-.0092-1.9839-.0089-.55221.4316-1.00708.9838-1.01597.5522-.0089 1.0071.43155 1.016.98376.0094.58551.0094 1.25141.0094 2.00971v.0432c0 1.3667 0 2.4433-.0594 3.3142-.0606.8877-.1862 1.6343-.4734 2.3278-.7105 1.7152-2.0732 3.0779-3.7884 3.7884-.6935.2872-1.4401.4128-2.3278.4734-.8709.0594-1.9475.0594-3.3141.0594h-.0738c-1.3666 0-2.44324 0-3.3141-.0594-.88766-.0606-1.63431-.1862-2.32778-.4734-1.7152-.7105-3.07792-2.0732-3.78838-3.7884-.28724-.6935-.41287-1.4401-.47343-2.3278-.05942-.8709-.05941-1.9475-.05941-3.3142v-.0736c0-1.3667-.00001-2.44335.05941-3.31421.06056-.88765.18619-1.63431.47343-2.32778.71046-1.71519 2.07318-3.07791 3.78838-3.78837.69347-.28725 1.44012-.41287 2.32777-.47343.87087-.05942 1.94751-.05942 3.31421-.05941h.0368c.0724 0 .1441-.00001.215-.00002 2.3496-.00036 3.8815-.00059 5.0961.39693.5248.17179.8111.73656.6393 1.26145-.1718.52488-.7366.81112-1.2615.63932zm5.1748.69834c.2783.47706.1172 1.08937-.3599 1.36765l-.2216.1293c-3.5949 2.09701-6.4832 5.21793-8.296 8.96413-.141.2913-.4142.4964-.7333.5504s-.6446-.0497-.8736-.2785l-4.01346-4.0092c-.39073-.3903-.39107-1.0234-.00075-1.4142.39031-.39071 1.02348-.39105 1.41421-.0007l3.0505 3.0473c1.9955-3.55608 4.9079-6.52373 8.4446-8.58679l.2217-.12929c.477-.27828 1.0893-.11715 1.3676.3599z"/></svg>';
	}

	// A document with a shield and a check mark - the accessibility survey
	private static function audit_icon( $size ) {
		return '<svg viewBox="0 0 64 64" width="' . (int) $size . '" height="' . (int) $size . '" fill="currentColor" aria-hidden="true" focusable="false">'
			. '<path d="m32 57.5h-24c-2.48145 0-4.5-2.01855-4.5-4.5v-37.75977c0-1.18262.48047-2.34277 1.31934-3.18066l7.24023-7.24023c.83789-.83887 1.99805-1.31934 3.18066-1.31934h29.75977c2.48145 0 4.5 2.01855 4.5 4.5v23c0 .82812-.67188 1.5-1.5 1.5s-1.5-.67188-1.5-1.5v-23c0-.82715-.67285-1.5-1.5-1.5h-29.75977c-.39355 0-.7793.16016-1.05957.44043l-7.24023 7.24023c-.28027.28027-.44043.66602-.44043 1.05957v37.75977c0 .82715.67285 1.5 1.5 1.5h24c.82812 0 1.5.67188 1.5 1.5s-.67188 1.5-1.5 1.5z"/><path d="m13 17.5h-8c-.82812 0-1.5-.67188-1.5-1.5s.67188-1.5 1.5-1.5h8c.82715 0 1.5-.67285 1.5-1.5v-8c0-.82812.67188-1.5 1.5-1.5s1.5.67188 1.5 1.5v8c0 2.48145-2.01855 4.5-4.5 4.5z"/><path d="m31 15.5h-10c-.82812 0-1.5-.67188-1.5-1.5s.67188-1.5 1.5-1.5h10c.82812 0 1.5.67188 1.5 1.5s-.67188 1.5-1.5 1.5z"/><path d="m41 22.5h-29c-.82812 0-1.5-.67188-1.5-1.5s.67188-1.5 1.5-1.5h29c.82812 0 1.5.67188 1.5 1.5s-.67188 1.5-1.5 1.5z"/><path d="m41 29.5h-29c-.82812 0-1.5-.67188-1.5-1.5s.67188-1.5 1.5-1.5h29c.82812 0 1.5.67188 1.5 1.5s-.67188 1.5-1.5 1.5z"/><path d="m31 36.5h-19c-.82812 0-1.5-.67188-1.5-1.5s.67188-1.5 1.5-1.5h19c.82812 0 1.5.67188 1.5 1.5s-.67188 1.5-1.5 1.5z"/><path d="m20 48.5h-8c-.82812 0-1.5-.67188-1.5-1.5s.67188-1.5 1.5-1.5h8c.82812 0 1.5.67188 1.5 1.5s-.67188 1.5-1.5 1.5z"/><path d="m59.39453 32.55176-11-2.99902c-.26953-.07324-.55566-.07129-.82324.00977l-11 3.28027c-.63574.18945-1.07129.77441-1.07129 1.4375v15.1582c0 2.74609 1.49805 5.26855 3.90918 6.58398l7.87305 4.29395c.22363.12207.4707.18359.71777.18359s.49414-.06152.71777-.18359l7.87402-4.29395c2.41016-1.31543 3.9082-3.83789 3.9082-6.58398v-15.43945c0-.67676-.45312-1.26953-1.10547-1.44727zm-5.33398 9.50879-6 6.00098c-.29102.29102-.6748.43945-1.06055.43945-.31543 0-.63184-.09863-.90039-.2998l-4-3c-.66212-.49707-.79688-1.4375-.2998-2.10059.49805-.66211 1.4375-.7959 2.10059-.2998l2.95898 2.21973 5.08008-5.08105c.58594-.58594 1.53516-.58594 2.12109 0s.58594 1.53516 0 2.12109z"/>'
			. '</svg>';
	}

	// The small card under the previews, on every settings screen
	private static function audit_card() {
		$offer = self::audit_offer();
		$url   = add_query_arg( array( 'page' => self::PAGE, 'tab' => 'audit' ), admin_url( 'admin.php' ) );
		?>
		<div class="ra-card ra-audit-card">
			<div class="ra-audit-card-top">
				<span class="ra-audit-card-icon"><?php echo self::audit_icon( 26 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
				<span>
					<strong><?php esc_html_e( 'Professional accessibility audit', 'real-accessability' ); ?></strong>
					<span class="ra-audit-card-sub"><?php echo esc_html( $offer['price'] ); ?> · <?php esc_html_e( 'A report of every issue and how to fix it', 'real-accessability' ); ?></span>
				</span>
			</div>
			<?php if ( self::audit_sent() ) : ?>
				<p class="ra-audit-card-done"><?php esc_html_e( 'Your request was sent. We will be in touch soon.', 'real-accessability' ); ?></p>
			<?php else : ?>
				<a class="button button-primary ra-audit-card-button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Get a quote', 'real-accessability' ); ?></a>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function tab_audit() {
		$offer   = self::audit_offer();
		$user    = wp_get_current_user();
		$dial    = self::dial_codes();
		$country = Real_Accessability_I18n::default_flag( get_user_locale() );
		$country = isset( $dial[ $country ] ) ? $country : 'us';
		$started = time();
		$points  = $offer['israel'] ? array(
			__( 'Manual and automated testing against Israeli Standard 5568 and WCAG 2.1 AA', 'real-accessability' ),
			__( 'A clear report of every issue, with how to fix it', 'real-accessability' ),
			__( 'Help with your accessibility statement and with the fixes', 'real-accessability' ),
			__( 'A reply within one business day', 'real-accessability' ),
		) : array(
			__( 'Manual and automated testing against WCAG 2.2 AA', 'real-accessability' ),
			__( 'A clear report of every issue, with how to fix it', 'real-accessability' ),
			__( 'Help with your accessibility statement and with the fixes', 'real-accessability' ),
			__( 'A reply within one business day', 'real-accessability' ),
		);
		?>
		<div class="ra-audit ra-card">
			<div class="ra-audit-pitch">
				<h2><?php esc_html_e( 'An accessibility menu alone is not enough', 'real-accessability' ); ?></h2>
				<p>
					<?php
					echo esc_html(
						$offer['israel']
							? __( 'To meet Israeli Standard 5568, the code and content of the site need to be accessible too. Our experts audit your site and show you exactly what to fix.', 'real-accessability' )
							: __( 'To meet WCAG 2.2 AA and laws such as the ADA, the code and content of the site need to be accessible too. Our experts audit your site and show you exactly what to fix.', 'real-accessability' )
					);
					?>
				</p>
				<p class="ra-audit-price"><?php echo esc_html( $offer['price'] ); ?></p>
				<ul>
					<?php foreach ( $points as $point ) : ?>
						<li><span class="ra-audit-check" aria-hidden="true"><?php echo self::audit_check_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span><?php echo esc_html( $point ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="ra-audit-body">
				<div class="ra-audit-done"<?php echo self::audit_sent() ? '' : ' hidden'; ?> role="status">
					<span class="ra-audit-done-icon" aria-hidden="true">✓</span>
					<p><strong><?php esc_html_e( 'Your request was sent.', 'real-accessability' ); ?></strong> <?php esc_html_e( 'We will get back to you as soon as possible.', 'real-accessability' ); ?></p>
				</div>

				<form class="ra-audit-form" method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"<?php echo self::audit_sent() ? ' hidden' : ''; ?> novalidate>
					<h3><?php esc_html_e( 'Get a free quote for an accessibility audit', 'real-accessability' ); ?></h3>
					<p class="ra-sub"><?php esc_html_e( 'We will get back to you with a quote for your site.', 'real-accessability' ); ?></p>

					<input type="hidden" name="action" value="real_accessability_audit">
					<?php wp_nonce_field( 'real_accessability_audit', 'nonce', false ); ?>
					<input type="hidden" name="started" value="<?php echo esc_attr( $started . '|' . wp_hash( 'real_accessability_audit|' . $started ) ); ?>">
					<div class="ra-audit-trap" aria-hidden="true">
						<label><?php esc_html_e( 'Leave this field empty', 'real-accessability' ); ?> <input type="text" name="company_website" value="" tabindex="-1" autocomplete="off"></label>
					</div>

					<p class="ra-audit-field">
						<label for="ra-audit-name"><?php esc_html_e( 'Full name', 'real-accessability' ); ?> <span aria-hidden="true">*</span></label>
						<input type="text" id="ra-audit-name" name="name" value="<?php echo esc_attr( $user->display_name ); ?>" aria-required="true" maxlength="100" autocomplete="name">
						<span class="ra-field-error" hidden></span>
					</p>
					<p class="ra-audit-field">
						<label for="ra-audit-phone"><?php esc_html_e( 'Phone', 'real-accessability' ); ?> <span aria-hidden="true">*</span></label>
						<span class="ra-phone">
							<button type="button" class="ra-phone-country" aria-haspopup="dialog" aria-expanded="false" aria-label="<?php echo esc_attr( __( 'Country code', 'real-accessability' ) . ': +' . $dial[ $country ] ); ?>">
								<img src="<?php echo esc_url( Real_Accessability_I18n::flag_url( $country ) ); ?>" alt="" width="20" height="20">
								<span class="ra-phone-dial">+<?php echo esc_html( $dial[ $country ] ); ?></span>
								<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
							</button>
							<input type="tel" id="ra-audit-phone" name="phone" value="" aria-required="true" maxlength="20" autocomplete="tel-national" inputmode="tel">
						</span>
						<input type="hidden" name="phone_country" value="<?php echo esc_attr( $country ); ?>">
						<span class="ra-field-error" hidden></span>
					</p>
					<p class="ra-audit-field">
						<label for="ra-audit-email"><?php esc_html_e( 'Email', 'real-accessability' ); ?> <span aria-hidden="true">*</span></label>
						<input type="email" id="ra-audit-email" name="email" value="<?php echo esc_attr( $user->user_email ); ?>" aria-required="true" maxlength="100" autocomplete="email" dir="ltr">
						<span class="ra-field-error" hidden></span>
					</p>
					<input type="hidden" name="website" value="<?php echo esc_url( home_url( '/' ) ); ?>">
					<p class="ra-audit-consent">
						<input type="checkbox" id="ra-audit-consent" name="consent" value="1" aria-required="true">
						<?php $consent = self::audit_consent_parts(); ?>
						<span><label for="ra-audit-consent"><?php echo esc_html( $consent[0] ); ?></label><button type="button" class="button-link ra-privacy-open" aria-haspopup="dialog"><?php echo esc_html( $consent[1] ); ?></button><?php echo esc_html( $consent[2] ); ?></span>
					</p>
					<p class="ra-field-error ra-consent-error" hidden></p>

					<p class="ra-audit-error" role="alert" hidden></p>
					<button type="submit" class="button button-primary button-hero ra-audit-submit" data-sending="<?php esc_attr_e( 'Sending...', 'real-accessability' ); ?>"><?php esc_html_e( 'Request a quote', 'real-accessability' ); ?></button>
				</form>
				<?php self::audit_privacy(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * The consent sentence, split around the privacy policy link so the link can sit inside it
	 * (in Hebrew it joins the word before it: "בהתאם ל" + "מדיניות פרטיות").
	 *
	 * @return array{0:string,1:string,2:string} Text before the link, the link text, text after it.
	 */
	private static function audit_consent_parts() {
		/* translators: %s: the "privacy policy" link. */
		$sentence = __( 'I agree that these details will be sent to Real Media Ltd. so they can contact me about an accessibility audit, in accordance with the %s.', 'real-accessability' );
		$parts    = explode( '%s', $sentence, 2 );
		return array( $parts[0], _x( 'privacy policy', 'link inside the consent sentence', 'real-accessability' ), isset( $parts[1] ) ? $parts[1] : '' );
	}

	// The privacy policy for audit requests, opened from the consent line
	private static function audit_privacy() {
		$sections = array(
			array(
				__( 'Who we are', 'real-accessability' ),
				__( 'This form is operated by Real Media Ltd., the developer of Real Accessibility. This policy explains what happens to the details you send through it. It follows the Israeli Protection of Privacy Law, 5741-1981, including Amendment 13.', 'real-accessability' ),
			),
			array(
				__( 'What we collect', 'real-accessability' ),
				__( 'Only what you type into the form: full name, phone and email, together with your site\'s address, your consent, the plugin version and the time of sending.', 'real-accessability' )
					. ' ' . __( 'Your details are sent only when you click the button, together with the language and price shown here. Nothing else from your site is sent.', 'real-accessability' ),
			),
			array(
				__( 'What we use it for', 'real-accessability' ),
				__( 'To get back to you about your request and send you a quote for an accessibility audit. We will not send you marketing messages unless you agree to receive them separately.', 'real-accessability' ),
			),
			array(
				__( 'Do you have to give it', 'real-accessability' ),
				__( 'You are not required by law to give us these details. Without them, we cannot get back to you about your request.', 'real-accessability' ),
			),
			array(
				__( 'Who receives it', 'real-accessability' ),
				__( 'The details are sent to Real Media Ltd., so it can get back to you with a quote.', 'real-accessability' ),
			),
			array(
				__( 'How long we keep it', 'real-accessability' ),
				__( 'For as long as needed to handle your request, and for up to 24 months after our last contact, unless the law requires otherwise.', 'real-accessability' ),
			),
			array(
				__( 'Security', 'real-accessability' ),
				__( 'We use up-to-date measures to protect the details. No system is completely immune to unauthorized access, so we cannot guarantee absolute protection.', 'real-accessability' ),
			),
			array(
				__( 'Your rights', 'real-accessability' ),
				/* translators: %s: email address for privacy requests. */
				sprintf( __( 'You can ask at any time to see, correct or delete your details, or to withdraw your consent, by writing to %s.', 'real-accessability' ), 'office@realmedia.co.il' ),
			),
		);
		?>
		<dialog class="ra-dialog ra-privacy" id="ra-privacy-dialog" aria-labelledby="ra-privacy-title">
			<div class="ra-dialog-head">
				<h2 id="ra-privacy-title"><?php esc_html_e( 'Privacy policy for audit requests', 'real-accessability' ); ?></h2>
				<button type="button" class="ra-dialog-close" aria-label="<?php esc_attr_e( 'Close', 'real-accessability' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
			</div>
			<div class="ra-dialog-body ra-privacy-body" tabindex="0">
				<?php foreach ( $sections as $section ) : ?>
					<h3><?php echo esc_html( $section[0] ); ?></h3>
					<p><?php echo esc_html( $section[1] ); ?></p>
				<?php endforeach; ?>
			</div>
			<div class="ra-dialog-actions">
				<button type="button" class="button button-primary button-hero ra-privacy-done"><?php esc_html_e( 'Close', 'real-accessability' ); ?></button>
			</div>
		</dialog>
		<?php
	}

	// International dialling codes, keyed like the flags
	private static function dial_codes() {
		static $codes = null;
		if ( null === $codes ) {
			$codes = include REAL_ACCESSABILITY_DIR . 'includes/dial-codes.php';
		}
		return $codes;
	}

	/**
	 * A short proof of work: the receiving scenario checks that sha256( "ts|email|website|nonce" )
	 * starts with five zeros, so every request costs the sender about a million hashes.
	 */
	private static function audit_proof( $data ) {
		for ( $nonce = 0; $nonce < 40000000; $nonce++ ) {
			$hash = hash( 'sha256', $data . '|' . $nonce );
			if ( 0 === strpos( $hash, '00000' ) ) {
				return array(
					'nonce' => $nonce,
					'hash'  => $hash,
				);
			}
		}
		return array(
			'nonce' => 0,
			'hash'  => '',
		);
	}

	public static function audit_request() {
		check_ajax_referer( 'real_accessability_audit', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to do that.', 'real-accessability' ) ), 403 );
		}

		$fail = function ( $message ) {
			wp_send_json_error( array( 'message' => $message ), 400 );
		};

		if ( self::audit_sent() ) {
			$fail( __( 'You already sent a request in the last 24 hours. We will be in touch soon.', 'real-accessability' ) );
		}

		// Bots fill the hidden field, or post faster than a person can type
		$trap    = isset( $_POST['company_website'] ) ? sanitize_text_field( wp_unslash( $_POST['company_website'] ) ) : '';
		$started = isset( $_POST['started'] ) ? sanitize_text_field( wp_unslash( $_POST['started'] ) ) : '';
		$parts   = explode( '|', $started );
		$time    = (int) $parts[0];
		$valid   = 2 === count( $parts ) && hash_equals( wp_hash( 'real_accessability_audit|' . $time ), $parts[1] );
		if ( '' !== $trap || ! $valid ) {
			// Looks like a success, so a bot learns nothing
			wp_send_json_success();
		}
		// Too fast for a person typing - but it may be autofill, so ask to send again instead of dropping it
		if ( time() - $time < 3 ) {
			$fail( __( 'The request could not be sent. Please try again later.', 'real-accessability' ) );
		}

		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$dial    = self::dial_codes();
		$country = isset( $_POST['phone_country'] ) ? sanitize_key( wp_unslash( $_POST['phone_country'] ) ) : '';
		$country = isset( $dial[ $country ] ) ? $country : 'us';
		$raw     = array(
			'email'   => isset( $_POST['email'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['email'] ) ) ) : '',
			'website' => isset( $_POST['website'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['website'] ) ) ) : '',
		);
		$email   = sanitize_email( $raw['email'] );
		$website = esc_url_raw( $raw['website'], array( 'http', 'https' ) );
		$website = $website && wp_http_validate_url( $website ) ? $website : home_url( '/' );
		$consent = ! empty( $_POST['consent'] );

		if ( '' === $name || '' === $phone || '' === $raw['email'] ) {
			$fail( __( 'Please fill in all the required fields.', 'real-accessability' ) );
		}
		if ( ! $email || ! is_email( $email ) ) {
			$fail( __( 'Please enter a valid email address.', 'real-accessability' ) );
		}
		// The national number: the trunk 0 goes (054 -> 54), except where it is part of the number (Italy, San Marino, Vatican)
		$digits = preg_replace( '/\D/', '', $phone );
		if ( ! in_array( $country, array( 'it', 'sm', 'va' ), true ) ) {
			$digits = preg_replace( '/^0/', '', $digits );
		}
		if ( ! preg_match( '/^[0-9()\s.-]+$/', $phone ) || strlen( $digits ) < 4 || strlen( $dial[ $country ] . $digits ) > 15 ) {
			$fail( __( 'Please enter a valid phone number.', 'real-accessability' ) );
		}
		if ( ! $consent ) {
			$fail( __( 'Please agree to send your details.', 'real-accessability' ) );
		}
		$endpoint = (string) apply_filters( 'real_accessability_audit_endpoint', self::AUDIT_ENDPOINT );
		if ( '' === $endpoint ) {
			$fail( __( 'The request could not be sent. Please try again later.', 'real-accessability' ) );
		}

		$offer = self::audit_offer();
		$ts    = time();
		$body  = array(
			'name'         => mb_substr( $name, 0, 100 ),
			'phone'        => '+' . $dial[ $country ] . ' ' . $digits,
			'phone_country' => strtoupper( $country ),
			'email'        => $email,
			'website'      => $website,
			'consent'      => true,
			'consent_text' => implode( '', self::audit_consent_parts() ),
			'language'     => get_user_locale(),
			'offer'        => $offer['offer'],
			'plugin'       => 'real-accessability ' . REAL_ACCESSABILITY_VERSION,
			'ts'           => $ts,
			'proof'        => self::audit_proof( $ts . '|' . $email . '|' . $website ),
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$fail( __( 'The request could not be sent. Please try again later.', 'real-accessability' ) );
		}

		set_transient( 'real_accessability_audit_sent', $ts, DAY_IN_SECONDS );
		wp_send_json_success();
	}

	// Above the save button on every settings screen
	private static function disclaimer() {
		?>
		<p class="ra-disclaimer">
			<strong><?php esc_html_e( 'Disclaimer:', 'real-accessability' ); ?></strong>
			<?php esc_html_e( 'Real Accessibility adds an accessibility menu that helps visitors adjust how the site is displayed. On its own, it does not make a website accessible or compliant with any accessibility law or standard (such as WCAG or Israeli Standard 5568). The site owner remains solely responsible for the accessibility of the site\'s code and content and for meeting the legal requirements. The plugin is provided "as is", without warranty of any kind, and the plugin\'s authors are not liable for any damage resulting from its use.', 'real-accessability' ); ?>
		</p>
		<?php
	}

	// The card can be left out (the audit screen) - the popup stays, for the switch at the top
	private static function activation_card( $show_card = true ) {
		if ( $show_card ) :
			?>
		<div class="ra-activate">
			<span class="ra-activate-icon" aria-hidden="true"><?php echo self::wheelchair_svg( 28 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
			<div class="ra-activate-text">
				<h2><?php esc_html_e( 'Your accessibility menu is ready', 'real-accessability' ); ?></h2>
				<p><?php esc_html_e( 'Set the color and the position below, then turn the menu on. You can keep changing the settings after it is live.', 'real-accessability' ); ?></p>
			</div>
			<button type="button" class="button button-primary button-hero ra-activate-open" aria-haspopup="dialog"><?php esc_html_e( 'Turn on the accessibility menu', 'real-accessability' ); ?></button>
		</div>
			<?php
		endif;
		?>

		<dialog class="ra-dialog" id="ra-activate-dialog" aria-labelledby="ra-dialog-title">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="real_accessability_activate">
				<?php wp_nonce_field( 'real_accessability_activate' ); ?>
				<div class="ra-dialog-head">
					<span class="ra-activate-icon" aria-hidden="true"><?php echo self::wheelchair_svg( 28 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
					<h2 id="ra-dialog-title"><?php esc_html_e( 'Turn on the accessibility menu', 'real-accessability' ); ?></h2>
					<button type="button" class="ra-dialog-close" aria-label="<?php esc_attr_e( 'Close', 'real-accessability' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
				</div>
				<div class="ra-dialog-body">
					<p><?php esc_html_e( 'Approving turns the menu on and shows a small credit at its bottom, with a link to the plugin page on WordPress.org.', 'real-accessability' ); ?></p>
					<p><?php esc_html_e( 'Real Accessibility is free, and the credit helps other site owners find it.', 'real-accessability' ); ?></p>
				</div>
				<div class="ra-dialog-actions">
					<button type="submit" name="credit" value="yes" class="button button-primary button-hero" autofocus><?php esc_html_e( 'Approve', 'real-accessability' ); ?></button>
					<button type="button" class="button button-hero ra-dialog-cancel"><?php esc_html_e( 'Cancel', 'real-accessability' ); ?></button>
					<button type="submit" name="credit" value="no" class="button-link ra-dialog-nocredit"><?php esc_html_e( 'You can also turn it on without the credit', 'real-accessability' ); ?></button>
				</div>
			</form>
		</dialog>
		<?php
	}

	// The plugin icon - a person in a wheelchair at a screen (viewBox 0 0 106 97).
	const WHEELCHAIR = 'M92.9582 84.875H87.789L82.4499 68.9145C81.646 66.5038 80.1051 64.4066 78.0447 62.9192C75.9844 61.4317 73.5087 60.6292 70.9675 60.625H56.5832C54.4394 60.625 52.3834 59.7734 50.8675 58.2575C47.5 54.89 48.4999 47.6854 48.4999 45.5416H72.7499V37.4583H48.4999V32.3334C48.4756 32.1506 48.4392 31.9697 48.3908 31.7918C52.2169 30.8323 55.5601 28.507 57.791 25.2539C60.0219 22.0008 60.9865 18.0443 60.503 14.1295C60.0196 10.2147 58.1216 6.61165 55.1665 3.99891C52.2113 1.38618 48.4028 -0.0559692 44.4582 -0.0559692C40.5137 -0.0559692 36.7052 1.38618 33.75 3.99891C30.7948 6.61165 28.8968 10.2147 28.4134 14.1295C27.93 18.0443 28.8946 22.0008 31.1255 25.2539C33.3564 28.507 36.6996 30.8323 40.5257 31.7918C40.4773 31.9697 40.4409 32.1506 40.4166 32.3334V52.5417C40.425 56.7615 42.083 60.8107 45.0365 63.8246C47.99 66.8384 52.0048 68.5781 56.2235 68.672C55.4848 73.0892 53.5373 77.2155 50.5969 80.5936C47.6564 83.9716 43.838 86.4694 39.5648 87.81C35.2917 89.1507 30.7307 89.2817 26.3876 88.1888C22.0445 87.0958 18.0888 84.8215 14.9592 81.6179C11.8297 78.4143 9.64833 74.4067 8.65711 70.0392C7.66588 65.6717 7.90348 61.1151 9.34356 56.8744C10.7836 52.6337 13.3699 48.8747 16.8157 46.0139C20.2615 43.1532 24.4322 41.3026 28.8655 40.6673L27.7177 32.6607C21.752 33.5163 16.1442 36.0222 11.5271 39.8956C6.91002 43.769 3.46735 48.8557 1.58762 54.5817C-0.292118 60.3078 -0.534098 66.4452 0.888982 72.3014C2.31206 78.1577 5.34356 83.4997 9.64138 87.7245C13.9392 91.9494 19.3323 94.889 25.212 96.2116C31.0918 97.5343 37.2242 97.1873 42.9172 95.2098C48.6102 93.2323 53.6372 89.7031 57.431 85.0204C61.2248 80.3376 63.6343 74.6878 64.3877 68.7084H70.9675C71.8161 68.7082 72.6433 68.9751 73.3317 69.4713C74.0201 69.9674 74.5349 70.6677 74.8031 71.4729L81.0434 90.1939C81.3114 90.9983 81.8255 91.6981 82.5131 92.1943C83.2008 92.6904 84.027 92.9577 84.8749 92.9584H92.9582V84.875ZM36.3749 16.1667C36.3749 14.568 36.849 13.0051 37.7372 11.6758C38.6254 10.3465 39.8878 9.31048 41.3649 8.69867C42.8419 8.08686 44.4672 7.92678 46.0352 8.23868C47.6032 8.55058 49.0435 9.32044 50.174 10.4509C51.3045 11.5814 52.0744 13.0217 52.3863 14.5897C52.6982 16.1577 52.5381 17.783 51.9263 19.26C51.3145 20.7371 50.2784 21.9995 48.9491 22.8877C47.6198 23.7759 46.057 24.25 44.4582 24.25C42.3144 24.25 40.2584 23.3984 38.7425 21.8825C37.2265 20.3666 36.3749 18.3105 36.3749 16.1667Z';
	const WHEELCHAIR_SCREEN = 'M64 57H89.365L101.505 34.928L94.495 31.072L84.635 49H64Z';

	private static function wheelchair_svg( $width = 28 ) {
		$height = round( $width * 97 / 106 );
		return '<svg viewBox="0 0 106 97" width="' . (int) $width . '" height="' . (int) $height . '" fill="currentColor" aria-hidden="true" focusable="false"><path d="' . self::WHEELCHAIR . '"/><path d="' . self::WHEELCHAIR_SCREEN . '"/></svg>';
	}

	// For the admin menu: WordPress repaints the fill to match the admin color scheme.
	private static function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 106 97"><path fill="black" d="' . self::WHEELCHAIR . '"/><path fill="black" d="' . self::WHEELCHAIR_SCREEN . '"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Admin menu icon as a data URI, the WordPress way.
	}

	public static function tabs() {
		return array(
			'button'    => __( 'Button and menu', 'real-accessability' ),
			'features'  => __( 'Features', 'real-accessability' ),
			'languages' => __( 'Languages', 'real-accessability' ),
			'statement' => __( 'Accessibility statement', 'real-accessability' ),
			'audit'     => __( 'Accessibility audit', 'real-accessability' ),
		);
	}

	private static function current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only selects which tab to show.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'button';
		return array_key_exists( $tab, self::tabs() ) ? $tab : 'button';
	}

	public static function menu() {
		self::$hook = add_menu_page(
			__( 'Real Accessibility', 'real-accessability' ),
			_x( 'Real Accessibility', 'admin menu label', 'real-accessability' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' ),
			self::menu_icon(),
			81
		);
		add_action( 'load-' . self::$hook, array( __CLASS__, 'mark_onboarded' ) );
	}

	public static function register() {
		register_setting(
			'real_accessability',
			Real_Accessability_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	// The last result of a form save - WordPress runs the callback a second time on it when the option is new.
	private static $last_clean = null;

	public static function sanitize( $input ) {
		// A settings form: only the fields of its tab are read, every one validated
		if ( is_array( $input ) && ! empty( $input['_tab'] ) ) {
			$tab = sanitize_key( $input['_tab'] );
			if ( ! array_key_exists( $tab, self::tabs() ) || 'audit' === $tab ) {
				return Real_Accessability_Settings::get();
			}
			self::$last_clean = Real_Accessability_Settings::sanitize( $input, $tab );
			return self::$last_clean;
		}

		// Built by the plugin itself, or the second pass over a form save that was just cleaned
		if ( is_array( $input ) && ( Real_Accessability_Settings::is_internal_save() || $input === self::$last_clean ) ) {
			return $input;
		}

		// Anything else is not trusted - keep what is saved
		return Real_Accessability_Settings::get();
	}

	public static function enqueue( $hook ) {
		if ( $hook !== self::$hook ) {
			return;
		}

		$url = REAL_ACCESSABILITY_URL . 'assets/';

		wp_enqueue_style( 'real-accessability-admin', $url . 'css/admin.css', array(), real_accessability_asset_version( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'real-accessability-admin', $url . 'js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), real_accessability_asset_version( 'assets/js/admin.js' ), true );
		wp_localize_script( 'real-accessability-admin', 'realAccessibilityAdmin', self::script_data() );
	}

	// Strings and URLs for admin.js.
	public static function script_data() {
		return array(
			'flagsUrl'  => REAL_ACCESSABILITY_URL . 'assets/flags/',
			/* translators: %s: contrast ratio, for example 1.8. */
			'low'       => __( 'Low contrast %s:1', 'real-accessability' ),
			'leaveText' => __( 'You have unsaved changes.', 'real-accessability' ),
			'auditError' => __( 'The request could not be sent. Please try again later.', 'real-accessability' ),
			'audit'      => array(
				'dial'     => self::dial_codes(),
				'flags'    => REAL_ACCESSABILITY_URL . 'assets/flags/',
				'code'     => __( 'Country code', 'real-accessability' ),
				'required' => __( 'This field is required.', 'real-accessability' ),
				'email'    => __( 'Please enter a valid email address.', 'real-accessability' ),
				'phone'    => __( 'Please enter a valid phone number.', 'real-accessability' ),
				'consent'  => __( 'Please agree to send your details.', 'real-accessability' ),
			),
			'flag'      => array(
				/* translators: %s: language name. */
				'label'     => __( 'Flag for %s', 'real-accessability' ),
				'choose'    => __( 'Choose a flag', 'real-accessability' ),
				'search'    => __( 'Search for a country', 'real-accessability' ),
				'suggested' => __( 'Suggested', 'real-accessability' ),
				'all'       => __( 'All countries', 'real-accessability' ),
				'none'      => __( 'No country found', 'real-accessability' ),
			),
			'langCount' => self::language_count_texts(),
		);
	}

	/**
	 * "N languages in the menu" for every possible N, so each language's own plural rule
	 * picks the form (Russian, Arabic and others have more than two).
	 */
	private static function language_count_texts() {
		$texts = array();
		$max   = count( Real_Accessability_I18n::complete_languages() );
		for ( $n = 0; $n <= $max; $n++ ) {
			/* translators: %d: number of languages shown in the menu. */
			$texts[] = sprintf( _n( '%d language in the menu', '%d languages in the menu', $n, 'real-accessability' ), $n );
		}
		return $texts;
	}

	private static function field_name( $path ) {
		return Real_Accessability_Settings::OPTION . $path;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab      = self::current_tab();
		$settings = Real_Accessability_Settings::get();
		?>
		<div class="wrap ra-admin">
			<hr class="wp-header-end">
			<div class="ra-header">
				<h1 class="ra-logo"><img src="<?php echo esc_url( REAL_ACCESSABILITY_URL . 'assets/img/logo.svg' ); ?>" alt="<?php esc_attr_e( 'Real Accessibility', 'real-accessability' ); ?>" width="187" height="32"></h1>
				<?php self::power_switch( $settings, $tab ); ?>
			</div>

			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings sections', 'real-accessability' ); ?>">
				<?php foreach ( self::tabs() as $key => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab<?php echo $key === $tab ? ' nav-tab-active' : ''; ?>"<?php echo $key === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?><?php echo 'audit' === $key ? ' <span class="ra-tab-badge">' . esc_html__( 'Legal compliance', 'real-accessability' ) . '</span>' : ''; ?></a>
				<?php endforeach; ?>
			</nav>

			<?php
			settings_errors();

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks which message to show after the credit choice.
			$credit_result = isset( $_GET['credit'] ) ? sanitize_key( wp_unslash( $_GET['credit'] ) ) : '';
			if ( 'on' === $credit_result ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Thank you! The credit is shown at the bottom of the accessibility menu.', 'real-accessability' ) . '</p></div>';
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks the message shown after turning the menu on.
			$activated = isset( $_GET['activated'] ) ? sanitize_key( wp_unslash( $_GET['activated'] ) ) : '';
			if ( $activated && ! empty( $settings['live'] ) ) {
				$message = 'credit' === $activated
					? __( 'The accessibility menu is live on your site, with the credit at the bottom. Thank you!', 'real-accessability' )
					: __( 'The accessibility menu is live on your site!', 'real-accessability' );
				echo '<div class="notice notice-success is-dismissible ra-live-notice"><p><strong>' . esc_html( $message ) . '</strong> <a class="button" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View the site', 'real-accessability' ) . '</a></p></div>';
			}

			if ( empty( $settings['live'] ) ) {
				self::activation_card( 'audit' !== $tab );
			} elseif ( empty( $settings['credit'] ) && empty( $settings['credit_asked'] ) && 'audit' !== $tab ) {
				// Asked on this screen only, until the owner answers
				self::welcome_card();
			}
			?>

			<?php
			if ( 'audit' === $tab ) {
				self::tab_audit();
				echo '</div>';
				return;
			}
			?>

			<form method="post" action="options.php" class="ra-form">
				<?php settings_fields( 'real_accessability' ); ?>
				<input type="hidden" name="<?php echo esc_attr( self::field_name( '[_tab]' ) ); ?>" value="<?php echo esc_attr( $tab ); ?>">
				<?php
				call_user_func( array( __CLASS__, 'tab_' . $tab ), $settings );
				// These tabs have their own save bar
				if ( ! in_array( $tab, array( 'button', 'languages', 'features' ), true ) ) {
					self::disclaimer();
					submit_button();
				}
				?>
			</form>
		</div>
		<?php
	}

	/* ---------- Button and menu ---------- */

	private static function device_icon( $device ) {
		$paths = array(
			'desktop' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
			'tablet'  => '<rect x="5" y="2.5" width="14" height="19" rx="2"/><path d="M11 18.5h2"/>',
			'mobile'  => '<rect x="7" y="2.5" width="10" height="19" rx="2"/><path d="M11 18.5h2"/>',
		);
		return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $device ] . '</svg>';
	}

	private static function slider( $id, $name, $label, $value, $min, $max, $step, $start, $end, $class ) {
		?>
		<div class="ra-slider-field">
			<div class="ra-slider-top">
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
				<output for="<?php echo esc_attr( $id ); ?>" dir="ltr"><?php echo esc_html( $value ); ?>px</output>
			</div>
			<input type="range" class="ra-slider <?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" step="<?php echo esc_attr( $step ); ?>">
			<div class="ra-slider-ends" aria-hidden="true"><span><?php echo esc_html( $start ); ?></span><span><?php echo esc_html( $end ); ?></span></div>
		</div>
		<?php
	}

	private static function tab_button( $settings ) {
		$devices = array(
			'desktop' => array(
				'name' => __( 'Desktop', 'real-accessability' ),
				'show' => __( 'Show the accessibility button on desktop', 'real-accessability' ),
			),
			'tablet'  => array(
				'name' => __( 'Tablet', 'real-accessability' ),
				'show' => __( 'Show the accessibility button on tablets', 'real-accessability' ),
			),
			'mobile'  => array(
				'name' => __( 'Mobile', 'real-accessability' ),
				'show' => __( 'Show the accessibility button on mobile', 'real-accessability' ),
			),
		);
		$anchors = array(
			'top-left'      => __( 'Top left', 'real-accessability' ),
			'top-center'    => __( 'Top center', 'real-accessability' ),
			'top-right'     => __( 'Top right', 'real-accessability' ),
			'middle-left'   => __( 'Middle left', 'real-accessability' ),
			'middle-center' => __( 'Center', 'real-accessability' ),
			'middle-right'  => __( 'Middle right', 'real-accessability' ),
			'bottom-left'   => __( 'Bottom left', 'real-accessability' ),
			'bottom-center' => __( 'Bottom center', 'real-accessability' ),
			'bottom-right'  => __( 'Bottom right', 'real-accessability' ),
		);
		$sides = array(
			'left'   => __( 'From the left', 'real-accessability' ),
			'right'  => __( 'From the right', 'real-accessability' ),
			'bottom' => __( 'From the bottom', 'real-accessability' ),
		);
		$shapes = array(
			'circle'  => __( 'Circle', 'real-accessability' ),
			'rounded' => __( 'Rounded', 'real-accessability' ),
			'square'  => __( 'Square', 'real-accessability' ),
		);
		$custom_icon = '' !== $settings['icon_color'];
		?>
		<div class="ra-bm">
			<?php foreach ( $devices as $key => $device ) : ?>
				<input type="hidden" class="ra-show" id="ra-show-<?php echo esc_attr( $key ); ?>" data-device="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::field_name( '[devices][' . $key . '][show]' ) ); ?>" value="<?php echo $settings['devices'][ $key ]['show'] ? '1' : '0'; ?>">
			<?php endforeach; ?>

			<div class="ra-devbar">
				<div class="ra-devtabs" role="tablist" aria-label="<?php esc_attr_e( 'Device', 'real-accessability' ); ?>">
					<?php foreach ( $devices as $key => $device ) : ?>
						<button type="button" class="ra-devtab<?php echo 'desktop' === $key ? ' is-active' : ''; ?>" role="tab" id="ra-tab-<?php echo esc_attr( $key ); ?>" aria-controls="ra-panel-<?php echo esc_attr( $key ); ?>" aria-selected="<?php echo 'desktop' === $key ? 'true' : 'false'; ?>" data-device="<?php echo esc_attr( $key ); ?>"<?php echo 'desktop' === $key ? '' : ' tabindex="-1"'; ?>>
							<?php echo self::device_icon( $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
							<?php echo esc_html( $device['name'] ); ?>
							<span class="ra-chip"<?php echo $settings['devices'][ $key ]['show'] ? ' hidden' : ''; ?>><?php esc_html_e( 'hidden', 'real-accessability' ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="ra-bm-grid">
				<div class="ra-bm-settings">

					<section class="ra-card" aria-labelledby="ra-card-color">
						<header class="ra-card-head">
							<h2 id="ra-card-color"><?php esc_html_e( 'Color', 'real-accessability' ); ?></h2>
						</header>
						<div class="ra-card-body">
							<div class="ra-line">
								<div>
									<label class="ra-label" for="ra-color"><?php esc_html_e( 'Brand color', 'real-accessability' ); ?></label>
									<p class="ra-sub"><?php esc_html_e( 'All the menu and button colors come from it.', 'real-accessability' ); ?></p>
								</div>
								<div class="ra-color-field">
									<span class="ra-derived" aria-hidden="true"><i data-derived="light"></i><i data-derived="deep"></i><i data-derived="tint"></i></span>
									<input type="text" class="ra-hex" id="ra-color" name="<?php echo esc_attr( self::field_name( '[color]' ) ); ?>" value="<?php echo esc_attr( $settings['color'] ); ?>" maxlength="7" spellcheck="false" dir="ltr">
									<input type="color" class="ra-swatch" value="<?php echo esc_attr( strtolower( $settings['color'] ) ); ?>" data-for="ra-color" aria-label="<?php esc_attr_e( 'Pick the brand color', 'real-accessability' ); ?>">
								</div>
							</div>
							<div class="ra-line">
								<div>
									<span class="ra-label" id="ra-icon-label"><?php esc_html_e( 'Button icon color', 'real-accessability' ); ?></span>
									<p class="ra-sub"><?php esc_html_e( 'Automatic picks white or black, whichever has more contrast.', 'real-accessability' ); ?></p>
								</div>
								<div class="ra-icon-field">
									<span class="ra-contrast" aria-live="polite" hidden></span>
									<span class="ra-icon-custom"<?php echo $custom_icon ? '' : ' hidden'; ?>>
										<input type="text" class="ra-hex" id="ra-icon-color" name="<?php echo esc_attr( self::field_name( '[icon_color]' ) ); ?>" value="<?php echo esc_attr( $custom_icon ? $settings['icon_color'] : '#FFFFFF' ); ?>" maxlength="7" spellcheck="false" dir="ltr" aria-label="<?php esc_attr_e( 'Custom icon color', 'real-accessability' ); ?>">
										<input type="color" class="ra-swatch" value="<?php echo esc_attr( strtolower( $custom_icon ? $settings['icon_color'] : '#ffffff' ) ); ?>" data-for="ra-icon-color" aria-label="<?php esc_attr_e( 'Pick the icon color', 'real-accessability' ); ?>">
									</span>
									<span class="ra-seg" role="radiogroup" aria-labelledby="ra-icon-label">
										<label><input type="radio" name="<?php echo esc_attr( self::field_name( '[icon_mode]' ) ); ?>" value="auto" <?php checked( ! $custom_icon ); ?>><span><?php esc_html_e( 'Automatic', 'real-accessability' ); ?></span></label>
										<label><input type="radio" name="<?php echo esc_attr( self::field_name( '[icon_mode]' ) ); ?>" value="custom" <?php checked( $custom_icon ); ?>><span><?php esc_html_e( 'Custom', 'real-accessability' ); ?></span></label>
									</span>
								</div>
							</div>
						</div>
					</section>

					<?php
					foreach ( $devices as $key => $device ) :
						$d    = $settings['devices'][ $key ];
						$base = '[devices][' . $key . ']';
						$id   = 'ra-' . $key . '-';
						$max  = (int) floor( $d['size'] / 2 );
						?>
						<div class="ra-device-panel" id="ra-panel-<?php echo esc_attr( $key ); ?>" role="tabpanel" aria-labelledby="ra-tab-<?php echo esc_attr( $key ); ?>" data-device="<?php echo esc_attr( $key ); ?>"<?php echo 'desktop' === $key ? '' : ' hidden'; ?>>

							<section class="ra-card" aria-labelledby="<?php echo esc_attr( $id . 'title' ); ?>">
								<header class="ra-card-head">
									<h2 id="<?php echo esc_attr( $id . 'title' ); ?>"><?php esc_html_e( 'Button and menu', 'real-accessability' ); ?></h2>
									<span class="ra-vis" role="group" aria-labelledby="<?php echo esc_attr( $id . 'vis' ); ?>">
										<span class="ra-vis-label" id="<?php echo esc_attr( $id . 'vis' ); ?>"><?php esc_html_e( 'Show the button on', 'real-accessability' ); ?></span>
										<?php foreach ( $devices as $vis_key => $vis ) : ?>
											<button type="button" class="ra-vis-btn" data-device="<?php echo esc_attr( $vis_key ); ?>" aria-pressed="<?php echo $settings['devices'][ $vis_key ]['show'] ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $vis['show'] ); ?>" title="<?php echo esc_attr( $vis['show'] ); ?>"><?php echo self::device_icon( $vis_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></button>
										<?php endforeach; ?>
									</span>
								</header>
								<div class="ra-subtabs" role="tablist" aria-label="<?php esc_attr_e( 'Button and menu', 'real-accessability' ); ?>">
									<?php
									$sections = array(
										'position' => __( 'Button position', 'real-accessability' ),
										'shape'    => __( 'Shape and size', 'real-accessability' ),
										'menu'     => __( 'The menu', 'real-accessability' ),
									);
									foreach ( $sections as $section => $label ) :
										?>
										<button type="button" class="ra-subtab<?php echo 'position' === $section ? ' is-active' : ''; ?>" role="tab" id="<?php echo esc_attr( $id . 'tab-' . $section ); ?>" aria-controls="<?php echo esc_attr( $id . 'sec-' . $section ); ?>" aria-selected="<?php echo 'position' === $section ? 'true' : 'false'; ?>" data-section="<?php echo esc_attr( $section ); ?>"<?php echo 'position' === $section ? '' : ' tabindex="-1"'; ?>><?php echo esc_html( $label ); ?></button>
									<?php endforeach; ?>
								</div>
								<div class="ra-card-body ra-groups">
									<p class="ra-off-note"<?php echo $d['show'] ? ' hidden' : ''; ?>><?php esc_html_e( 'The button is hidden on this device. Turn it back on with the icons above.', 'real-accessability' ); ?></p>

									<div class="ra-group" role="tabpanel" id="<?php echo esc_attr( $id . 'sec-position' ); ?>" aria-labelledby="<?php echo esc_attr( $id . 'tab-position' ); ?>" data-section="position">
										<div class="ra-pos">
											<fieldset class="ra-anchors">
												<legend class="screen-reader-text"><?php esc_html_e( 'Button position', 'real-accessability' ); ?></legend>
												<?php foreach ( $anchors as $anchor => $label ) : ?>
													<label class="ra-anchor" title="<?php echo esc_attr( $label ); ?>">
														<input type="radio" name="<?php echo esc_attr( self::field_name( $base . '[anchor]' ) ); ?>" value="<?php echo esc_attr( $anchor ); ?>" data-label="<?php echo esc_attr( $label ); ?>" <?php checked( $d['anchor'], $anchor ); ?>>
														<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
													</label>
												<?php endforeach; ?>
												<span class="ra-anchor-name" aria-hidden="true"><?php echo esc_html( $anchors[ $d['anchor'] ] ); ?></span>
											</fieldset>
											<div class="ra-offsets">
												<?php
												// Up to 200px on the slider; a larger saved value keeps its own maximum
												self::slider( $id . 'x', self::field_name( $base . '[offset_x]' ), __( 'Distance from the edge - horizontal', 'real-accessability' ), $d['offset_x'], 0, max( 200, (int) $d['offset_x'] ), 1, __( 'Attached', 'real-accessability' ), max( 200, (int) $d['offset_x'] ) . 'px', 'ra-x' );
												self::slider( $id . 'y', self::field_name( $base . '[offset_y]' ), __( 'Distance from the edge - vertical', 'real-accessability' ), $d['offset_y'], 0, max( 200, (int) $d['offset_y'] ), 1, __( 'Attached', 'real-accessability' ), max( 200, (int) $d['offset_y'] ) . 'px', 'ra-y' );
												?>
											</div>
										</div>
									</div>

									<div class="ra-group" role="tabpanel" id="<?php echo esc_attr( $id . 'sec-shape' ); ?>" aria-labelledby="<?php echo esc_attr( $id . 'tab-shape' ); ?>" data-section="shape" hidden>
										<div class="ra-shapes" role="group" aria-label="<?php esc_attr_e( 'Shape', 'real-accessability' ); ?>">
											<?php foreach ( $shapes as $shape => $label ) : ?>
												<button type="button" class="ra-choice ra-shape" data-shape="<?php echo esc_attr( $shape ); ?>" aria-pressed="false"><i class="ra-shape-<?php echo esc_attr( $shape ); ?>" aria-hidden="true"></i><?php echo esc_html( $label ); ?></button>
											<?php endforeach; ?>
										</div>
										<?php
										self::slider( $id . 'size', self::field_name( $base . '[size]' ), __( 'Button size', 'real-accessability' ), $d['size'], 40, 72, 2, '40px', '72px', 'ra-size' );
										self::slider( $id . 'radius', self::field_name( $base . '[radius]' ), __( 'Corner radius', 'real-accessability' ), $d['radius'], 0, $max, 1, __( 'Square', 'real-accessability' ), __( 'Circle', 'real-accessability' ), 'ra-radius' );
										?>
									</div>

									<div class="ra-group" role="tabpanel" id="<?php echo esc_attr( $id . 'sec-menu' ); ?>" aria-labelledby="<?php echo esc_attr( $id . 'tab-menu' ); ?>" data-section="menu" hidden>
										<p class="ra-sub"><?php esc_html_e( 'Opens from the edge of the screen, wherever the button is.', 'real-accessability' ); ?></p>
										<fieldset class="ra-sides">
											<legend class="screen-reader-text"><?php esc_html_e( 'Menu opens from', 'real-accessability' ); ?></legend>
											<?php foreach ( $sides as $side => $label ) : ?>
												<label class="ra-choice ra-side">
													<input type="radio" name="<?php echo esc_attr( self::field_name( $base . '[drawer]' ) ); ?>" value="<?php echo esc_attr( $side ); ?>" data-label="<?php echo esc_attr( $label ); ?>" <?php checked( $d['drawer'], $side ); ?>>
													<span class="ra-side-mini ra-side-<?php echo esc_attr( $side ); ?>" aria-hidden="true"><b></b></span>
													<span><?php echo esc_html( $label ); ?></span>
												</label>
											<?php endforeach; ?>
										</fieldset>
										<?php self::slider( $id . 'dradius', self::field_name( $base . '[drawer_radius]' ), __( 'Menu corner radius', 'real-accessability' ), $d['drawer_radius'], 0, 32, 1, '0px', '32px', 'ra-dradius' ); ?>
									</div>
								</div>
							</section>
						</div>
					<?php endforeach; ?>
					<?php self::audit_card(); ?>
				</div>

				<div class="ra-bm-preview">
					<section class="ra-card ra-preview-card" aria-labelledby="ra-card-preview">
						<header class="ra-card-head">
							<h2 id="ra-card-preview"><?php esc_html_e( 'Preview', 'real-accessability' ); ?></h2>
							<span class="ra-seg ra-preview-mode" role="group" aria-label="<?php esc_attr_e( 'Preview', 'real-accessability' ); ?>">
								<button type="button" data-mode="button" aria-pressed="false"><?php esc_html_e( 'Button', 'real-accessability' ); ?></button>
								<button type="button" data-mode="open" aria-pressed="true"><?php esc_html_e( 'Menu open', 'real-accessability' ); ?></button>
							</span>
						</header>
						<div class="ra-card-body">
							<div class="ra-pv" data-device="desktop" data-mode="open" aria-hidden="true">
								<div class="ra-pv-bar"><i></i><i></i><i></i><span></span></div>
								<div class="ra-pv-site">
									<div class="ra-pv-nav"></div>
									<div class="ra-pv-hero"><b></b><i></i><i></i></div>
									<div class="ra-pv-img"></div>
									<div class="ra-pv-cards"><span></span><span></span><span></span></div>
									<div class="ra-pv-scrim"></div>
									<div class="ra-pv-drawer">
										<div class="ra-pv-dhead"><b><?php esc_html_e( 'Accessibility', 'real-accessability' ); ?></b><span><i></i><i></i></span></div>
										<div class="ra-pv-dbody">
											<div class="ra-pv-prof"><span class="on"></span><span></span><span></span></div>
											<div class="ra-pv-tiles"><span class="on"></span><span></span><span></span><span class="on"></span><span></span><span></span><span></span><span></span></div>
											<div class="ra-pv-rows"></div>
										</div>
										<div class="ra-pv-dfoot"><span></span><span></span><span></span></div>
									</div>
									<div class="ra-pv-fab"><?php echo self::wheelchair_svg( 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></div>
									<p class="ra-pv-off"><?php esc_html_e( 'The button is hidden on this device.', 'real-accessability' ); ?></p>
								</div>
							</div>
						</div>
					</section>
					<?php self::promo_banner(); ?>
				</div>
			</div>

			<?php self::disclaimer(); ?>

			<div class="ra-savebar">
				<?php submit_button( __( 'Save changes', 'real-accessability' ), 'primary', 'submit', false ); ?>
				<a class="button ra-cancel" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'tab' => 'button' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Cancel', 'real-accessability' ); ?></a>
				<span class="ra-dirty" hidden><?php esc_html_e( 'You have unsaved changes', 'real-accessability' ); ?></span>
			</div>
		</div>
		<?php
	}

	/* ---------- Features ---------- */

	// The widget's own icons (assets/js/icons.js), for the rows on the Features screen.
	private static function widget_icon( $name ) {
		static $icons = null;
		if ( null === $icons ) {
			$js    = (string) file_get_contents( REAL_ACCESSABILITY_DIR . 'assets/js/icons.js' );
			$start = strpos( $js, '{' );
			$end   = strrpos( $js, '}' );
			$icons = ( false !== $start && false !== $end ) ? (array) json_decode( substr( $js, $start, $end - $start + 1 ), true ) : array();
		}
		if ( empty( $icons[ $name ] ) ) {
			return '';
		}
		return '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false"><path d="' . esc_attr( $icons[ $name ] ) . '"/></svg>';
	}

	private static function toggle_row( $name, $checked, $label, $desc, $icon, $colors = null ) {
		$id    = 'ra-ft-' . md5( $name );
		$style = $colors ? ' style="background:linear-gradient(135deg,' . esc_attr( $colors[0] ) . ' 0%,' . esc_attr( $colors[1] ) . ' 100%);box-shadow:inset 0 1px 0 rgba(255,255,255,.28),0 4px 10px -4px ' . esc_attr( $colors[1] ) . '"' : '';
		?>
		<label class="ra-ft-row" for="<?php echo esc_attr( $id ); ?>">
			<span class="ra-ft-icon<?php echo $colors ? ' is-profile' : ''; ?>"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>><?php echo self::widget_icon( $icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG with an escaped path. ?></span>
			<span class="ra-ft-text">
				<span class="ra-ft-name"><?php echo esc_html( $label ); ?></span>
				<?php if ( $desc ) : ?>
					<span class="ra-ft-desc"><?php echo esc_html( $desc ); ?></span>
				<?php endif; ?>
			</span>
			<input type="checkbox" class="ra-toggle" role="switch" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?>>
		</label>
		<?php
	}

	private static function tab_features( $settings ) {
		$s        = Real_Accessability_I18n::catalog();
		$profiles = array(
			'seizure' => array( 'bolt', '#FDB022', '#F79009' ),
			'vision'  => array( 'eye', '#53B1FD', '#1570EF' ),
			'adhd'    => array( 'target', '#A48AFB', '#7A5AF8' ),
			'dys'     => array( 'book-open-cover', '#3CCB7F', '#099250' ),
			'motor'   => array( 'hand', '#FD853A', '#E04F16' ),
			'screen'  => array( 'computer-speaker', '#8098F9', '#444CE7' ),
			'senior'  => array( 'glasses', '#F670C7', '#DD2590' ),
		);
		$groups   = array(
			$s['display'] => array(
				'contrast' => 'contrast',
				'font'     => 'text-size',
				'links'    => 'link',
				'headings' => 'underline',
				'cursor'   => 'cursor',
				'noimg'    => 'picture',
				'mag'      => 'zoom-in',
				'align'    => 'align-justify',
			),
			$s['text']    => array(
				'size' => 'text-size',
				'lh'   => 'text-height',
				'ls'   => 'text-width',
			),
			$s['reading'] => array(
				'guide'  => 'text',
				'motion' => 'pause-circle',
				'keys'   => 'keyboard',
			),
		);
		?>
		<div class="ra-ft">
			<p class="ra-sub ra-ft-intro"><?php esc_html_e( 'Choose what visitors see in the accessibility menu. Sections without any option are not shown.', 'real-accessability' ); ?></p>

			<div class="ra-bm-grid">
			<div class="ra-bm-settings">
				<section class="ra-card" aria-labelledby="ra-ft-profiles">
					<header class="ra-card-head"><h2 id="ra-ft-profiles"><?php echo esc_html( $s['profiles'] ); ?></h2></header>
					<div class="ra-ft-rows">
						<?php
						foreach ( $profiles as $key => $look ) {
							self::toggle_row( self::field_name( '[profiles][' . $key . ']' ), ! empty( $settings['profiles'][ $key ] ), $s[ $key ], $s[ $key . 'Desc' ], $look[0], array( $look[1], $look[2] ) );
						}
						?>
					</div>
				</section>

				<?php foreach ( $groups as $title => $items ) : ?>
					<section class="ra-card" aria-label="<?php echo esc_attr( $title ); ?>">
						<header class="ra-card-head"><h2><?php echo esc_html( $title ); ?></h2></header>
						<div class="ra-ft-rows">
							<?php
							foreach ( $items as $key => $icon ) {
								self::toggle_row( self::field_name( '[features][' . $key . ']' ), ! empty( $settings['features'][ $key ] ), $s[ $key ], isset( $s[ $key . 'Desc' ] ) ? $s[ $key . 'Desc' ] : '', $icon );
							}
							?>
						</div>
					</section>
				<?php endforeach; ?>

				<section class="ra-card" aria-labelledby="ra-ft-footer">
					<header class="ra-card-head"><h2 id="ra-ft-footer"><?php esc_html_e( 'Menu footer', 'real-accessability' ); ?></h2></header>
					<div class="ra-ft-rows">
						<?php
						self::toggle_row( self::field_name( '[hide_button]' ), ! empty( $settings['hide_button'] ), __( 'Hide button', 'real-accessability' ), __( 'Hides the accessibility button until the visitor\'s next visit', 'real-accessability' ), 'eye-crossed' );
						self::toggle_row( self::field_name( '[credit]' ), ! empty( $settings['credit'] ), __( 'Plugin credit', 'real-accessability' ), __( 'A small credit at the bottom of the menu, with a link to the plugin page on WordPress.org', 'real-accessability' ), 'universal-access' );
						?>
					</div>
				</section>
				<?php self::audit_card(); ?>
			</div>

			<div class="ra-bm-preview">
				<section class="ra-card ra-ft-preview" aria-labelledby="ra-ft-preview-title">
					<header class="ra-card-head"><h2 id="ra-ft-preview-title"><?php esc_html_e( 'Preview', 'real-accessability' ); ?></h2></header>
					<div class="ra-card-body">
						<iframe class="ra-ft-frame" title="<?php esc_attr_e( 'Preview of the accessibility menu', 'real-accessability' ); ?>" sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox"
							data-config="<?php echo esc_attr( wp_json_encode( Real_Accessability_Frontend::preview_config( $settings ) ) ); ?>"
							data-icons="<?php echo esc_url( REAL_ACCESSABILITY_URL . 'assets/js/icons.js?ver=' . real_accessability_asset_version( 'assets/js/icons.js' ) ); ?>"
							data-widget="<?php echo esc_url( REAL_ACCESSABILITY_URL . 'assets/js/widget.js?ver=' . real_accessability_asset_version( 'assets/js/widget.js' ) ); ?>"></iframe>
					</div>
				</section>
				<?php self::promo_banner(); ?>
			</div>
			</div>

			<?php self::disclaimer(); ?>

			<div class="ra-savebar">
				<?php submit_button( __( 'Save changes', 'real-accessability' ), 'primary', 'submit', false ); ?>
				<a class="button ra-cancel" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'tab' => 'features' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Cancel', 'real-accessability' ); ?></a>
				<span class="ra-dirty" hidden><?php esc_html_e( 'You have unsaved changes', 'real-accessability' ); ?></span>
			</div>
		</div>
		<?php
	}

	/* ---------- Languages ---------- */

	private static function tab_languages( $settings ) {
		$rows      = Real_Accessability_I18n::language_rows( $settings );
		$countries = Real_Accessability_I18n::countries();
		$default   = $settings['default_language'];
		$name      = self::field_name( '[languages]' );
		$strings   = Real_Accessability_I18n::catalog();

		if ( ! $default || ! in_array( $default, wp_list_pluck( $rows, 'locale' ), true ) ) {
			$default = $rows[0]['locale'];
		}
		?>
		<div class="ra-lp" data-color="<?php echo esc_attr( $settings['color'] ); ?>">
			<div class="ra-bm-grid">
				<div class="ra-bm-settings">
					<section class="ra-card" aria-labelledby="ra-langs-title">
						<header class="ra-card-head">
							<h2 id="ra-langs-title"><?php echo esc_html( sprintf( '%s (%s)', __( 'Menu languages', 'real-accessability' ), number_format_i18n( count( $rows ) ) ) ); ?></h2>
							<span class="ra-lang-count" aria-live="polite"></span>
						</header>
						<div class="ra-card-body">
							<p class="ra-lang-search">
								<input type="search" class="ra-lang-filter" placeholder="<?php esc_attr_e( 'Search languages', 'real-accessability' ); ?>" aria-label="<?php esc_attr_e( 'Search languages', 'real-accessability' ); ?>" autocomplete="off">
							</p>
							<p class="ra-lang-noresults" hidden><?php esc_html_e( 'No language found', 'real-accessability' ); ?></p>

							<ul class="ra-lang-list">
								<?php
								foreach ( $rows as $i => $row ) :
									$info = Real_Accessability_I18n::locale_info( $row['locale'] );
									$base = $name . '[' . $i . ']';
									$on   = ! empty( $row['active'] );
									?>
									<li class="ra-lang-row<?php echo $on ? '' : ' is-off'; ?>" data-code="<?php echo esc_attr( $info['code'] ); ?>" data-name="<?php echo esc_attr( $info['name'] ); ?>" data-dir="<?php echo esc_attr( $info['dir'] ); ?>">
										<span class="ra-grip" title="<?php esc_attr_e( 'Drag to reorder', 'real-accessability' ); ?>" aria-hidden="true"></span>
										<input type="hidden" name="<?php echo esc_attr( $base . '[locale]' ); ?>" value="<?php echo esc_attr( $row['locale'] ); ?>" data-field="locale">

										<span class="ra-flag-pick" data-default-flag="<?php echo esc_attr( Real_Accessability_I18n::default_flag( $row['locale'] ) ); ?>">
											<button type="button" class="ra-flag-btn" aria-haspopup="dialog" aria-expanded="false" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: language name. */ __( 'Flag for %s', 'real-accessability' ), $info['name'] ) ); ?>">
												<img src="<?php echo esc_url( Real_Accessability_I18n::flag_url( $row['flag'] ) ); ?>" alt="" width="22" height="22">
												<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
											</button>
											<select name="<?php echo esc_attr( $base . '[flag]' ); ?>" data-field="flag" hidden>
												<?php foreach ( $countries as $code => $country ) : ?>
													<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $row['flag'], $code ); ?>><?php echo esc_html( $country ); ?></option>
												<?php endforeach; ?>
											</select>
										</span>

										<span class="ra-lang-name">
											<strong lang="<?php echo esc_attr( str_replace( '_', '-', $row['locale'] ) ); ?>" dir="<?php echo esc_attr( $info['dir'] ); ?>"><?php echo esc_html( $info['name'] ); ?></strong>
										</span>

										<code class="ra-lang-locale" dir="ltr"><?php echo esc_html( $row['locale'] ); ?></code>

										<label class="ra-default-pill">
											<input type="radio" name="<?php echo esc_attr( self::field_name( '[default_language]' ) ); ?>" value="<?php echo esc_attr( $row['locale'] ); ?>" <?php checked( $default, $row['locale'] ); ?> aria-label="<?php echo esc_attr( sprintf( /* translators: %s: language name. */ __( 'Make %s the default', 'real-accessability' ), $info['name'] ) ); ?>">
											<span aria-hidden="true"><?php esc_html_e( 'Default', 'real-accessability' ); ?></span>
										</label>

										<input type="checkbox" class="ra-toggle" role="switch" name="<?php echo esc_attr( $base . '[active]' ); ?>" value="1" <?php checked( $on ); ?> data-field="active" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: language name. */ __( 'Show %s in the menu', 'real-accessability' ), $info['name'] ) ); ?>">
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</section>
					<?php self::audit_card(); ?>
				</div>

				<div class="ra-bm-preview">
					<section class="ra-card" aria-labelledby="ra-langs-preview">
						<header class="ra-card-head"><h2 id="ra-langs-preview"><?php esc_html_e( 'Preview', 'real-accessability' ); ?></h2></header>
						<div class="ra-card-body">
							<div class="ra-langpv" aria-hidden="true">
								<div class="ra-langpv-head">
									<b><?php echo esc_html( $strings['title'] ); ?></b>
									<span class="ra-langpv-ctrls"><span class="ra-langpv-pill"><img alt="" width="18" height="18"><span class="ra-langpv-code"></span><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span><span class="ra-langpv-x">&times;</span></span>
								</div>
								<div class="ra-langpv-menu"></div>
							</div>
						</div>
					</section>
					<?php self::promo_banner(); ?>
				</div>
			</div>

			<?php self::disclaimer(); ?>

			<div class="ra-savebar">
				<?php submit_button( __( 'Save changes', 'real-accessability' ), 'primary', 'submit', false ); ?>
				<a class="button ra-cancel" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'tab' => 'languages' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Cancel', 'real-accessability' ); ?></a>
				<span class="ra-dirty" hidden><?php esc_html_e( 'You have unsaved changes', 'real-accessability' ); ?></span>
			</div>
		</div>
		<?php
	}

	/* ---------- Accessibility statement ---------- */

	private static function tab_statement( $settings ) {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ra-statement-page"><?php esc_html_e( 'Statement page', 'real-accessability' ); ?></label></th>
				<td>
					<?php
					wp_dropdown_pages(
						array(
							'name'              => esc_attr( self::field_name( '[statement_page]' ) ),
							'id'                => 'ra-statement-page',
							'selected'          => (int) $settings['statement_page'],
							'show_option_none'  => esc_html__( '- Select a page -', 'real-accessability' ),
							'option_none_value' => '0',
							'post_status'       => array( 'publish', 'private', 'draft' ),
						)
					);
					?>
					<?php if ( $settings['statement_page'] && get_permalink( $settings['statement_page'] ) ) : ?>
						<a href="<?php echo esc_url( get_permalink( $settings['statement_page'] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View page', 'real-accessability' ); ?></a>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'The page on your site with the accessibility statement. The "Accessibility statement" button in the menu links to it.', 'real-accessability' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Statement button', 'real-accessability' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( self::field_name( '[statement_button]' ) ); ?>" value="1" <?php checked( $settings['statement_button'] ); ?>> <?php esc_html_e( 'Show the "Accessibility statement" button in the menu', 'real-accessability' ); ?></label>
				</td>
			</tr>
		</table>
		<?php
	}
}
