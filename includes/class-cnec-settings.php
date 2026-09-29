<?php
/**
 * Plugin settings: defaults, storage and sanitizing.
 *
 * @package ChamberNationEventsCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings store. The same sanitizer is used for the admin page and for
 * per-page shortcode attribute overrides.
 */
class CNEC_Settings {

	/**
	 * Option name.
	 */
	const OPTION = 'cnec_settings';

	/**
	 * Boolean setting keys.
	 *
	 * @var string[]
	 */
	private static $booleans = array(
		'show_images',
		'show_placeholder',
		'show_past',
		'show_all_upcoming',
		'show_calendar_mobile',
		'show_excerpt',
		'allow_submit',
		'show_registration',
	);

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'org_id'               => '',
			'server'               => 'org',
			'cname'                => '',
			'category'             => '',
			'cache_minutes'        => 30,
			'columns'              => 1,
			'first_day'            => 'monday',
			'image_fit'            => 'contain',
			'show_images'          => 1,
			'show_placeholder'     => 1,
			'placeholder_image'    => '',
			'show_calendar_mobile' => 1,
			'show_excerpt'         => 1,
			'show_past'            => 0,
			'show_all_upcoming'    => 0,
			'open_method'          => 'modal',
			'allow_submit'         => 0,
			'submit_label'         => __( 'Submit an Event', 'chamber-nation-events-calendar' ),
			'show_registration'    => 1,
			'reg_label'            => __( 'Event Registration', 'chamber-nation-events-calendar' ),
			'organizer_name'       => '',
			'organizer_url'        => '',
			'default_country'      => 'US',
		);
	}

	/**
	 * Available chamber platforms. The key is the API path segment.
	 *
	 * @return array[]
	 */
	public static function servers() {
		/**
		 * Filter the available chamber platforms.
		 *
		 * @param array[] $servers key => array( 'label' => ..., 'host' => ... ).
		 */
		return apply_filters(
			'cnec_servers',
			array(
				'org'    => array(
					'label' => 'ChamberOrganizer (chamberorganizer.com)',
					'host'  => 'https://chamberorganizer.com',
				),
				'ectown' => array(
					'label' => 'ECTownUSA (ectownusa.net)',
					'host'  => 'https://ectownusa.net',
				),
			)
		);
	}

	/**
	 * Saved settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * Platform host for the configured server.
	 *
	 * @param array $settings Settings.
	 * @return string
	 */
	public static function host( array $settings ) {
		$servers = self::servers();
		return isset( $servers[ $settings['server'] ] ) ? $servers[ $settings['server'] ]['host'] : 'https://chamberorganizer.com';
	}

	/**
	 * Base URL for chamber links (custom domain, falling back to the platform host).
	 *
	 * @param array $settings Settings.
	 * @return string
	 */
	public static function cname( array $settings ) {
		return untrailingslashit( $settings['cname'] ? $settings['cname'] : self::host( $settings ) );
	}

	/**
	 * Interpret yes/no/true/1 style values.
	 *
	 * @param mixed $value Raw value.
	 * @return int 1 or 0.
	 */
	public static function to_bool( $value ) {
		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'yes', 'true', 'on' ), true ) ? 1 : 0;
	}

	/**
	 * Sanitize a full settings array. Missing booleans are treated as off,
	 * which is how unchecked checkboxes arrive from the admin form.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$get      = static function ( $key ) use ( $input, $defaults ) {
			return isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : (string) $defaults[ $key ];
		};

		$server   = sanitize_key( $get( 'server' ) );
		$category = $get( 'category' );
		$country  = strtoupper( preg_replace( '/[^A-Za-z]/', '', $get( 'default_country' ) ) );

		$clean = array(
			'org_id'            => sanitize_text_field( $get( 'org_id' ) ),
			'server'            => array_key_exists( $server, self::servers() ) ? $server : $defaults['server'],
			'cname'             => esc_url_raw( untrailingslashit( $get( 'cname' ) ) ),
			'category'          => ctype_digit( $category ) ? $category : '',
			'cache_minutes'     => min( 1440, max( 1, absint( $get( 'cache_minutes' ) ) ) ),
			'columns'           => min( 3, max( 1, absint( $get( 'columns' ) ) ) ),
			'first_day'         => 'sunday' === strtolower( $get( 'first_day' ) ) ? 'sunday' : 'monday',
			'image_fit'         => 'cover' === strtolower( $get( 'image_fit' ) ) ? 'cover' : 'contain',
			'placeholder_image' => esc_url_raw( $get( 'placeholder_image' ) ),
			'open_method'       => in_array( strtolower( $get( 'open_method' ) ), array( 'new_tab', 'tab' ), true ) ? 'new_tab' : 'modal',
			'submit_label'      => sanitize_text_field( $get( 'submit_label' ) ),
			'reg_label'         => sanitize_text_field( $get( 'reg_label' ) ),
			'organizer_name'    => sanitize_text_field( $get( 'organizer_name' ) ),
			'organizer_url'     => esc_url_raw( $get( 'organizer_url' ) ),
			'default_country'   => 2 === strlen( $country ) ? $country : '',
		);

		foreach ( self::$booleans as $key ) {
			$clean[ $key ] = isset( $input[ $key ] ) ? self::to_bool( $input[ $key ] ) : 0;
		}

		return $clean;
	}
}
