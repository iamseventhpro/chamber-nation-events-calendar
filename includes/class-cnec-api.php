<?php
/**
 * Server-side client for the Chamber Widgets events API.
 *
 * Every request happens in PHP while the page renders, so events are part of
 * the HTML that search engines and AI crawlers receive.
 *
 * @package ChamberNationEventsCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fetches, caches and normalizes events and categories.
 */
class CNEC_API {

	/**
	 * API base URL.
	 */
	const API_BASE = 'https://auth.chamberwidgets.com/cn-api/';

	/**
	 * Option holding the cache version; bumping it invalidates all entries.
	 */
	const CACHE_VERSION_OPTION = 'cnec_cache_version';

	/**
	 * Settings for this request (saved settings plus shortcode overrides).
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Sanitized settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Invalidate all cached API responses.
	 */
	public static function flush_cache() {
		update_option( self::CACHE_VERSION_OPTION, (int) get_option( self::CACHE_VERSION_OPTION, 1 ) + 1, false );
	}

	/**
	 * Events for a month, or for a whole year when $month is null.
	 *
	 * @param int         $year     Year.
	 * @param int|null    $month    Month 1-12, or null for the whole year.
	 * @param string      $category Category ID or ''.
	 * @return array[]|WP_Error Normalized events sorted by date and time.
	 */
	public function get_events( $year, $month = null, $category = '' ) {
		if ( '' === $this->settings['org_id'] ) {
			return new WP_Error( 'cnec_no_org', __( 'No Organization ID is configured. Set it under CN Events in the dashboard.', 'chamber-nation-events-calendar' ) );
		}

		// Mirrors the widget's request format exactly.
		$search = '&org_id=' . $this->settings['org_id'] . '&year=' . (int) $year;
		if ( null !== $month ) {
			$search .= '&month=' . sprintf( '%02d', (int) $month );
		}
		$search .= '&day=' . ( '' !== $category ? '&category=' . $category : '' );

		$raw = $this->cached(
			'ev',
			array( $this->settings['server'], $search ),
			function () use ( $search ) {
				$data = $this->request(
					'POST',
					self::API_BASE . rawurlencode( $this->settings['server'] ) . '/calendar/events',
					array( 'searchValues' => $search )
				);
				return is_wp_error( $data ) ? $data : array_values( array_filter( array_map( array( __CLASS__, 'slim_record' ), $data ) ) );
			}
		);

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$events = array();
		foreach ( (array) $raw as $record ) {
			$event = $this->normalize_event( $record );
			if ( $event ) {
				$events[ $event['key'] ] = $event;
			}
		}
		$events = array_values( $events );
		usort( $events, array( __CLASS__, 'compare_events' ) );

		return $events;
	}

	/**
	 * Category list for the organization.
	 *
	 * @return array|WP_Error Map of category ID => name.
	 */
	public function get_categories() {
		if ( '' === $this->settings['org_id'] ) {
			return new WP_Error( 'cnec_no_org', __( 'No Organization ID is configured.', 'chamber-nation-events-calendar' ) );
		}

		$url = add_query_arg( 'org_id', rawurlencode( $this->settings['org_id'] ), self::API_BASE . rawurlencode( $this->settings['server'] ) . '/calendar/categories' );
		$raw = $this->cached(
			'cat',
			array( $url ),
			function () use ( $url ) {
				return $this->request( 'GET', $url );
			},
			6 * HOUR_IN_SECONDS
		);

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$categories = array();
		foreach ( (array) $raw as $cat ) {
			if ( is_array( $cat ) && isset( $cat['calendar_type_id'], $cat['name'] ) ) {
				$categories[ (string) $cat['calendar_type_id'] ] = self::clean_text( $cat['name'] );
			}
		}
		return $categories;
	}

	/**
	 * Sort by date, then start time.
	 *
	 * @param array $a Event.
	 * @param array $b Event.
	 * @return int
	 */
	public static function compare_events( $a, $b ) {
		return array( $a['date'], $a['start_time'] ) <=> array( $b['date'], $b['start_time'] );
	}

	/**
	 * Transient-cached fetch with a last-known-good fallback and a short
	 * error cache so an API outage doesn't slow every page view.
	 *
	 * @param string   $type  Cache namespace.
	 * @param array    $parts Values identifying the request.
	 * @param callable $fetch Returns decoded data or WP_Error.
	 * @param int|null $ttl   Fresh TTL in seconds.
	 * @return mixed|WP_Error
	 */
	private function cached( $type, array $parts, callable $fetch, $ttl = null ) {
		$hash      = md5( wp_json_encode( $parts ) );
		$fresh_key = 'cnec_' . $type . '_' . md5( $hash . '|' . get_option( self::CACHE_VERSION_OPTION, 1 ) );
		$stale_key = 'cnec_stale_' . $type . '_' . $hash;
		$error_key = 'cnec_err_' . $type . '_' . $hash;

		$fresh = get_transient( $fresh_key );
		if ( false !== $fresh ) {
			return $fresh;
		}

		$stale = get_transient( $stale_key );
		$error = get_transient( $error_key );
		if ( false !== $error ) {
			return false !== $stale ? $stale : new WP_Error( 'cnec_api_error', $error );
		}

		$data = $fetch();
		if ( is_wp_error( $data ) ) {
			set_transient( $error_key, $data->get_error_message(), 5 * MINUTE_IN_SECONDS );
			return false !== $stale ? $stale : $data;
		}

		if ( null === $ttl ) {
			$ttl = (int) $this->settings['cache_minutes'] * MINUTE_IN_SECONDS;
		}
		set_transient( $fresh_key, $data, $ttl );
		set_transient( $stale_key, $data, WEEK_IN_SECONDS );

		return $data;
	}

	/**
	 * Perform an HTTP request and decode the JSON body.
	 *
	 * @param string     $method GET or POST.
	 * @param string     $url    URL.
	 * @param array|null $body   Form fields for POST.
	 * @return array|WP_Error
	 */
	private function request( $method, $url, $body = null ) {
		$args = array(
			'method'  => $method,
			'timeout' => 15,
			'headers' => array(
				'Accept'  => 'application/json',
				'Referer' => home_url( '/' ),
			),
		);
		if ( null !== $body ) {
			$args['body'] = $body;
		}

		/**
		 * Filter the HTTP arguments used for API requests.
		 *
		 * @param array  $args HTTP args.
		 * @param string $url  Request URL.
		 */
		$response = wp_remote_request( $url, apply_filters( 'cnec_http_args', $args, $url ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			/* translators: %d: HTTP status code */
			return new WP_Error( 'cnec_http', sprintf( __( 'The events API returned HTTP %d.', 'chamber-nation-events-calendar' ), $code ) );
		}

		$body = trim( wp_remote_retrieve_body( $response ) );
		$data = json_decode( $body, true );
		if ( null === $data && '' !== $body ) {
			// The API reports problems such as "Org Not Found - XYZ" as plain text.
			return new WP_Error(
				'cnec_api_message',
				/* translators: %s: message returned by the API */
				sprintf( __( 'The events API said: %s', 'chamber-nation-events-calendar' ), wp_html_excerpt( sanitize_text_field( $body ), 200, '…' ) )
			);
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Reduce an API record to the fields the calendar uses before caching.
	 * Year-wide responses can exceed 1 MB, mostly description HTML, so the
	 * HTML is replaced by its plain text and first image.
	 *
	 * @param mixed $raw API record.
	 * @return array|null
	 */
	public static function slim_record( $raw ) {
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$keep = array(
			'calendar_date_id', 'eventid', 'name', 'startdate', 'enddate', 'start_time', 'end_time',
			'cd_time_override_ind', 'cd_start_time', 'cd_end_time', 'cd_cancelled_ind', 'cd_cancelled_note',
			'timezone', 'cal_types', 'location_name', 'address', 'address2', 'city', 'state', 'zip',
			'country_code', 'c_lat', 'c_lng', 'cal_image_full', 'cal_image', 'cal_image_url',
			'cal_sponsor_logo_1', 'cal_sponsor_logo_2', 'more_info_link', 'alt_landing_page',
			'event_reg_link', 'alt_ev_reg_url',
		);
		$slim = array_intersect_key( $raw, array_flip( $keep ) );

		$html = ( isset( $raw['description'] ) && is_scalar( $raw['description'] ) ? $raw['description'] : '' )
			. ' ' . ( isset( $raw['details'] ) && is_scalar( $raw['details'] ) ? $raw['details'] : '' );

		$text = wp_strip_all_tags( html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$text = trim( preg_replace( '/\s+/u', ' ', str_replace( "\xC2\xA0", ' ', $text ) ) );

		$slim['_text'] = wp_html_excerpt( $text, 2000, '…' );
		$slim['_img']  = preg_match( '/<img [^>]*src=["\']([^"\'>]+)["\']/i', $html, $m ) ? html_entity_decode( $m[1] ) : '';

		return $slim;
	}

	/**
	 * Convert an API record into the internal event shape.
	 *
	 * @param mixed $raw API record.
	 * @return array|null
	 */
	public function normalize_event( $raw ) {
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$s = static function ( $key ) use ( $raw ) {
			return isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? trim( (string) $raw[ $key ] ) : '';
		};

		$date = $s( 'startdate' );
		if ( ! self::is_date( $date ) ) {
			return null;
		}

		$start_time = $s( 'start_time' );
		$end_time   = $s( 'end_time' );
		if ( '' !== $s( 'cd_time_override_ind' ) && self::is_time( $s( 'cd_start_time' ) ) ) {
			$start_time = $s( 'cd_start_time' );
			$end_time   = $s( 'cd_end_time' );
		}
		$start_time = self::is_time( $start_time ) ? substr( $start_time, 0, 5 ) : '';
		$end_time   = self::is_time( $end_time ) ? substr( $end_time, 0, 5 ) : '';
		if ( '00:00' === $start_time && in_array( $end_time, array( '', '00:00' ), true ) ) {
			$start_time = '';
			$end_time   = '';
		}

		$end_date = $s( 'enddate' );
		if ( ! self::is_date( $end_date ) || $end_date < $date ) {
			$end_date = '';
		}

		$tz_abbr = $s( 'timezone' );
		$tz      = self::timezone_for( $tz_abbr );
		$start   = new DateTimeImmutable( $date . ' ' . ( $start_time ? $start_time : '00:00' ), $tz );
		$end     = null;
		if ( $end_time ) {
			$end = new DateTimeImmutable( ( $end_date ? $end_date : $date ) . ' ' . $end_time, $tz );
			if ( $end <= $start && ! $end_date ) {
				$end = $end->modify( '+1 day' );
			}
		}

		if ( ! isset( $raw['_text'] ) ) {
			$raw = self::slim_record( $raw );
		}
		$text = (string) $raw['_text'];

		$event_id = $s( 'eventid' );
		$cname    = CNEC_Settings::cname( $this->settings );
		$url      = esc_url_raw( $s( 'more_info_link' ) );
		if ( ! $url && $event_id ) {
			$url = $cname . '/Calendar/moreinfo.php?eventid=' . rawurlencode( $event_id );
		}

		preg_match_all( '/\d+/', $s( 'cal_types' ), $cats );

		$location = array(
			'name'    => self::clean_text( $s( 'location_name' ) ),
			'street'  => self::clean_text( trim( $s( 'address' ) . ' ' . $s( 'address2' ) ) ),
			'city'    => self::clean_text( $s( 'city' ) ),
			'region'  => self::clean_text( $s( 'state' ) ),
			'postal'  => self::clean_text( $s( 'zip' ) ),
			'country' => $s( 'country_code' ) ? strtoupper( $s( 'country_code' ) ) : $this->settings['default_country'],
			'lat'     => is_numeric( $s( 'c_lat' ) ) ? (float) $s( 'c_lat' ) : null,
			'lng'     => is_numeric( $s( 'c_lng' ) ) ? (float) $s( 'c_lng' ) : null,
		);

		$online = '' === $location['street'] && '' === $location['city']
			&& (bool) preg_match( '/\b(zoom|virtual|online|webinar|teams|google meet|livestream)\b/i', $location['name'] );

		$image          = $this->image_for( $raw );
		$is_placeholder = false;
		if ( ! $image && $this->settings['show_placeholder'] ) {
			$image          = $this->settings['placeholder_image'] ? $this->settings['placeholder_image'] : CNEC_URL . 'assets/images/placeholder.svg';
			$is_placeholder = true;
		}

		$event = array(
			'key'            => $s( 'calendar_date_id' ) ? $s( 'calendar_date_id' ) : $event_id . '-' . $date,
			'id'             => $event_id,
			'name'           => self::clean_text( $s( 'name' ) ),
			'date'           => $date,
			'end_date'       => $end_date,
			'start_time'     => $start_time,
			'end_time'       => $end_time,
			'all_day'        => '' === $start_time,
			'tz_abbr'        => $tz_abbr,
			'start'          => $start,
			'end'            => $end,
			'text'           => $text,
			'image'          => $image,
			'image_is_placeholder' => $is_placeholder,
			'location'       => $location,
			'online'         => $online,
			'url'            => $url,
			'landing'        => esc_url_raw( $s( 'alt_landing_page' ) ),
			'reg_url'        => esc_url_raw( $s( 'event_reg_link' ) ? $s( 'event_reg_link' ) : $s( 'alt_ev_reg_url' ) ),
			'cancelled'      => '' !== $s( 'cd_cancelled_ind' ),
			'cancelled_note' => sanitize_text_field( $s( 'cd_cancelled_note' ) ),
			'categories'     => $cats[0],
		);

		if ( '' === $event['name'] ) {
			$event['name'] = __( '(Untitled)', 'chamber-nation-events-calendar' );
		}

		/**
		 * Filter a normalized event. Return null to drop it.
		 *
		 * @param array|null $event Normalized event.
		 * @param array      $raw   Raw API record.
		 */
		return apply_filters( 'cnec_event', $event, $raw );
	}

	/**
	 * Pick the event thumbnail, following the widget's priority order and
	 * skipping PDFs.
	 *
	 * @param array $raw Slimmed API record.
	 * @return string
	 */
	private function image_for( array $raw ) {
		$get   = static function ( $key ) use ( $raw ) {
			return isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? trim( (string) $raw[ $key ] ) : '';
		};
		$cname = CNEC_Settings::cname( $this->settings );
		$host  = CNEC_Settings::host( $this->settings );

		$candidates = array( $get( 'cal_image_full' ) );
		if ( $get( 'cal_image' ) ) {
			$candidates[] = $cname . '/Calendar/cal_image/' . rawurlencode( $get( 'cal_image' ) );
		}
		// cal_image_url is sometimes a web page rather than an image.
		if ( preg_match( '/\.(jpe?g|png|gif|webp|avif|svg)(\?|$)/i', $get( 'cal_image_url' ) ) ) {
			$candidates[] = $get( 'cal_image_url' );
		}
		foreach ( array( 'cal_sponsor_logo_1', 'cal_sponsor_logo_2' ) as $logo ) {
			if ( $get( $logo ) ) {
				$candidates[] = $host . '/members/secure/calendar/logos/' . rawurlencode( $get( $logo ) );
			}
		}
		$candidates[] = $get( '_img' );

		foreach ( $candidates as $candidate ) {
			$candidate = esc_url_raw( $candidate );
			if ( $candidate && ! preg_match( '/\.pdf(\?|$)/i', $candidate ) ) {
				return $candidate;
			}
		}
		return '';
	}

	/**
	 * Map the API's timezone abbreviation to a region so DST is correct
	 * (the API reports "PST" year-round).
	 *
	 * @param string $abbr Abbreviation such as PST.
	 * @return DateTimeZone
	 */
	public static function timezone_for( $abbr ) {
		$map = array(
			'PST'  => 'America/Los_Angeles',
			'PDT'  => 'America/Los_Angeles',
			'PT'   => 'America/Los_Angeles',
			'MST'  => 'America/Denver',
			'MDT'  => 'America/Denver',
			'MT'   => 'America/Denver',
			'CST'  => 'America/Chicago',
			'CDT'  => 'America/Chicago',
			'CT'   => 'America/Chicago',
			'EST'  => 'America/New_York',
			'EDT'  => 'America/New_York',
			'ET'   => 'America/New_York',
			'AKST' => 'America/Anchorage',
			'AKDT' => 'America/Anchorage',
			'HST'  => 'Pacific/Honolulu',
			'AST'  => 'America/Halifax',
			'ADT'  => 'America/Halifax',
		);

		/**
		 * Filter the timezone abbreviation map.
		 *
		 * @param string[] $map Abbreviation => IANA timezone.
		 */
		$map  = apply_filters( 'cnec_timezone_map', $map );
		$abbr = strtoupper( trim( $abbr ) );

		try {
			if ( isset( $map[ $abbr ] ) ) {
				return new DateTimeZone( $map[ $abbr ] );
			}
			if ( '' !== $abbr ) {
				return new DateTimeZone( $abbr );
			}
		} catch ( Exception $e ) {
			// Fall through to the site timezone.
		}
		return wp_timezone();
	}

	/**
	 * Decode entities and repair double-encoded UTF-8 (UTF-8 bytes that were
	 * read as Latin-1), like the widget's fixEncoding().
	 *
	 * @param string $str Text.
	 * @return string
	 */
	public static function clean_text( $str ) {
		$str = trim( html_entity_decode( (string) $str, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

		// Only strings made entirely of code points <= U+00FF with some above
		// U+007F can be mojibake.
		if ( '' !== $str && preg_match( '/[\x{80}-\x{FF}]/u', $str ) && ! preg_match( '/[^\x{00}-\x{FF}]/u', $str ) ) {
			$bytes = '';
			foreach ( preg_split( '//u', $str, -1, PREG_SPLIT_NO_EMPTY ) as $char ) {
				$bytes .= 1 === strlen( $char ) ? $char : chr( ( ( ord( $char[0] ) & 0x1F ) << 6 ) | ( ord( $char[1] ) & 0x3F ) );
			}
			if ( preg_match( '//u', $bytes ) ) {
				$str = $bytes;
			}
		}

		return sanitize_text_field( $str );
	}

	/**
	 * Whether a string is a real Y-m-d date.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	public static function is_date( $value ) {
		return (bool) preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/**
	 * Whether a string is an H:i or H:i:s time.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	private static function is_time( $value ) {
		return (bool) preg_match( '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $value );
	}
}
