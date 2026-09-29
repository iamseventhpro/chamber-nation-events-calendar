<?php
/**
 * Plugin bootstrap.
 *
 * @package ChamberNationEventsCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires hooks together.
 */
final class CNEC_Plugin {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'cn_events';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );

		CNEC_SEO::init();
		CNEC_Updater::init();

		add_action( 'init', array( __CLASS__, 'maybe_flush_rules' ), 99 );
		add_filter( 'cn_llms_txt_enabled', array( __CLASS__, 'llms_enabled' ) );
		add_filter( 'cn_llms_txt_sections', array( __CLASS__, 'llms_sections' ), 10, 2 );
		add_action( 'save_post_page', array( __CLASS__, 'forget_events_page' ) );

		if ( is_admin() ) {
			CNEC_Admin::init();
		}
	}

	/**
	 * Flush rewrite rules once per version, so /llms.txt works after updates
	 * (updates don't run activation hooks).
	 */
	public static function maybe_flush_rules() {
		if ( get_option( 'cnec_rules_version' ) !== CNEC_VERSION ) {
			update_option( 'cnec_rules_version', CNEC_VERSION, false );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * URL of the first published page with the [cn_events] shortcode.
	 *
	 * @return string
	 */
	public static function events_page_url() {
		$id = get_transient( 'cnec_events_page' );
		if ( false === $id ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached in a transient below.
			$id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s ORDER BY ID ASC LIMIT 1",
					'%' . $wpdb->esc_like( '[' . self::SHORTCODE ) . '%'
				)
			);
			set_transient( 'cnec_events_page', $id, DAY_IN_SECONDS );
		}
		return $id ? get_permalink( (int) $id ) : '';
	}

	/**
	 * Re-detect the events page when pages change.
	 */
	public static function forget_events_page() {
		delete_transient( 'cnec_events_page' );
		CN_Llms_Txt::flush();
	}

	/**
	 * Opt in to /llms.txt once an organization is configured.
	 *
	 * @param bool $enabled Enabled.
	 * @return bool
	 */
	public static function llms_enabled( $enabled ) {
		return $enabled || ( '' !== CNEC_Settings::get()['org_id'] && '' !== self::events_page_url() );
	}

	/**
	 * Upcoming events section of llms.txt / llms-full.txt.
	 *
	 * @param array[] $sections Sections.
	 * @param string  $type     index or full.
	 * @return array[]
	 */
	public static function llms_sections( $sections, $type ) {
		$settings = CNEC_Settings::sanitize( CNEC_Settings::get() );
		$page     = self::events_page_url();
		if ( '' === $settings['org_id'] || '' === $page ) {
			return $sections;
		}

		$api    = new CNEC_API( $settings );
		$today  = current_datetime()->format( 'Y-m-d' );
		$month  = new DateTimeImmutable( 'first day of this month', wp_timezone() );
		$months = 'full' === $type ? 3 : 2;
		$events = array();
		for ( $i = 0; $i < $months; $i++ ) {
			$m      = $month->modify( '+' . $i . ' months' );
			$result = $api->get_events( (int) $m->format( 'Y' ), (int) $m->format( 'n' ) );
			if ( ! is_wp_error( $result ) ) {
				foreach ( $result as $event ) {
					if ( $event['date'] >= $today ) {
						$events[ $event['key'] ] = $event;
					}
				}
			}
		}
		usort( $events, array( 'CNEC_API', 'compare_events' ) );
		$events = array_slice( $events, 0, 'full' === $type ? 300 : 40 );

		$body = '- [' . __( 'Events Calendar', 'chamber-nation-events-calendar' ) . '](' . $page . '): ' . __( 'monthly calendar of chamber and community events.', 'chamber-nation-events-calendar' ) . "\n";
		if ( $events ) {
			$body .= "\n### " . __( 'Upcoming events', 'chamber-nation-events-calendar' ) . "\n\n";
			foreach ( $events as $event ) {
				$when = wp_date( 'D, M j, Y', $event['start']->getTimestamp(), $event['start']->getTimezone() );
				if ( ! $event['all_day'] ) {
					$when .= ' ' . wp_date( 'g:i A', $event['start']->getTimestamp(), $event['start']->getTimezone() ) . ( $event['tz_abbr'] ? ' ' . $event['tz_abbr'] : '' );
				}
				$link  = $event['landing'] ? $event['landing'] : $event['url'];
				$where = implode( ', ', array_filter( array( $event['location']['name'], $event['location']['city'] ) ) );
				$body .= '- ' . $when . ': [' . CN_Llms_Txt::line( $event['name'] ) . '](' . $link . ')' . ( $where ? ' — ' . CN_Llms_Txt::line( $where ) : '' ) . "\n";
				if ( 'full' === $type && $event['text'] ) {
					$body .= '  ' . CN_Llms_Txt::line( wp_html_excerpt( $event['text'], 300, '…' ) ) . "\n";
				}
			}
		}

		$sections[] = array(
			'title' => __( 'Events', 'chamber-nation-events-calendar' ),
			'body'  => $body,
		);
		return $sections;
	}

	/**
	 * Register the [cn_events] shortcode.
	 */
	public static function register_shortcode() {
		add_shortcode( self::SHORTCODE, array( 'CNEC_Calendar', 'shortcode' ) );
	}

	/**
	 * Public query vars used for crawlable, server-side navigation.
	 *
	 * @param string[] $vars Registered query vars.
	 * @return string[]
	 */
	public static function register_query_vars( $vars ) {
		$vars[] = CNEC_Calendar::QV_MONTH;
		$vars[] = CNEC_Calendar::QV_DAY;
		$vars[] = CNEC_Calendar::QV_CAT;
		return $vars;
	}

	/**
	 * Asset version: plugin version plus file modification time, so edited
	 * files are never served stale from browser caches.
	 *
	 * @param string $path Path relative to the plugin root.
	 * @return string
	 */
	public static function asset_version( $path ) {
		$mtime = @filemtime( CNEC_DIR . $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return CNEC_VERSION . ( $mtime ? '.' . $mtime : '' );
	}

	/**
	 * Register front-end assets; enqueue early when the page contains the
	 * shortcode so the stylesheet lands in <head>.
	 */
	public static function register_assets() {
		wp_register_style( 'cnec-calendar', CNEC_URL . 'assets/css/calendar.css', array(), self::asset_version( 'assets/css/calendar.css' ) );
		wp_register_script(
			'cnec-calendar',
			CNEC_URL . 'assets/js/calendar.js',
			array(),
			self::asset_version( 'assets/js/calendar.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		if ( CNEC_SEO::page_has_calendar() ) {
			wp_enqueue_style( 'cnec-calendar' );
			wp_enqueue_script( 'cnec-calendar' );
		}
	}
}
