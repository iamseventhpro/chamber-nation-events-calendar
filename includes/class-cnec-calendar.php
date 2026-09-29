<?php
/**
 * Server-side port of the Duda "Events Compact" widget.
 *
 * Navigation, category filtering and day selection are ordinary links
 * (?cn_month=, ?cn_day=, ?cn_cat=), so every view is crawlable HTML.
 *
 * @package ChamberNationEventsCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the [cn_events] shortcode.
 */
class CNEC_Calendar {

	/**
	 * Month query var (YYYY-MM).
	 */
	const QV_MONTH = 'cn_month';

	/**
	 * Day query var (YYYY-MM-DD).
	 */
	const QV_DAY = 'cn_day';

	/**
	 * Category query var (numeric ID).
	 */
	const QV_CAT = 'cn_cat';

	/**
	 * Number of calendars rendered on this request (for unique IDs).
	 *
	 * @var int
	 */
	private static $instances = 0;

	/**
	 * Settings for the calendar being rendered.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Request state (view, year, month, day, category).
	 *
	 * @var array
	 */
	private $state;

	/**
	 * Base page URL without calendar query args.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * HTML id of this calendar instance.
	 *
	 * @var string
	 */
	private $uid;

	/**
	 * Shortcode callback. Any setting key can be overridden as an attribute,
	 * e.g. [cn_events category="1152" columns="2" show_all_upcoming="yes"].
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts     = is_array( $atts ) ? array_change_key_case( $atts, CASE_LOWER ) : array();
		$settings = CNEC_Settings::sanitize( shortcode_atts( CNEC_Settings::get(), $atts, CNEC_Plugin::SHORTCODE ) );

		wp_enqueue_style( 'cnec-calendar' );
		wp_enqueue_script( 'cnec-calendar' );

		$calendar = new self( $settings );
		return $calendar->render();
	}

	/**
	 * Constructor.
	 *
	 * @param array $settings Sanitized settings.
	 */
	public function __construct( array $settings ) {
		++self::$instances;
		$this->settings = $settings;
		$this->state    = self::request_state( $settings );
		$this->uid      = 1 === self::$instances ? 'cn-events' : 'cn-events-' . self::$instances;

		$base           = in_the_loop() || is_singular() ? get_permalink() : '';
		$this->base_url = remove_query_arg( array( self::QV_MONTH, self::QV_DAY, self::QV_CAT ), $base ? $base : home_url( '/' ) );
	}

	/**
	 * Resolve the requested view from query vars.
	 *
	 * @param array $settings Settings.
	 * @return array
	 */
	public static function request_state( array $settings ) {
		$today = current_datetime()->format( 'Y-m-d' );
		$state = array(
			'today'           => $today,
			'view'            => 'month',
			'year'            => (int) substr( $today, 0, 4 ),
			'month'           => (int) substr( $today, 5, 2 ),
			'day'             => '',
			'category'        => '',
			'category_locked' => '' !== $settings['category'],
		);

		if ( $state['category_locked'] ) {
			$state['category'] = $settings['category'];
		} else {
			$cat = (string) get_query_var( self::QV_CAT );
			if ( ctype_digit( $cat ) ) {
				$state['category'] = $cat;
			}
		}

		$day   = (string) get_query_var( self::QV_DAY );
		$month = (string) get_query_var( self::QV_MONTH );

		if ( CNEC_API::is_date( $day ) ) {
			$state['view']  = 'day';
			$state['day']   = $day;
			$state['year']  = (int) substr( $day, 0, 4 );
			$state['month'] = (int) substr( $day, 5, 2 );
		} elseif ( self::is_month( $month ) ) {
			$state['year']  = (int) substr( $month, 0, 4 );
			$state['month'] = (int) substr( $month, 5, 2 );
		} elseif ( $settings['show_all_upcoming'] ) {
			$state['view'] = 'upcoming';
		}

		return $state;
	}

	/**
	 * Whether a value is a YYYY-MM month.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	public static function is_month( $value ) {
		return (bool) preg_match( '/^(19|20)\d{2}-(0[1-9]|1[0-2])$/', (string) $value );
	}

	/**
	 * Render the calendar.
	 *
	 * @return string
	 */
	public function render() {
		$settings = $this->settings;
		$state    = $this->state;

		if ( '' === $settings['org_id'] ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="cnc-root"><p class="cn-empty">' . esc_html__( 'CN Events: add your Organization ID under CN Events in the dashboard.', 'chamber-nation-events-calendar' ) . '</p></div>';
			}
			return '';
		}

		$api    = new CNEC_API( $settings );
		$errors = array();

		if ( 'upcoming' === $state['view'] ) {
			$lists = array();
			foreach ( array( $state['year'], $state['year'] + 1 ) as $year ) {
				$result = $api->get_events( $year, null, $state['category'] );
				if ( is_wp_error( $result ) ) {
					$errors[] = $result;
				} else {
					$lists[] = $result;
				}
			}
			$events = self::merge_events( $lists );
		} else {
			$events = $api->get_events( $state['year'], $state['month'], $state['category'] );
			if ( is_wp_error( $events ) ) {
				$errors[] = $events;
				$events   = array();
			}
		}

		$by_date = array();
		foreach ( $events as $event ) {
			$by_date[ $event['date'] ][] = $event;
		}

		$prefix     = sprintf( '%04d-%02d', $state['year'], $state['month'] );
		$month_ts   = $this->utc( $prefix . '-01' )->getTimestamp();
		$list       = array();
		$next_event = null;
		$empty_msg  = '';

		switch ( $state['view'] ) {
			case 'day':
				$list       = isset( $by_date[ $state['day'] ] ) ? $by_date[ $state['day'] ] : array();
				$list_label = $this->format_date( 'F j', $state['day'] );
				$empty_msg  = __( 'No events for this day.', 'chamber-nation-events-calendar' );
				break;

			case 'upcoming':
				$current_prefix = substr( $state['today'], 0, 7 );
				foreach ( $by_date as $date => $day_events ) {
					if ( $date >= $state['today'] || ( $settings['show_past'] && 0 === strpos( $date, $current_prefix ) ) ) {
						$list = array_merge( $list, $day_events );
					}
				}
				$list_label = __( 'All Upcoming Events', 'chamber-nation-events-calendar' );
				$empty_msg  = __( 'No events for this period.', 'chamber-nation-events-calendar' );
				break;

			default:
				foreach ( $by_date as $date => $day_events ) {
					if ( 0 === strpos( $date, $prefix ) && ( $settings['show_past'] || $date >= $state['today'] ) ) {
						$list = array_merge( $list, $day_events );
					}
				}
				$list_label = $this->format_date( 'F Y', $prefix . '-01' );
				if ( ! $list && ! $errors ) {
					$next_event = $this->find_next_event( $api );
				}
				$empty_msg = __( 'No events for this month.', 'chamber-nation-events-calendar' );
		}

		usort( $list, array( 'CNEC_API', 'compare_events' ) );

		$error_msg = '';
		if ( $errors && ! $list ) {
			$error_msg = current_user_can( 'manage_options' )
				/* translators: %s: error message */
				? sprintf( __( 'Events could not be loaded: %s', 'chamber-nation-events-calendar' ), $errors[0]->get_error_message() )
				: __( 'Events are temporarily unavailable. Please check back soon.', 'chamber-nation-events-calendar' );
		}

		$prev = $this->utc( $prefix . '-01' )->modify( '-1 month' );
		$next = $this->utc( $prefix . '-01' )->modify( '+1 month' );

		$categories = null;
		if ( ! $state['category_locked'] ) {
			$categories = $api->get_categories();
		}

		$cname  = CNEC_Settings::cname( $settings );
		$org_q  = rawurlencode( $settings['org_id'] );
		$vars   = array(
			'uid'            => $this->uid,
			'settings'       => $settings,
			'state'          => $state,
			'month_title'    => wp_date( 'F Y', $month_ts, new DateTimeZone( 'UTC' ) ),
			'prev'           => array(
				'url'   => $this->url( array( self::QV_MONTH => $prev->format( 'Y-m' ) ) ),
				'label' => wp_date( 'F', $prev->getTimestamp(), new DateTimeZone( 'UTC' ) ),
			),
			'next'           => array(
				'url'   => $this->url( array( self::QV_MONTH => $next->format( 'Y-m' ) ) ),
				'label' => wp_date( 'F', $next->getTimestamp(), new DateTimeZone( 'UTC' ) ),
			),
			'weekdays'       => $this->weekday_labels(),
			'cells'          => $this->build_cells( $prefix, $by_date ),
			'categories'     => $categories,
			'form_action'    => $this->base_url,
			'form_month'     => 'upcoming' !== $state['view'] && ( 'day' === $state['view'] || '' !== (string) get_query_var( self::QV_MONTH ) ) ? $prefix : '',
			'submit_url'     => $settings['allow_submit'] ? $cname . '/Calendar/submit_event.php?org_id=' . $org_q : '',
			'reg_url'        => $settings['show_registration'] ? $cname . '/members/evr/regmenu2.php?orgcode=' . $org_q : '',
			'list_label'     => $list_label,
			'show_month_url' => 'day' === $state['view'] ? $this->url( array( self::QV_MONTH => $prefix ) ) : '',
			'events'         => $list,
			'empty_message'  => $error_msg ? $error_msg : $empty_msg,
			'next_event'     => $next_event,
			'no_upcoming'    => 'month' === $state['view'] && ! $list && ! $errors && null === $next_event,
			'renderer'       => $this,
		);

		$html = $this->load_template( 'calendar', $vars );

		if ( $list ) {
			$canonical = 'upcoming' === $state['view'] ? $this->url( array(), false ) : $this->url( array( self::QV_MONTH => $prefix ), false );
			$html     .= CNEC_Schema::script_tag( CNEC_Schema::item_list( $list, $settings, $canonical, $list_label ) );
		}

		return $html;
	}

	/**
	 * Merge event lists, de-duplicating by occurrence key.
	 *
	 * @param array[] $lists Lists of events.
	 * @return array[]
	 */
	private static function merge_events( array $lists ) {
		$merged = array();
		foreach ( $lists as $list ) {
			foreach ( $list as $event ) {
				$merged[ $event['key'] ] = $event;
			}
		}
		$merged = array_values( $merged );
		usort( $merged, array( 'CNEC_API', 'compare_events' ) );
		return $merged;
	}

	/**
	 * When the displayed month is empty, find the first event in the next
	 * 12 months (the widget's "next upcoming event" card). Uses whole-year
	 * requests, so at most two cached API calls.
	 *
	 * @param CNEC_API $api API client.
	 * @return array|null array( 'date_label', 'month_label', 'url' ).
	 */
	private function find_next_event( CNEC_API $api ) {
		$first = $this->utc( sprintf( '%04d-%02d-01', $this->state['year'], $this->state['month'] ) );
		$after = $first->format( 'Y-m-t' );
		$limit = $first->modify( '+13 months' )->format( 'Y-m-d' );
		$years = array_unique( array( (int) $first->modify( '+1 month' )->format( 'Y' ), (int) $first->modify( '+12 months' )->format( 'Y' ) ) );

		$best = null;
		foreach ( $years as $year ) {
			$events = $api->get_events( $year, null, $this->state['category'] );
			if ( is_wp_error( $events ) ) {
				continue;
			}
			foreach ( $events as $event ) {
				if ( $event['date'] > $after && $event['date'] < $limit && ( null === $best || $event['date'] < $best ) ) {
					$best = $event['date'];
				}
			}
		}

		if ( null === $best ) {
			return null;
		}

		return array(
			'date_label'  => $this->format_date( 'M j, Y', $best ),
			'month_label' => $this->format_date( 'F Y', $best ),
			'url'         => $this->url( array( self::QV_MONTH => substr( $best, 0, 7 ) ) ),
		);
	}

	/**
	 * Build the 42 cells of the month grid.
	 *
	 * @param string  $prefix  YYYY-MM.
	 * @param array[] $by_date Events keyed by date.
	 * @return array[]
	 */
	private function build_cells( $prefix, array $by_date ) {
		$first     = $this->utc( $prefix . '-01' );
		$dow       = (int) $first->format( 'w' );
		$start_idx = 'sunday' === $this->settings['first_day'] ? $dow : ( $dow + 6 ) % 7;
		$day       = $first->modify( '-' . $start_idx . ' days' );
		$cells     = array();

		for ( $i = 0; $i < 42; $i++ ) {
			$ymd   = $day->format( 'Y-m-d' );
			$count = isset( $by_date[ $ymd ] ) ? count( $by_date[ $ymd ] ) : 0;

			$cells[] = array(
				'ymd'      => $ymd,
				'number'   => (int) $day->format( 'j' ),
				'label'    => $this->format_date( 'F j, Y', $ymd ),
				'out'      => $day->format( 'Y-m' ) !== $prefix,
				'count'    => $count,
				'today'    => $ymd === $this->state['today'],
				'selected' => 'day' === $this->state['view'] && $ymd === $this->state['day'],
				'url'      => $count ? $this->url( array( self::QV_DAY => $ymd ) ) : '',
			);
			$day     = $day->modify( '+1 day' );
		}

		return $cells;
	}

	/**
	 * Weekday column labels in display order.
	 *
	 * @return array[] Each: array( 'short' => 'MO', 'full' => 'Monday' ).
	 */
	private function weekday_labels() {
		global $wp_locale;
		$short = array(
			_x( 'SU', 'weekday abbreviation', 'chamber-nation-events-calendar' ),
			_x( 'MO', 'weekday abbreviation', 'chamber-nation-events-calendar' ),
			_x( 'TU', 'weekday abbreviation', 'chamber-nation-events-calendar' ),
			_x( 'WE', 'weekday abbreviation', 'chamber-nation-events-calendar' ),
			_x( 'TH', 'weekday abbreviation', 'chamber-nation-events-calendar' ),
			_x( 'FR', 'weekday abbreviation', 'chamber-nation-events-calendar' ),
			_x( 'SA', 'weekday abbreviation', 'chamber-nation-events-calendar' ),
		);
		$order = 'sunday' === $this->settings['first_day'] ? array( 0, 1, 2, 3, 4, 5, 6 ) : array( 1, 2, 3, 4, 5, 6, 0 );

		$labels = array();
		foreach ( $order as $i ) {
			$labels[] = array(
				'short' => $short[ $i ],
				'full'  => $wp_locale->get_weekday( $i ),
			);
		}
		return $labels;
	}

	/**
	 * Build a calendar URL, keeping the selected category.
	 *
	 * @param array $args     Query args.
	 * @param bool  $fragment Whether to jump to the calendar on load.
	 * @return string
	 */
	public function url( array $args = array(), $fragment = true ) {
		if ( ! $this->state['category_locked'] && '' !== $this->state['category'] ) {
			$args = array_merge( array( self::QV_CAT => $this->state['category'] ), $args );
		}
		$url = $args ? add_query_arg( $args, $this->base_url ) : $this->base_url;
		return $fragment ? $url . '#' . $this->uid : $url;
	}

	/**
	 * Format a Y-m-d date without timezone shifting.
	 *
	 * @param string $format Date format.
	 * @param string $ymd    Date.
	 * @return string
	 */
	public function format_date( $format, $ymd ) {
		return wp_date( $format, $this->utc( $ymd )->getTimestamp(), new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Card time line, e.g. "10:00 AM – 11:00 AM PST".
	 *
	 * @param array $event Event.
	 * @return string
	 */
	public function time_label( array $event ) {
		if ( ! $event['start_time'] ) {
			return '';
		}
		$format = apply_filters( 'cnec_time_format', 'g:i A' );
		$out    = $this->format_time( $format, $event['start_time'] );
		if ( $event['end_time'] ) {
			$out .= ' – ' . $this->format_time( $format, $event['end_time'] );
		}
		if ( $event['tz_abbr'] ) {
			$out .= ' ' . $event['tz_abbr'];
		}
		return $out;
	}

	/**
	 * Card location line: "Venue • City".
	 *
	 * @param array $event Event.
	 * @return string
	 */
	public function where_label( array $event ) {
		return implode( ' • ', array_filter( array( $event['location']['name'], $event['location']['city'] ) ) );
	}

	/**
	 * Load a template; themes can override at {theme}/cn-events/{name}.php.
	 *
	 * @param string $name Template name.
	 * @param array  $vars Variables.
	 * @return string
	 */
	public function load_template( $name, array $vars ) {
		$file = locate_template( array( 'cn-events/' . $name . '.php' ) );
		if ( ! $file ) {
			$file = CNEC_DIR . 'templates/' . $name . '.php';
		}

		/**
		 * Filter the template path.
		 *
		 * @param string $file Absolute path.
		 * @param string $name Template name.
		 */
		$file = apply_filters( 'cnec_template', $file, $name );

		ob_start();
		( static function ( $cnec_file, $cnec_vars ) {
			extract( $cnec_vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			include $cnec_file;
		} )( $file, $vars );
		return (string) ob_get_clean();
	}

	/**
	 * Format an H:i time.
	 *
	 * @param string $format Format.
	 * @param string $time   H:i.
	 * @return string
	 */
	private function format_time( $format, $time ) {
		return wp_date( $format, $this->utc( '1970-01-01 ' . $time )->getTimestamp(), new DateTimeZone( 'UTC' ) );
	}

	/**
	 * DateTimeImmutable in UTC (used for calendar math and labels only).
	 *
	 * @param string $value Date string.
	 * @return DateTimeImmutable
	 */
	private function utc( $value ) {
		return new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
	}
}
