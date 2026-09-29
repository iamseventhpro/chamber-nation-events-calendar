<?php
/**
 * Search-engine hygiene for calendar views: canonical URLs, titles and
 * robots directives, including Yoast SEO, Rank Math and AIOSEO.
 *
 * @package ChamberNationEventsCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * SEO integration.
 */
class CNEC_SEO {

	/**
	 * Months back / forward that stay indexable; beyond that pages are noindex
	 * so crawlers don't index endless empty months.
	 */
	const INDEX_MONTHS_BACK    = 12;
	const INDEX_MONTHS_FORWARD = 24;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'get_canonical_url', array( __CLASS__, 'canonical' ), 20 );
		add_filter( 'wpseo_canonical', array( __CLASS__, 'canonical' ), 20 );
		add_filter( 'rank_math/frontend/canonical', array( __CLASS__, 'canonical' ), 20 );
		add_filter( 'aioseo_canonical_url', array( __CLASS__, 'canonical' ), 20 );

		add_filter( 'document_title_parts', array( __CLASS__, 'title_parts' ), 20 );
		add_filter( 'wpseo_title', array( __CLASS__, 'title_string' ), 20 );
		add_filter( 'rank_math/frontend/title', array( __CLASS__, 'title_string' ), 20 );
		add_filter( 'aioseo_title', array( __CLASS__, 'title_string' ), 20 );

		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 20 );
		add_filter( 'wpseo_robots', array( __CLASS__, 'robots_string' ), 20 );
		add_filter( 'rank_math/frontend/robots', array( __CLASS__, 'robots_rank_math' ), 20 );
	}

	/**
	 * Whether the current singular page contains the calendar shortcode.
	 *
	 * @return bool
	 */
	public static function page_has_calendar() {
		static $has = null;
		if ( null === $has ) {
			$post = is_singular() ? get_queried_object() : null;
			$has  = $post instanceof WP_Post && has_shortcode( $post->post_content, CNEC_Plugin::SHORTCODE );
		}
		return $has;
	}

	/**
	 * Month (YYYY-MM) the request points at, from cn_month or cn_day.
	 *
	 * @return string
	 */
	private static function requested_month() {
		$month = (string) get_query_var( CNEC_Calendar::QV_MONTH );
		if ( CNEC_Calendar::is_month( $month ) ) {
			return $month;
		}
		$day = (string) get_query_var( CNEC_Calendar::QV_DAY );
		return CNEC_API::is_date( $day ) ? substr( $day, 0, 7 ) : '';
	}

	/**
	 * Each month/category view gets its own canonical URL. Day views point
	 * at their month.
	 *
	 * @param string $url Canonical URL.
	 * @return string
	 */
	public static function canonical( $url ) {
		if ( ! $url || ! self::page_has_calendar() ) {
			return $url;
		}

		$args  = array();
		$month = self::requested_month();
		if ( $month ) {
			$args[ CNEC_Calendar::QV_MONTH ] = $month;
		}
		$cat = (string) get_query_var( CNEC_Calendar::QV_CAT );
		if ( ctype_digit( $cat ) && '' === CNEC_Settings::get()['category'] ) {
			$args[ CNEC_Calendar::QV_CAT ] = $cat;
		}

		return $args ? add_query_arg( $args, $url ) : $url;
	}

	/**
	 * Month label for the title, or ''.
	 *
	 * @return string
	 */
	private static function title_month_label() {
		if ( ! self::page_has_calendar() ) {
			return '';
		}
		$month = self::requested_month();
		return $month ? wp_date( 'F Y', ( new DateTimeImmutable( $month . '-01', new DateTimeZone( 'UTC' ) ) )->getTimestamp(), new DateTimeZone( 'UTC' ) ) : '';
	}

	/**
	 * Core title: "Events – October 2026".
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public static function title_parts( $parts ) {
		$label = self::title_month_label();
		if ( $label && isset( $parts['title'] ) ) {
			$parts['title'] .= ' – ' . $label;
		}
		return $parts;
	}

	/**
	 * SEO plugin titles: "October 2026 – {title}".
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public static function title_string( $title ) {
		$label = self::title_month_label();
		return $label && is_string( $title ) && false === strpos( $title, $label ) ? $label . ' – ' . $title : $title;
	}

	/**
	 * Whether this view should be noindex (day views and far-off months).
	 *
	 * @return bool
	 */
	private static function should_noindex() {
		if ( ! self::page_has_calendar() ) {
			return false;
		}
		if ( CNEC_API::is_date( (string) get_query_var( CNEC_Calendar::QV_DAY ) ) ) {
			return true;
		}
		$month = (string) get_query_var( CNEC_Calendar::QV_MONTH );
		if ( ! CNEC_Calendar::is_month( $month ) ) {
			return false;
		}
		$now  = current_datetime()->modify( 'first day of this month' )->setTime( 0, 0 );
		$then = new DateTimeImmutable( $month . '-01', wp_timezone() );
		return $then < $now->modify( '-' . self::INDEX_MONTHS_BACK . ' months' ) || $then > $now->modify( '+' . self::INDEX_MONTHS_FORWARD . ' months' );
	}

	/**
	 * Core robots directives.
	 *
	 * @param array $robots Directives.
	 * @return array
	 */
	public static function robots( $robots ) {
		if ( self::should_noindex() ) {
			unset( $robots['index'] );
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	/**
	 * Yoast robots string.
	 *
	 * @param string $robots Directives.
	 * @return string
	 */
	public static function robots_string( $robots ) {
		return self::should_noindex() ? 'noindex, follow' : $robots;
	}

	/**
	 * Rank Math robots array.
	 *
	 * @param array $robots Directives.
	 * @return array
	 */
	public static function robots_rank_math( $robots ) {
		if ( self::should_noindex() ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'follow';
		}
		return $robots;
	}
}
