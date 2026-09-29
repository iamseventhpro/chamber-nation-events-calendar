<?php
/**
 * Single event card.
 *
 * Override by copying to {theme}/cn-events/event-card.php.
 *
 * @package ChamberNationEventsCalendar
 *
 * @var array         $event    Normalized event.
 * @var array         $settings Settings.
 * @var CNEC_Calendar $renderer Helpers.
 */

defined( 'ABSPATH' ) || exit;

if ( $event['landing'] ) {
	$cnec_href = $event['landing'];
	$cnec_open = 'tab';
} else {
	$cnec_href = $event['url'];
	$cnec_open = 'new_tab' === $settings['open_method'] ? 'tab' : 'modal';
}

$cnec_time  = $renderer->time_label( $event );
$cnec_where = $renderer->where_label( $event );
?>
<article class="cnc-item<?php echo $event['cancelled'] ? ' cnc-cancelled' : ''; ?>" id="cn-event-<?php echo esc_attr( $event['key'] ); ?>">
	<?php if ( $settings['show_images'] && $event['image'] ) : ?>
		<div class="cnc-thumb">
			<img src="<?php echo esc_url( $event['image'] ); ?>" alt="<?php echo esc_attr( $event['image_is_placeholder'] ? '' : $event['name'] ); ?>" loading="lazy" decoding="async" />
		</div>
	<?php endif; ?>

	<div class="cnc-body">
		<h3 class="cnc-title-ev notranslate" translate="no">
			<a class="cnc-ev-link" href="<?php echo esc_url( $cnec_href ); ?>" data-open="<?php echo esc_attr( $cnec_open ); ?>"<?php echo 'tab' === $cnec_open ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html( $event['name'] ); ?></a>
		</h3>

		<?php if ( $event['cancelled'] ) : ?>
			<div class="cnc-badge"><?php esc_html_e( 'Cancelled', 'chamber-nation-events-calendar' ); ?><?php echo $event['cancelled_note'] ? ' – ' . esc_html( $event['cancelled_note'] ) : ''; ?></div>
		<?php endif; ?>

		<div class="cnc-date-row">
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z"/></svg>
			<time datetime="<?php echo esc_attr( $event['all_day'] ? $event['date'] : $event['start']->format( DATE_ATOM ) ); ?>"><?php echo esc_html( $renderer->format_date( 'M j, Y', $event['date'] ) ); ?></time>
		</div>

		<?php if ( $cnec_time ) : ?>
			<div class="cnc-time-row">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20c4.41 0 8-3.59 8-8s-3.59-8-8-8-8 3.59-8 8 3.59 8 8 8zm0-14c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6 2.69-6 6-6zm-.5 3h1v5l4.25 2.52-.5.86L11.5 15V9z"/></svg>
				<span><?php echo esc_html( $cnec_time ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $cnec_where ) : ?>
			<div class="cnc-loc">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>
				<span><?php echo esc_html( $cnec_where ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $settings['show_excerpt'] && $event['text'] ) : ?>
			<p class="cnc-excerpt"><?php echo esc_html( wp_trim_words( $event['text'], 30, '…' ) ); ?></p>
		<?php endif; ?>
	</div>
</article>
