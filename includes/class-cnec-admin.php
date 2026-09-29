<?php
/**
 * "CN Events" admin page.
 *
 * @package ChamberNationEventsCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin settings screen.
 */
class CNEC_Admin {

	/**
	 * Menu slug.
	 */
	const PAGE = 'cn-events';

	/**
	 * Settings group.
	 */
	const GROUP = 'cnec_settings_group';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_cnec_clear_cache', array( __CLASS__, 'handle_clear_cache' ) );
		add_action( 'update_option_' . CNEC_Settings::OPTION, array( 'CNEC_API', 'flush_cache' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CNEC_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Top-level "CN Events" menu.
	 */
	public static function add_menu() {
		add_menu_page(
			__( 'CN Events', 'chamber-nation-events-calendar' ),
			__( 'CN Events', 'chamber-nation-events-calendar' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' ),
			'dashicons-calendar-alt',
			30
		);
	}

	/**
	 * Register the option.
	 */
	public static function register_setting() {
		register_setting(
			self::GROUP,
			CNEC_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'CNEC_Settings', 'sanitize' ),
				'default'           => CNEC_Settings::defaults(),
			)
		);
	}

	/**
	 * Media picker script for the placeholder image.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue( $hook ) {
		if ( 'toplevel_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'cnec-admin', CNEC_URL . 'assets/js/admin.js', array( 'jquery' ), CNEC_Plugin::asset_version( 'assets/js/admin.js' ), true );
		wp_localize_script(
			'cnec-admin',
			'cnecAdmin',
			array(
				'chooseTitle'  => __( 'Choose placeholder image', 'chamber-nation-events-calendar' ),
				'chooseButton' => __( 'Use this image', 'chamber-nation-events-calendar' ),
			)
		);
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'chamber-nation-events-calendar' ) . '</a>' );
		return $links;
	}

	/**
	 * Clear cached API responses.
	 */
	public static function handle_clear_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'chamber-nation-events-calendar' ) );
		}
		check_admin_referer( 'cnec_clear_cache' );
		CNEC_API::flush_cache();
		wp_safe_redirect( add_query_arg( 'cnec_cleared', '1', admin_url( 'admin.php?page=' . self::PAGE ) ) );
		exit;
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s   = CNEC_Settings::get();
		$api = new CNEC_API( CNEC_Settings::sanitize( $s ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CN Events', 'chamber-nation-events-calendar' ); ?></h1>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag after a nonce-checked redirect.
			if ( isset( $_GET['cnec_cleared'] ) ) :
				?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Event cache cleared.', 'chamber-nation-events-calendar' ); ?></p></div>
			<?php endif; ?>

			<div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap">
				<form action="options.php" method="post" style="flex:1 1 640px;min-width:0">
					<?php settings_fields( self::GROUP ); ?>

					<h2><?php esc_html_e( 'Connection', 'chamber-nation-events-calendar' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php
						self::text_row( $s, 'org_id', __( 'Organization ID', 'chamber-nation-events-calendar' ), __( 'Your chamber\'s organization code, e.g. HEND.', 'chamber-nation-events-calendar' ) );
						self::server_row( $s );
						self::text_row( $s, 'cname', __( 'Chamber domain', 'chamber-nation-events-calendar' ), __( 'Optional custom domain for event links, e.g. https://mms.yourchamber.com. Leave blank to use the platform domain.', 'chamber-nation-events-calendar' ), 'url' );
						self::category_row( $s, $api );
						self::number_row( $s, 'cache_minutes', __( 'Cache duration', 'chamber-nation-events-calendar' ), __( 'Minutes to keep API results before fetching again.', 'chamber-nation-events-calendar' ), 1, 1440 );
						?>
					</table>

					<h2><?php esc_html_e( 'Layout', 'chamber-nation-events-calendar' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php
						self::select_row(
							$s,
							'columns',
							__( 'Event list columns', 'chamber-nation-events-calendar' ),
							array(
								'1' => '1',
								'2' => '2',
								'3' => '3',
							)
						);
						self::select_row(
							$s,
							'first_day',
							__( 'First day of week', 'chamber-nation-events-calendar' ),
							array(
								'monday' => __( 'Monday', 'chamber-nation-events-calendar' ),
								'sunday' => __( 'Sunday', 'chamber-nation-events-calendar' ),
							)
						);
						self::select_row(
							$s,
							'image_fit',
							__( 'Image fit', 'chamber-nation-events-calendar' ),
							array(
								'contain' => __( 'Contain (show whole image)', 'chamber-nation-events-calendar' ),
								'cover'   => __( 'Cover (fill and crop)', 'chamber-nation-events-calendar' ),
							)
						);
						self::checkbox_row( $s, 'show_images', __( 'Show event images', 'chamber-nation-events-calendar' ) );
						self::checkbox_row( $s, 'show_placeholder', __( 'Show a placeholder when an event has no image', 'chamber-nation-events-calendar' ) );
						self::placeholder_row( $s );
						self::checkbox_row( $s, 'show_calendar_mobile', __( 'Show the month grid on mobile', 'chamber-nation-events-calendar' ) );
						self::checkbox_row( $s, 'show_excerpt', __( 'Show a short description on each event card (recommended for SEO)', 'chamber-nation-events-calendar' ) );
						?>
					</table>

					<h2><?php esc_html_e( 'Events', 'chamber-nation-events-calendar' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php
						self::checkbox_row( $s, 'show_past', __( 'Show past events from the current month', 'chamber-nation-events-calendar' ) );
						self::checkbox_row( $s, 'show_all_upcoming', __( 'Start on "All Upcoming Events" instead of the current month', 'chamber-nation-events-calendar' ) );
						self::select_row(
							$s,
							'open_method',
							__( 'Open event details', 'chamber-nation-events-calendar' ),
							array(
								'modal'   => __( 'In a popup on this page', 'chamber-nation-events-calendar' ),
								'new_tab' => __( 'In a new tab', 'chamber-nation-events-calendar' ),
							)
						);
						?>
					</table>

					<h2><?php esc_html_e( 'Buttons', 'chamber-nation-events-calendar' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php
						self::checkbox_row( $s, 'allow_submit', __( 'Show "Submit an Event" button', 'chamber-nation-events-calendar' ) );
						self::text_row( $s, 'submit_label', __( 'Submit button label', 'chamber-nation-events-calendar' ) );
						self::checkbox_row( $s, 'show_registration', __( 'Show "Event Registration" button', 'chamber-nation-events-calendar' ) );
						self::text_row( $s, 'reg_label', __( 'Registration button label', 'chamber-nation-events-calendar' ) );
						?>
					</table>

					<h2><?php esc_html_e( 'SEO & structured data', 'chamber-nation-events-calendar' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php
						self::text_row( $s, 'organizer_name', __( 'Organizer name', 'chamber-nation-events-calendar' ), __( 'Used as the schema.org organizer. Defaults to the site title.', 'chamber-nation-events-calendar' ) );
						self::text_row( $s, 'organizer_url', __( 'Organizer URL', 'chamber-nation-events-calendar' ), __( 'Defaults to the site home page.', 'chamber-nation-events-calendar' ), 'url' );
						self::text_row( $s, 'default_country', __( 'Default country code', 'chamber-nation-events-calendar' ), __( 'Two-letter code used when an event address has no country, e.g. US.', 'chamber-nation-events-calendar' ) );
						?>
					</table>

					<?php submit_button(); ?>
				</form>

				<div style="flex:0 1 320px">
					<?php self::render_sidebar( $s, $api ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Sidebar: usage, connection status and cache tools.
	 *
	 * @param array    $s   Settings.
	 * @param CNEC_API $api API client.
	 */
	private static function render_sidebar( array $s, CNEC_API $api ) {
		?>
		<div class="card">
			<h2 class="title"><?php esc_html_e( 'Add the calendar', 'chamber-nation-events-calendar' ); ?></h2>
			<p><?php esc_html_e( 'Place this shortcode on any page (a Shortcode block works in the block editor):', 'chamber-nation-events-calendar' ); ?></p>
			<p><code>[<?php echo esc_html( CNEC_Plugin::SHORTCODE ); ?>]</code></p>
			<p><?php esc_html_e( 'Any setting can be overridden per page, for example:', 'chamber-nation-events-calendar' ); ?></p>
			<p><code>[<?php echo esc_html( CNEC_Plugin::SHORTCODE ); ?> category="1152" columns="2" show_all_upcoming="yes"]</code></p>
		</div>

		<div class="card">
			<h2 class="title"><?php esc_html_e( 'Connection status', 'chamber-nation-events-calendar' ); ?></h2>
			<?php
			if ( '' === $s['org_id'] ) {
				echo '<p>' . esc_html__( 'Enter your Organization ID and save to connect.', 'chamber-nation-events-calendar' ) . '</p>';
			} else {
				$now    = current_datetime();
				$events = $api->get_events( (int) $now->format( 'Y' ), (int) $now->format( 'n' ), $s['category'] );
				if ( is_wp_error( $events ) ) {
					echo '<p style="color:#b32d2e">' . esc_html( $events->get_error_message() ) . '</p>';
				} else {
					/* translators: %d: number of events */
					echo '<p style="color:#008a20">' . esc_html( sprintf( _n( 'Connected: %d event this month.', 'Connected: %d events this month.', count( $events ), 'chamber-nation-events-calendar' ), count( $events ) ) ) . '</p>';
				}
			}
			?>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="cnec_clear_cache" />
				<?php wp_nonce_field( 'cnec_clear_cache' ); ?>
				<?php submit_button( __( 'Clear event cache', 'chamber-nation-events-calendar' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Field name attribute.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private static function name( $key ) {
		return CNEC_Settings::OPTION . '[' . $key . ']';
	}

	/**
	 * Text/URL input row.
	 *
	 * @param array  $s     Settings.
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $help  Help text.
	 * @param string $type  Input type.
	 */
	private static function text_row( array $s, $key, $label, $help = '', $type = 'text' ) {
		?>
		<tr>
			<th scope="row"><label for="cnec_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="<?php echo esc_attr( $type ); ?>" class="regular-text" id="cnec_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::name( $key ) ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" />
				<?php if ( $help ) : ?>
					<p class="description"><?php echo esc_html( $help ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Number input row.
	 *
	 * @param array  $s     Settings.
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $help  Help text.
	 * @param int    $min   Min.
	 * @param int    $max   Max.
	 */
	private static function number_row( array $s, $key, $label, $help, $min, $max ) {
		?>
		<tr>
			<th scope="row"><label for="cnec_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="number" class="small-text" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" id="cnec_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::name( $key ) ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" />
				<p class="description"><?php echo esc_html( $help ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Select row.
	 *
	 * @param array  $s       Settings.
	 * @param string $key     Key.
	 * @param string $label   Label.
	 * @param array  $options Value => label.
	 */
	private static function select_row( array $s, $key, $label, array $options ) {
		?>
		<tr>
			<th scope="row"><label for="cnec_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select id="cnec_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::name( $key ) ); ?>">
					<?php foreach ( $options as $value => $text ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) $s[ $key ], (string) $value ); ?>><?php echo esc_html( $text ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Checkbox row.
	 *
	 * @param array  $s     Settings.
	 * @param string $key   Key.
	 * @param string $label Label.
	 */
	private static function checkbox_row( array $s, $key, $label ) {
		?>
		<tr>
			<th scope="row"></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( self::name( $key ) ); ?>" value="1" <?php checked( (int) $s[ $key ], 1 ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
			</td>
		</tr>
		<?php
	}

	/**
	 * Platform select.
	 *
	 * @param array $s Settings.
	 */
	private static function server_row( array $s ) {
		$options = array();
		foreach ( CNEC_Settings::servers() as $key => $server ) {
			$options[ $key ] = $server['label'];
		}
		self::select_row( $s, 'server', __( 'Platform', 'chamber-nation-events-calendar' ), $options );
	}

	/**
	 * Default category: a dropdown from the API when available.
	 *
	 * @param array    $s   Settings.
	 * @param CNEC_API $api API client.
	 */
	private static function category_row( array $s, CNEC_API $api ) {
		$categories = '' !== $s['org_id'] ? $api->get_categories() : new WP_Error( 'cnec_no_org', '' );
		?>
		<tr>
			<th scope="row"><label for="cnec_category"><?php esc_html_e( 'Category', 'chamber-nation-events-calendar' ); ?></label></th>
			<td>
				<?php if ( is_wp_error( $categories ) ) : ?>
					<input type="text" class="small-text" id="cnec_category" name="<?php echo esc_attr( self::name( 'category' ) ); ?>" value="<?php echo esc_attr( $s['category'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Numeric category ID, or blank for all events. A dropdown appears once the connection works.', 'chamber-nation-events-calendar' ); ?></p>
				<?php else : ?>
					<select id="cnec_category" name="<?php echo esc_attr( self::name( 'category' ) ); ?>">
						<option value=""><?php esc_html_e( 'All events (visitors can filter)', 'chamber-nation-events-calendar' ); ?></option>
						<?php foreach ( $categories as $id => $name ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $s['category'], (string) $id ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Choosing a category shows only that category and hides the visitor category filter.', 'chamber-nation-events-calendar' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Placeholder image picker.
	 *
	 * @param array $s Settings.
	 */
	private static function placeholder_row( array $s ) {
		?>
		<tr>
			<th scope="row"><label for="cnec_placeholder_image"><?php esc_html_e( 'Placeholder image', 'chamber-nation-events-calendar' ); ?></label></th>
			<td>
				<input type="url" class="regular-text" id="cnec_placeholder_image" name="<?php echo esc_attr( self::name( 'placeholder_image' ) ); ?>" value="<?php echo esc_attr( $s['placeholder_image'] ); ?>" />
				<button type="button" class="button" id="cnec_placeholder_pick"><?php esc_html_e( 'Choose image', 'chamber-nation-events-calendar' ); ?></button>
				<p class="description"><?php esc_html_e( 'Leave blank to use the built-in placeholder.', 'chamber-nation-events-calendar' ); ?></p>
			</td>
		</tr>
		<?php
	}
}
