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

		if ( is_admin() ) {
			CNEC_Admin::init();
		}
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
