<?php
/**
 * Calendar layout (server-rendered port of the Events Compact widget).
 *
 * Override by copying to {theme}/cn-events/calendar.php.
 *
 * @package ChamberNationEventsCalendar
 *
 * @var string        $uid            Instance HTML id.
 * @var array         $settings       Settings.
 * @var array         $state          Request state.
 * @var string        $month_title    e.g. "September 2026".
 * @var array         $prev           array( url, label ).
 * @var array         $next           array( url, label ).
 * @var array[]       $weekdays       Weekday labels.
 * @var array[]       $cells          42 grid cells.
 * @var array|WP_Error|null $categories ID => name, error, or null when locked.
 * @var string        $form_action    Category form action.
 * @var string        $form_month     Month to keep when filtering.
 * @var string        $submit_url     Submit-event URL or ''.
 * @var string        $reg_url        Registration URL or ''.
 * @var string        $list_label     List heading.
 * @var string        $show_month_url "Show month" link in day view.
 * @var array[]       $events         Events to list.
 * @var string        $empty_message  Message when there are no events.
 * @var array|null    $next_event     Next-event info for empty months.
 * @var bool          $no_upcoming    Empty month and nothing in the next 12 months.
 * @var CNEC_Calendar $renderer       Helpers.
 */

defined( 'ABSPATH' ) || exit;

$cnec_modal = 'modal' === $settings['open_method'];
?>
<div id="<?php echo esc_attr( $uid ); ?>" class="cnc-root" data-cols="<?php echo esc_attr( $settings['columns'] ); ?>" data-mobile-calendar="<?php echo $settings['show_calendar_mobile'] ? 'yes' : 'no'; ?>">

	<nav class="cnc-nav" aria-label="<?php esc_attr_e( 'Month navigation', 'chamber-nation-events-calendar' ); ?>">
		<a class="cnc-btn cnc-prev" href="<?php echo esc_url( $prev['url'] ); ?>" aria-label="<?php esc_attr_e( 'Previous month', 'chamber-nation-events-calendar' ); ?>">
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6z"/></svg>
			<span class="cnc-nav-label"><?php echo esc_html( $prev['label'] ); ?></span>
		</a>
		<h2 class="cnc-title"><?php echo esc_html( $month_title ); ?></h2>
		<a class="cnc-btn cnc-next" href="<?php echo esc_url( $next['url'] ); ?>" aria-label="<?php esc_attr_e( 'Next month', 'chamber-nation-events-calendar' ); ?>">
			<span class="cnc-nav-label"><?php echo esc_html( $next['label'] ); ?></span>
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg>
		</a>
	</nav>

	<div class="cnc-split">
		<div class="cnc-left">
			<div class="cnc-cal">
				<div class="cnc-cal-head"><div><?php esc_html_e( 'Month', 'chamber-nation-events-calendar' ); ?></div><div><?php esc_html_e( 'Days', 'chamber-nation-events-calendar' ); ?></div></div>
				<div class="cnc-weekday" aria-hidden="true">
					<?php foreach ( $weekdays as $cnec_wd ) : ?>
						<div title="<?php echo esc_attr( $cnec_wd['full'] ); ?>"><?php echo esc_html( $cnec_wd['short'] ); ?></div>
					<?php endforeach; ?>
				</div>
				<div class="cnc-grid">
					<?php
					foreach ( $cells as $cnec_cell ) :
						$cnec_class = 'cnc-cell' . ( $cnec_cell['selected'] ? ' cnc-selected' : '' ) . ( $cnec_cell['today'] ? ' cnc-today' : '' );
						$cnec_inner = '<span class="cnc-date' . ( $cnec_cell['out'] ? ' cnc-out' : '' ) . '">' . esc_html( $cnec_cell['number'] ) . '</span>'
							. ( $cnec_cell['count'] ? '<span class="cnc-dot" aria-hidden="true"></span>' : '' );
						if ( $cnec_cell['url'] ) :
							/* translators: 1: date, 2: number of events */
							$cnec_aria = sprintf( _n( '%1$s, %2$d event', '%1$s, %2$d events', $cnec_cell['count'], 'chamber-nation-events-calendar' ), $cnec_cell['label'], $cnec_cell['count'] );
							?>
							<a class="<?php echo esc_attr( $cnec_class ); ?>" href="<?php echo esc_url( $cnec_cell['url'] ); ?>" rel="nofollow" aria-label="<?php echo esc_attr( $cnec_aria ); ?>"<?php echo $cnec_cell['selected'] ? ' aria-current="date"' : ''; ?>><?php echo $cnec_inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above. ?></a>
						<?php else : ?>
							<div class="<?php echo esc_attr( $cnec_class ); ?>"><?php echo $cnec_inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above. ?></div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>

			<?php if ( null !== $categories ) : ?>
				<div class="cnc-cats">
					<form method="get" action="<?php echo esc_url( $form_action . '#' . $uid ); ?>">
						<?php if ( $form_month ) : ?>
							<input type="hidden" name="<?php echo esc_attr( CNEC_Calendar::QV_MONTH ); ?>" value="<?php echo esc_attr( $form_month ); ?>" />
						<?php endif; ?>
						<label class="cnc-sr" for="<?php echo esc_attr( $uid ); ?>-cat"><?php esc_html_e( 'Filter by category', 'chamber-nation-events-calendar' ); ?></label>
						<select class="cnc-cat-dropdown" id="<?php echo esc_attr( $uid ); ?>-cat" name="<?php echo esc_attr( CNEC_Calendar::QV_CAT ); ?>">
							<?php if ( is_wp_error( $categories ) ) : ?>
								<option value=""><?php esc_html_e( 'Categories unavailable', 'chamber-nation-events-calendar' ); ?></option>
							<?php else : ?>
								<option value=""><?php esc_html_e( 'All Events', 'chamber-nation-events-calendar' ); ?></option>
								<?php foreach ( $categories as $cnec_id => $cnec_name ) : ?>
									<option value="<?php echo esc_attr( $cnec_id ); ?>" <?php selected( $state['category'], (string) $cnec_id ); ?>><?php echo esc_html( $cnec_name ); ?></option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
						<noscript><button type="submit" class="cnc-btn cnc-cat-go"><?php esc_html_e( 'Filter', 'chamber-nation-events-calendar' ); ?></button></noscript>
					</form>
				</div>
			<?php endif; ?>

			<?php if ( $submit_url ) : ?>
				<div class="cnc-submit-wrap">
					<a href="<?php echo esc_url( $submit_url ); ?>" target="_blank" rel="noopener" class="cnc-submit-btn"><?php echo esc_html( $settings['submit_label'] ); ?></a>
				</div>
			<?php endif; ?>

			<?php if ( $reg_url ) : ?>
				<div class="cnc-reg-wrap">
					<a href="<?php echo esc_url( $reg_url ); ?>" target="_blank" rel="noopener" class="cnc-reg-btn"><?php echo esc_html( $settings['reg_label'] ); ?></a>
				</div>
			<?php endif; ?>
		</div>

		<section class="cnc-main" aria-labelledby="<?php echo esc_attr( $uid ); ?>-list-label">
			<div class="cnc-day-head">
				<h2 class="label" id="<?php echo esc_attr( $uid ); ?>-list-label">
					<?php
					/* translators: %s: date, month or "All Upcoming Events" */
					echo esc_html( sprintf( __( 'Events for %s', 'chamber-nation-events-calendar' ), $list_label ) );
					?>
				</h2>
				<div class="cnc-day-tools">
					<?php if ( $show_month_url ) : ?>
						<a class="cnc-btn" href="<?php echo esc_url( $show_month_url ); ?>"><?php esc_html_e( 'Show month', 'chamber-nation-events-calendar' ); ?></a>
					<?php endif; ?>
					<div class="chip"><span class="count"><?php echo esc_html( number_format_i18n( count( $events ) ) ); ?></span><span class="cnc-sr"> <?php esc_html_e( 'events', 'chamber-nation-events-calendar' ); ?></span></div>
				</div>
			</div>

			<div class="cnc-list cnc-cols-<?php echo esc_attr( $settings['columns'] ); ?>" data-image-fit="<?php echo esc_attr( $settings['image_fit'] ); ?>">
				<?php if ( $events ) : ?>
					<?php
					foreach ( $events as $cnec_event ) {
						$cnec_card = $renderer->load_template(
							'event-card',
							array(
								'event'    => $cnec_event,
								'settings' => $settings,
								'renderer' => $renderer,
							)
						);
						echo $cnec_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The card template escapes its own output.
					}
					?>
				<?php elseif ( 'month' === $state['view'] && ! empty( $next_event ) ) : ?>
					<div class="cn-no-events-card">
						<div class="cn-no-events-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="48" height="48"><path fill="currentColor" d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z"/></svg></div>
						<h3><?php esc_html_e( 'No Upcoming Events This Month', 'chamber-nation-events-calendar' ); ?></h3>
						<p>
							<?php
							/* translators: %s: date of the next event */
							printf( esc_html__( 'The next upcoming event is on %s', 'chamber-nation-events-calendar' ), '<strong>' . esc_html( $next_event['date_label'] ) . '</strong>' );
							?>
						</p>
						<a class="cn-jump-btn" href="<?php echo esc_url( $next_event['url'] ); ?>">
							<?php
							/* translators: %s: month and year */
							echo esc_html( sprintf( __( 'View %s', 'chamber-nation-events-calendar' ), $next_event['month_label'] ) );
							?>
						</a>
					</div>
				<?php elseif ( $no_upcoming ) : ?>
					<div class="cn-no-events-card">
						<div class="cn-no-events-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="48" height="48"><path fill="currentColor" d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z"/></svg></div>
						<h3><?php esc_html_e( 'No Upcoming Events', 'chamber-nation-events-calendar' ); ?></h3>
						<p><?php esc_html_e( 'There are currently no scheduled events in the next 12 months.', 'chamber-nation-events-calendar' ); ?></p>
					</div>
				<?php else : ?>
					<div class="cn-empty"><?php echo esc_html( $empty_message ); ?></div>
				<?php endif; ?>
			</div>
		</section>
	</div>

	<?php if ( $cnec_modal ) : ?>
		<div class="cn-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $uid ); ?>-modal-title">
			<div class="cn-modal__box" role="document">
				<div class="cn-modal__head">
					<div class="cn-modal__title notranslate" id="<?php echo esc_attr( $uid ); ?>-modal-title" translate="no"></div>
					<button type="button" class="cn-modal__close" aria-label="<?php esc_attr_e( 'Close', 'chamber-nation-events-calendar' ); ?>">✕</button>
				</div>
				<div class="cn-modal__body"></div>
			</div>
		</div>
	<?php endif; ?>
</div>
