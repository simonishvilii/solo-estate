<?php
/**
 * Settings: languages, prices, leads, appearance, interface texts.
 *
 * @package SoloEstate\Admin
 */

namespace SoloEstate\Admin;

use SoloEstate\I18n;
use SoloEstate\Install;
use SoloEstate\Rates;
use SoloEstate\Settings;

defined( 'ABSPATH' ) || exit;

class Settings_Page {

	/** @var string Open tab. */
	private static $active = 'general';

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'admin_post_solo_estate_save_settings', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_solo_estate_refresh_rate', array( __CLASS__, 'handle_refresh_rate' ) );
		add_action( 'admin_post_solo_estate_style_export', array( __CLASS__, 'handle_style_export' ) );
		add_action( 'admin_post_solo_estate_style_import', array( __CLASS__, 'handle_style_import' ) );
		add_action( 'admin_post_solo_estate_style_reset', array( __CLASS__, 'handle_style_reset' ) );
	}

	/**
	 * Screen. All sections are posted together; tabs only switch visibility.
	 */
	public static function render() {
		$s        = Settings::all();
		$provider = I18n::provider();
		$tabs     = array(
			'general'    => __( 'Languages', 'solo-estate' ),
			'prices'     => __( 'Prices', 'solo-estate' ),
			'leads'      => __( 'Leads', 'solo-estate' ),
			'appearance' => __( 'Appearance', 'solo-estate' ),
			'style'      => __( 'Style', 'solo-estate' ),
			'localization' => __( 'Localization', 'solo-estate' ),
			'access'     => __( 'Access', 'solo-estate' ),
			'advanced'   => __( 'Advanced', 'solo-estate' ),
		);
		if ( ! Admin::is_administrator() ) {
			unset( $tabs['access'], $tabs['advanced'] );
		}

		echo '<div class="wrap solo-estate-wrap"><h1>' . esc_html__( 'Solo Estate settings', 'solo-estate' ) . '</h1>';
		echo '<nav class="nav-tab-wrapper solo-estate-tabs" data-solo-estate-tabs>';
		// Localization search and paging reload the page with ?tab=localization.
		self::$active = Localization::requested() ? Localization::TAB : 'general';
		foreach ( $tabs as $key => $label ) {
			printf( '<a href="#%1$s" class="nav-tab%2$s" data-tab="%1$s">%3$s</a>', esc_attr( $key ), self::$active === $key ? ' nav-tab-active' : '', esc_html( $label ) );
		}
		echo '</nav>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'solo_estate_save_settings' );
		echo '<input type="hidden" name="action" value="solo_estate_save_settings">';

		// Languages.
		self::open( 'general' );
		$providers = array(
			'polylang'       => 'Polylang',
			'wpml'           => 'WPML',
			'translatepress' => 'TranslatePress',
			'none'           => __( 'none', 'solo-estate' ),
		);
		self::row(
			__( 'Multilingual plugin', 'solo-estate' ),
			'<strong>' . esc_html( $providers[ $provider ] ) . '</strong><p class="description">' .
			( 'none' === $provider
				? esc_html__( 'No multilingual plugin detected. The languages below are used for content fields.', 'solo-estate' )
				: esc_html__( 'Content languages are taken automatically from this plugin. Add or remove languages there.', 'solo-estate' ) ) . '</p>'
		);
		$list = array();
		foreach ( I18n::languages() as $code => $lang ) {
			$list[] = sprintf( '<code>%1$s</code> %2$s%3$s', esc_html( $code ), esc_html( $lang['name'] ), $code === I18n::default_code() ? ' <em>(' . esc_html__( 'default', 'solo-estate' ) . ')</em>' : '' );
		}
		self::row( __( 'Content languages', 'solo-estate' ), implode( '<br>', $list ) );
		if ( 'none' === $provider ) {
			self::row(
				__( 'Languages', 'solo-estate' ),
				sprintf( '<textarea name="s[languages]" rows="4" class="large-text code">%s</textarea>', esc_textarea( $s['languages'] ) ) .
				'<p class="description">' . esc_html__( 'One per line: code|Name|locale, e.g. ka|ქართული|ka_GE. The first line is the default language.', 'solo-estate' ) . '</p>'
			);
		} else {
			printf( '<input type="hidden" name="s[languages]" value="%s">', esc_attr( $s['languages'] ) );
		}
		self::close();

		// Prices.
		self::open( 'prices' );
		self::row( __( 'Show prices', 'solo-estate' ), self::checkbox( 'show_prices', $s['show_prices'] ) );
		self::row( __( 'Area unit', 'solo-estate' ), self::text( 'area_unit', $s['area_unit'], 'small-text' ) );
		self::row( __( 'Base currency', 'solo-estate' ), self::text( 'base_currency', $s['base_currency'], 'small-text' ) . ' ' . self::text( 'base_symbol', $s['base_symbol'], 'small-text' ) . '<p class="description">' . esc_html__( 'Code and symbol, e.g. GEL ₾. Prices are entered and shown in this currency.', 'solo-estate' ) . '</p>' );
		self::row( __( 'Second currency', 'solo-estate' ), self::checkbox( 'alt_enabled', $s['alt_enabled'], __( 'Show a currency switcher', 'solo-estate' ) ) );
		self::row( __( 'Second currency code / symbol', 'solo-estate' ), self::text( 'alt_currency', $s['alt_currency'], 'small-text' ) . ' ' . self::text( 'alt_symbol', $s['alt_symbol'], 'small-text' ) . '<p class="description">' . esc_html__( 'e.g. USD $. Prices in this currency are calculated with the exchange rate.', 'solo-estate' ) . '</p>' );
		$stored = Rates::stored();
		$status = '';
		if ( ! empty( $stored['rate'] ) && ! empty( $stored['base'] ) ) {
			$status = sprintf(
				/* translators: 1: base currency, 2: rate, 3: second currency, 4: date */
				__( 'Current rate: 1 %1$s = %2$s %3$s (National Bank of Georgia, %4$s).', 'solo-estate' ),
				$stored['base'],
				number_format_i18n( (float) $stored['rate'], 4 ),
				$stored['alt'],
				isset( $stored['date'] ) ? $stored['date'] : ''
			);
		}
		self::row(
			__( 'Exchange rate', 'solo-estate' ),
			sprintf(
				'<select name="s[rate_source]"><option value="nbg"%1$s>%3$s</option><option value="manual"%2$s>%4$s</option></select>',
				selected( $s['rate_source'], 'nbg', false ),
				selected( $s['rate_source'], 'manual', false ),
				esc_html__( 'National Bank of Georgia (updated automatically)', 'solo-estate' ),
				esc_html__( 'Fixed rate entered below', 'solo-estate' )
			) .
			( '' !== $status ? '<p>' . esc_html( $status ) . '</p>' : '' ) .
			( ! empty( $stored['error'] ) ? '<p class="solo-estate-warn">' . esc_html( $stored['error'] ) . ' ' . esc_html__( 'The fixed rate below is used until it works again.', 'solo-estate' ) . '</p>' : '' ) .
			sprintf( ' <a class="button" href="%1$s">%2$s</a>', esc_url( wp_nonce_url( add_query_arg( 'action', 'solo_estate_refresh_rate', admin_url( 'admin-post.php' ) ), 'solo_estate_refresh_rate' ) ), esc_html__( 'Update the rate now', 'solo-estate' ) ) .
			'<p class="description">' . esc_html__( 'The rate is checked every 6 hours in the background.', 'solo-estate' ) . '</p>'
		);
		self::row(
			__( 'Fixed / fallback rate', 'solo-estate' ),
			/* translators: 1: base currency, 2: second currency */
			sprintf( '1 %1$s = <input type="text" name="s[alt_rate]" value="%3$s" class="small-text" inputmode="decimal"> %2$s', esc_html( $s['base_currency'] ), esc_html( $s['alt_currency'] ), esc_attr( $s['alt_rate'] ) )
		);
		self::row(
			__( 'Currency shown first', 'solo-estate' ),
			sprintf(
				'<select name="s[default_currency]"><option value="alt"%1$s>%3$s</option><option value="base"%2$s>%4$s</option></select>',
				selected( $s['default_currency'], 'alt', false ),
				selected( $s['default_currency'], 'base', false ),
				esc_html( $s['alt_currency'] ),
				esc_html( $s['base_currency'] )
			)
		);
		self::close();

		// Leads.
		self::open( 'leads' );
		self::row( __( 'Lead form', 'solo-estate' ), self::checkbox( 'leads_enabled', $s['leads_enabled'], __( 'Enabled', 'solo-estate' ) ) );
		self::row(
			__( 'Show form on', 'solo-estate' ),
			sprintf(
				'<select name="s[lead_show_on]"><option value="flat"%1$s>%4$s</option><option value="all"%2$s>%5$s</option><option value="none"%3$s>%6$s</option></select>',
				selected( $s['lead_show_on'], 'flat', false ),
				selected( $s['lead_show_on'], 'all', false ),
				selected( $s['lead_show_on'], 'none', false ),
				esc_html__( 'Apartment pages', 'solo-estate' ),
				esc_html__( 'Every level', 'solo-estate' ),
				esc_html__( 'Nowhere (use the [solo_estate_lead_form] shortcode)', 'solo-estate' )
			)
		);
		self::row( __( 'Email recipients', 'solo-estate' ), self::text( 'lead_recipients', $s['lead_recipients'], 'large-text' ) . '<p class="description">' . esc_html__( 'Comma separated. Leave empty to disable emails.', 'solo-estate' ) . '</p>' );
		self::row( __( 'Email subject', 'solo-estate' ), self::text( 'lead_subject', $s['lead_subject'], 'large-text' ) . '<p class="description">' . esc_html__( 'Placeholders: {site} {project} {building} {floor} {flat} {name} {phone}', 'solo-estate' ) . '</p>' );
		self::row( __( 'Webhook URL', 'solo-estate' ), self::text( 'lead_webhook', $s['lead_webhook'], 'large-text', 'url' ) . '<p class="description">' . esc_html__( 'Optional. Every lead is POSTed here as JSON — use it for Zoho CRM (via Zoho Flow), Make, Zapier or your own CRM.', 'solo-estate' ) . '</p>' );
		self::row( __( 'Webhook secret', 'solo-estate' ), self::text( 'lead_webhook_secret', $s['lead_webhook_secret'], 'regular-text code' ) . sprintf( ' <button type="button" class="button" data-solo-estate-generate="s[lead_webhook_secret]">%s</button>', esc_html__( 'Generate', 'solo-estate' ) ) . '<p class="description">' . esc_html__( 'Optional. When set, each webhook request carries an X-Solo-Estate-Signature header: sha256 HMAC of the body with this secret, so your CRM can verify the request really came from this site. Only HTTPS webhook URLs are accepted.', 'solo-estate' ) . '</p>' );
		self::row(
			__( 'Keep leads for', 'solo-estate' ),
			sprintf( '<input type="number" min="0" max="3650" class="small-text" name="s[lead_retention]" value="%d"> %s', (int) $s['lead_retention'], esc_html__( 'days', 'solo-estate' ) ) .
			'<p class="description">' . esc_html__( '0 = keep forever. Older leads are deleted automatically once a day. Visitor IP addresses are always removed after 30 days.', 'solo-estate' ) . '</p>'
		);
		self::row(
			__( 'Visitor IP source', 'solo-estate' ),
			sprintf(
				'<select name="s[ip_header]"><option value=""%1$s>%4$s</option><option value="cf"%2$s>%5$s</option><option value="xff"%3$s>%6$s</option></select>',
				selected( $s['ip_header'], '', false ),
				selected( $s['ip_header'], 'cf', false ),
				selected( $s['ip_header'], 'xff', false ),
				esc_html__( 'Direct connection (REMOTE_ADDR) — default', 'solo-estate' ),
				esc_html__( 'Cloudflare (CF-Connecting-IP)', 'solo-estate' ),
				esc_html__( 'Reverse proxy (X-Forwarded-For)', 'solo-estate' )
			) . '<p class="description">' . esc_html__( 'Used for spam rate limiting. Change it only if the site is behind Cloudflare or a proxy — otherwise visitors could fake their IP.', 'solo-estate' ) . '</p>'
		);
		self::row( __( 'Analytics events', 'solo-estate' ), self::checkbox( 'tracking_events', $s['tracking_events'], __( 'Send "generate_lead" to Google Analytics (gtag) and "Lead" to Meta Pixel when they are present on the site', 'solo-estate' ) ) );
		self::close();

		// Appearance.
		self::open( 'appearance' );
		self::row( __( 'Accent colour', 'solo-estate' ), sprintf( '<input type="text" class="solo-estate-color" name="s[accent_color]" value="%s">', esc_attr( $s['accent_color'] ) ) );
		self::row( __( 'Highlight colour', 'solo-estate' ), sprintf( '<input type="text" class="solo-estate-color" name="s[highlight_color]" value="%s">', esc_attr( $s['highlight_color'] ) ) );
		self::row(
			__( 'Tooltip', 'solo-estate' ),
			sprintf(
				'<select name="s[tooltip_style]"><option value="dark"%1$s>%3$s</option><option value="light"%2$s>%4$s</option></select>',
				selected( $s['tooltip_style'], 'dark', false ),
				selected( $s['tooltip_style'], 'light', false ),
				esc_html__( 'Dark (accent colour)', 'solo-estate' ),
				esc_html__( 'Light (white)', 'solo-estate' )
			) . '<br>' . self::checkbox( 'tooltip_image', $s['tooltip_image'], __( 'Show the apartment plan in the tooltip', 'solo-estate' ) )
		);
		self::row( __( 'Polygon opacity', 'solo-estate' ), self::text( 'fill_opacity', $s['fill_opacity'], 'small-text' ) . ' / ' . self::text( 'hover_opacity', $s['hover_opacity'], 'small-text' ) . '<p class="description">' . esc_html__( 'Normal / on hover, from 0 to 1.', 'solo-estate' ) . '</p>' );
		self::row( __( 'Sold out', 'solo-estate' ), self::checkbox( 'auto_sold_out', $s['auto_sold_out'], __( 'Automatically show projects, phases, buildings and floors whose units are all sold as "Sold out"', 'solo-estate' ) ) . '<br>' . self::checkbox( 'sold_out_nolink', $s['sold_out_nolink'], __( 'Sold out phases and buildings cannot be opened (no link)', 'solo-estate' ) ) . '<p class="description">' . esc_html__( 'The label text is editable under Localization (search: Sold out). A status set by hand (e.g. Sold) and "Can visitors open it?" on each item always win.', 'solo-estate' ) . '</p>' );
		self::row( __( 'Sold out colour', 'solo-estate' ), sprintf( '<input type="text" class="solo-estate-color" name="s[sold_color]" value="%s">', esc_attr( $s['sold_color'] ) ) );
		self::row(
			__( 'Commercial spaces', 'solo-estate' ),
			sprintf( '<input type="text" class="solo-estate-color" name="s[commercial_color]" value="%1$s"> <input type="text" class="solo-estate-color" name="s[commercial_hover]" value="%2$s">', esc_attr( $s['commercial_color'] ), esc_attr( $s['commercial_hover'] ) ) .
			'<p class="description">' . esc_html__( 'Colour and hover colour of commercial spaces on floor plans, so they stand out from apartments. Sold, reserved and rented spaces use their status colour.', 'solo-estate' ) . '</p>'
		);
		self::row( __( 'Apartment filter', 'solo-estate' ), self::checkbox( 'show_filter', $s['show_filter'], __( 'Show the apartment search (rooms, area, price, building) on the projects and project pages', 'solo-estate' ) ) );
		self::row( __( 'Lists under images', 'solo-estate' ), self::checkbox( 'show_lists', $s['show_lists'], __( 'Show a list of floors and apartments next to or below each image (helps on phones and for SEO)', 'solo-estate' ) ) . '<br>' . self::checkbox( 'project_cards', $s['project_cards'], __( 'Also show cards of the buildings below the masterplan', 'solo-estate' ) ) . '<p class="description">' . esc_html__( 'Without a masterplan the buildings are always listed as cards.', 'solo-estate' ) . '</p>' );
		self::close();

		// Style.
		self::open( 'style' );
		echo '<tr><td colspan="2"><p class="description">' . esc_html__( 'How the apartment selector looks on the site. Empty fonts and a size of 0 follow your theme. Accent and highlight colours are on the Appearance tab.', 'solo-estate' ) . '</p></td></tr>';
		$web_fonts = '<select name="s[web_font]"><option value="">' . esc_html__( 'None — use the theme font', 'solo-estate' ) . '</option>';
		foreach ( array_keys( Settings::WEB_FONTS ) as $font ) {
			$web_fonts .= sprintf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $font ), selected( $s['web_font'], $font, false ) );
		}
		$web_fonts .= '</select>';
		self::row( __( 'Google Font', 'solo-estate' ), $web_fonts . '<p class="description">' . esc_html__( 'Loads the font from Google Fonts and uses it for the selector. Noto Sans Georgian is a clean modern Georgian font; Inter and Manrope use it for Georgian letters.', 'solo-estate' ) . '</p>' );
		self::row( __( 'Font', 'solo-estate' ), sprintf( '<input type="text" class="regular-text" name="s[font_family]" value="%1$s" placeholder="%2$s">', esc_attr( $s['font_family'] ), esc_attr__( 'Theme font', 'solo-estate' ) ) . '<p class="description">' . esc_html__( 'Advanced: your own font list, e.g. Manrope, "Noto Sans Georgian", sans-serif — Latin letters in Manrope, Georgian in Noto Sans Georgian. It wins over the Google Font above.', 'solo-estate' ) . '</p>' );
		self::row( __( 'Text size', 'solo-estate' ), sprintf( '<input type="number" min="0" max="24" class="small-text" name="s[font_size]" value="%d"> px', (int) $s['font_size'] ) . '<p class="description">' . esc_html__( '0 = the theme size. 15–17 px suits most sites; use it when the theme makes everything too large or too small.', 'solo-estate' ) . '</p>' );
		self::row( __( 'Corner rounding', 'solo-estate' ), sprintf( '<input type="number" min="0" max="30" class="small-text" name="s[radius]" value="%d"> px', (int) $s['radius'] ) . '<p class="description">' . esc_html__( '0 = square corners.', 'solo-estate' ) . '</p>' );
		$colors = array(
			'text_color'  => __( 'Text', 'solo-estate' ),
			'muted_color' => __( 'Secondary text', 'solo-estate' ),
			'line_color'  => __( 'Lines and borders', 'solo-estate' ),
			'soft_color'  => __( 'Light backgrounds', 'solo-estate' ),
			'card_color'  => __( 'Cards and panels', 'solo-estate' ),
		);
		foreach ( $colors as $key => $label ) {
			self::row( $label, sprintf( '<input type="text" class="solo-estate-color" name="s[%1$s]" value="%2$s" data-default-color="%3$s">', esc_attr( $key ), esc_attr( $s[ $key ] ), esc_attr( Settings::defaults()[ $key ] ) ) );
		}
		self::row(
			__( 'Buttons', 'solo-estate' ),
			sprintf( '<input type="text" class="solo-estate-color" name="s[button_color]" value="%1$s"> <input type="text" class="solo-estate-color" name="s[button_text]" value="%2$s" data-default-color="#ffffff">', esc_attr( $s['button_color'] ), esc_attr( $s['button_text'] ) ) .
			'<p class="description">' . esc_html__( 'Background and text. Leave the background empty to use the accent colour.', 'solo-estate' ) . '</p>'
		);
		self::row(
			__( 'Text on the accent colour', 'solo-estate' ),
			sprintf( '<input type="text" class="solo-estate-color" name="s[accent_text]" value="%s" data-default-color="#ffffff">', esc_attr( $s['accent_text'] ) ) .
			'<p class="description">' . esc_html__( 'Tooltips, active tabs and chips, the price box and other places with the accent colour as background.', 'solo-estate' ) . '</p>'
		);
		self::row( __( 'Shadows', 'solo-estate' ), self::checkbox( 'card_shadow', $s['card_shadow'], __( 'Soft shadow under cards and panels', 'solo-estate' ) ) );
		self::close();

		if ( Admin::is_administrator() ) {
			self::render_access();
		}

		// Advanced.
		if ( Admin::is_administrator() ) {
			self::render_advanced();
		}

		// The Localization tab saves with its own button.
		printf( '<div class="solo-estate-settings-submit"%s>', Localization::TAB === self::$active ? ' hidden' : '' );
		submit_button();
		echo '</div></form>';
		self::render_style_transfer();
		Localization::render_panel( Localization::TAB === self::$active );
		echo '</div>';
	}

	/**
	 * Advanced tab (administrators only): deleting all data on uninstall, shortcodes.
	 */
	private static function render_advanced() {
		self::open( 'advanced' );
		self::row( __( 'Uninstall', 'solo-estate' ), self::checkbox( 'delete_on_uninstall', Settings::get( 'delete_on_uninstall' ), __( 'Delete all Solo Estate data (projects, apartments, leads, settings) when the plugin is deleted', 'solo-estate' ) ) );
		self::row( __( 'Shortcodes', 'solo-estate' ), '<code>[solo_estate]</code> — ' . esc_html__( 'all projects: Projects → project → building → floor → apartment', 'solo-estate' ) . '<br><code>[solo_estate id="1"]</code> — ' . esc_html__( 'one project only', 'solo-estate' ) . '<br><code>[solo_estate_lead_form]</code> — ' . esc_html__( 'lead form only', 'solo-estate' ) . '<p class="description">' . esc_html__( 'Works in any theme and page builder (Avada: use a Text Block or Code Block element). A Gutenberg block "Solo Estate project" is also available.', 'solo-estate' ) . '</p>' );
		self::close();
	}

	/**
	 * Access tab: which WordPress roles work in Solo Estate, and at what level. Administrators
	 * only: a Solo Estate manager could otherwise hand the plugin (and its data and leads) to
	 * every editor or subscriber.
	 */
	private static function render_access() {
		self::open( 'access' );
		self::row(
			__( 'Solo Estate roles', 'solo-estate' ),
			'<ul class="ul-disc"><li><strong>' . esc_html__( 'Manager', 'solo-estate' ) . '</strong> — ' . esc_html__( 'everything, including deleting, statuses, specification list, import and settings.', 'solo-estate' ) . '</li>' .
			'<li><strong>' . esc_html__( 'Marketing', 'solo-estate' ) . '</strong> — ' . esc_html__( 'adds and changes projects, buildings, floors and units: photos, prices, rooms and new sections, statuses. Cannot delete anything.', 'solo-estate' ) . '</li>' .
			'<li><strong>' . esc_html__( 'Sales', 'solo-estate' ) . '</strong> — ' . esc_html__( 'sees everything and changes statuses only (e.g. For sale → Reserved → Sold); reads leads.', 'solo-estate' ) . '</li></ul>' .
			'<p class="description">' . esc_html__( 'Give a user the role "Solo Estate Manager", "Solo Estate Marketing" or "Solo Estate Sales" under Users, or give an existing WordPress role a level below. Administrators are always managers.', 'solo-estate' ) . '</p>'
		);
		$levels = array(
			''          => __( 'No access', 'solo-estate' ),
			'sales'     => __( 'Sales', 'solo-estate' ),
			'marketing' => __( 'Marketing', 'solo-estate' ),
			'manager'   => __( 'Manager', 'solo-estate' ),
		);
		foreach ( wp_roles()->role_objects as $slug => $role ) {
			if ( 'administrator' === $slug || 0 === strpos( $slug, 'solo_estate_' ) ) {
				continue;
			}
			$current = Install::role_level( $role );
			$select  = sprintf( '<select name="roles[%s]">', esc_attr( $slug ) );
			foreach ( $levels as $key => $label ) {
				$select .= sprintf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $key ), selected( $current, $key, false ), esc_html( $label ) );
			}
			self::row( translate_user_role( wp_roles()->role_names[ $slug ] ), $select . '</select>' );
		}
		self::close();
	}

	private static function open( $key ) {
		printf( '<div class="solo-estate-tab-panel%2$s" data-panel="%1$s"><table class="form-table" role="presentation"><tbody>', esc_attr( $key ), self::$active === $key ? ' is-active' : '' );
	}

	private static function close() {
		echo '</tbody></table></div>';
	}

	/**
	 * Row with pre-escaped HTML.
	 *
	 * @param string $label Label.
	 * @param string $html  Escaped HTML.
	 */
	private static function row( $label, $html ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . $html . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}

	private static function text( $key, $value, $class = 'regular-text', $type = 'text' ) {
		return sprintf( '<input type="%4$s" class="%3$s" name="s[%1$s]" value="%2$s">', esc_attr( $key ), esc_attr( $value ), esc_attr( $class ), esc_attr( $type ) );
	}

	private static function checkbox( $key, $value, $label = '' ) {
		return sprintf( '<label><input type="checkbox" name="s[%1$s]" value="1"%2$s> %3$s</label>', esc_attr( $key ), checked( $value, 1, false ), esc_html( $label ) );
	}

	/**
	 * Export / import / reset of the style (its own forms, below the settings form).
	 */
	private static function render_style_transfer() {
		echo '<div class="solo-estate-tab-panel solo-estate-card solo-estate-style-transfer" data-panel="style">';
		echo '<h2>' . esc_html__( 'Import / export style', 'solo-estate' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Colours, fonts, sizes, rounding, tooltip and polygon settings in one JSON file. Use it to copy a look to another site or keep a backup. Other settings (prices, leads, texts) are not included.', 'solo-estate' ) . '</p>';

		printf(
			'<p><a class="button" href="%1$s">%2$s</a></p>',
			esc_url( wp_nonce_url( add_query_arg( 'action', 'solo_estate_style_export', admin_url( 'admin-post.php' ) ), 'solo_estate_style_export' ) ),
			esc_html__( 'Download style (.json)', 'solo-estate' )
		);

		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'solo_estate_style_import' );
		echo '<input type="hidden" name="action" value="solo_estate_style_import">';
		echo '<p><label><strong>' . esc_html__( 'Upload a style file', 'solo-estate' ) . '</strong><br><input type="file" name="style_file" accept=".json,application/json"></label></p>';
		echo '<p><label><strong>' . esc_html__( 'or paste the JSON code', 'solo-estate' ) . '</strong><br><textarea name="style_json" rows="6" class="large-text code" placeholder="{ &quot;type&quot;: &quot;solo-estate-style&quot;, &quot;settings&quot;: { … } }"></textarea></label></p>';
		submit_button( __( 'Import style', 'solo-estate' ), 'primary', 'submit', false );
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="solo-estate-style-reset">';
		wp_nonce_field( 'solo_estate_style_reset' );
		echo '<input type="hidden" name="action" value="solo_estate_style_reset">';
		submit_button( __( 'Reset style to defaults', 'solo-estate' ), 'secondary', 'submit', false );
		echo '</form></div>';
	}

	/**
	 * Downloads the style settings as JSON.
	 */
	public static function handle_style_export() {
		Admin::check( 'solo_estate_style_export' );
		$data = array(
			'type'     => 'solo-estate-style',
			'version'  => 1,
			'plugin'   => SOLO_ESTATE_VERSION,
			'site'     => home_url( '/' ),
			'date'     => gmdate( 'c' ),
			'settings' => Settings::style(),
		);
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=solo-estate-style-' . gmdate( 'Y-m-d' ) . '.json' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download.
		exit;
	}

	/**
	 * Applies an uploaded or pasted style file.
	 */
	public static function handle_style_import() {
		Admin::check( 'solo_estate_style_import' );
		$back = Admin::url( 'solo-estate-settings' ) . '#style';

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$json = isset( $_POST['style_json'] ) ? trim( (string) wp_unslash( $_POST['style_json'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded and sanitized per key below.
		$tmp  = isset( $_FILES['style_file']['tmp_name'] ) ? (string) $_FILES['style_file']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$size = isset( $_FILES['style_file']['size'] ) ? (int) $_FILES['style_file']['size'] : 0;
		// phpcs:enable
		if ( '' === $json && '' !== $tmp && is_uploaded_file( $tmp ) && $size > 0 && $size <= 100 * KB_IN_BYTES ) {
			$json = (string) file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local upload.
		}
		if ( strlen( $json ) > 100 * KB_IN_BYTES ) {
			Admin::redirect( $back, 'error' );
		}
		$data = json_decode( preg_replace( '/^\xEF\xBB\xBF/', '', $json ), true );
		// Accept the full export file or just the "settings" object.
		if ( is_array( $data ) && isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$data = $data['settings'];
		}
		$applied = is_array( $data ) ? Settings::apply_style( $data ) : 0;
		Admin::redirect( $back, $applied ? 'style_imported' : 'style_invalid' );
	}

	/**
	 * Puts every style setting back to its default.
	 */
	public static function handle_style_reset() {
		Admin::check( 'solo_estate_style_reset' );
		Settings::apply_style( array_intersect_key( Settings::defaults(), array_flip( Settings::STYLE_KEYS ) ) );
		Admin::redirect( Admin::url( 'solo-estate-settings' ) . '#style', 'updated' );
	}

	/**
	 * Fetches the National Bank of Georgia rate now.
	 */
	public static function handle_refresh_rate() {
		Admin::check( 'solo_estate_refresh_rate' );
		Admin::redirect( Admin::url( 'solo-estate-settings' ) . '#prices', Rates::refresh() ? 'updated' : 'error' );
	}

	/**
	 * Saves settings and texts.
	 */
	public static function handle_save() {
		Admin::check( 'solo_estate_save_settings' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified above, sanitized in save().
		$input = isset( $_POST['s'] ) && is_array( $_POST['s'] ) ? $_POST['s'] : array();
		// Only administrators see the Advanced tab; others keep its setting as it is.
		if ( ! Admin::is_administrator() ) {
			$input['delete_on_uninstall'] = Settings::get( 'delete_on_uninstall' );
		}
		Settings::save( $input );
		if ( isset( $_POST['roles'] ) && is_array( $_POST['roles'] ) && Admin::is_administrator() ) {
			$map = array();
			foreach ( wp_unslash( $_POST['roles'] ) as $slug => $level ) {
				$level                        = sanitize_key( $level );
				$map[ sanitize_key( $slug ) ] = isset( Install::LEVEL_CAPS[ $level ] ) ? $level : '';
			}
			Install::apply_role_levels( $map );
		}
		// phpcs:enable
		Rates::maybe_schedule();
		Admin::redirect( Admin::url( 'solo-estate-settings' ) );
	}
}
