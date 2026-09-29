<?php
/**
 * schema.org JSON-LD for events.
 *
 * @package ChamberNationEventsCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds an ItemList of schema.org Event objects.
 */
class CNEC_Schema {

	/**
	 * Build the ItemList for the events shown on the page.
	 *
	 * @param array[] $events   Normalized events.
	 * @param array   $settings Settings.
	 * @param string  $page_url Canonical URL of this calendar view.
	 * @param string  $name     List name.
	 * @return array
	 */
	public static function item_list( array $events, array $settings, $page_url, $name ) {
		$items = array();
		foreach ( array_values( $events ) as $i => $event ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'item'     => self::event( $event, $settings ),
			);
		}

		$data = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'name'            => $name,
			'url'             => $page_url,
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
		);

		/**
		 * Filter the JSON-LD ItemList before output.
		 *
		 * @param array   $data   JSON-LD.
		 * @param array[] $events Normalized events.
		 */
		return apply_filters( 'cnec_schema_item_list', $data, $events );
	}

	/**
	 * Build one schema.org Event.
	 *
	 * @param array $event    Normalized event.
	 * @param array $settings Settings.
	 * @return array
	 */
	public static function event( array $event, array $settings ) {
		$url = $event['landing'] ? $event['landing'] : $event['url'];

		$data = array(
			'@type'               => 'Event',
			'@id'                 => $event['url'] . '#event-' . $event['key'],
			'name'                => $event['name'],
			'startDate'           => $event['all_day'] ? $event['date'] : $event['start']->format( DATE_ATOM ),
			'eventStatus'         => $event['cancelled'] ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
			'eventAttendanceMode' => $event['online'] ? 'https://schema.org/OnlineEventAttendanceMode' : 'https://schema.org/OfflineEventAttendanceMode',
			'url'                 => $url,
		);

		if ( $event['end'] ) {
			$data['endDate'] = $event['end']->format( DATE_ATOM );
		} elseif ( $event['all_day'] && $event['end_date'] ) {
			$data['endDate'] = $event['end_date'];
		}

		if ( $event['text'] ) {
			$data['description'] = wp_html_excerpt( $event['text'], 1000, '…' );
		}

		if ( $event['image'] && ! $event['image_is_placeholder'] ) {
			$data['image'] = array( $event['image'] );
		}

		$data['location'] = $event['online']
			? array(
				'@type' => 'VirtualLocation',
				'url'   => $url,
			)
			: self::place( $event['location'] );
		if ( ! $data['location'] ) {
			unset( $data['location'] );
		}

		$data['organizer'] = array(
			'@type' => 'Organization',
			'name'  => $settings['organizer_name'] ? $settings['organizer_name'] : get_bloginfo( 'name' ),
			'url'   => $settings['organizer_url'] ? $settings['organizer_url'] : home_url( '/' ),
		);

		if ( $event['reg_url'] ) {
			$data['offers'] = array(
				'@type'        => 'Offer',
				'url'          => $event['reg_url'],
				'availability' => $event['cancelled'] ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock',
			);
		}

		if ( $event['id'] ) {
			$data['identifier'] = $event['id'];
		}

		/**
		 * Filter a single schema.org Event.
		 *
		 * @param array $data  JSON-LD Event.
		 * @param array $event Normalized event.
		 */
		return apply_filters( 'cnec_schema_event', $data, $event );
	}

	/**
	 * schema.org Place for a physical location.
	 *
	 * @param array $loc Normalized location.
	 * @return array|null
	 */
	private static function place( array $loc ) {
		$address = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $loc['street'],
				'addressLocality' => $loc['city'],
				'addressRegion'   => $loc['region'],
				'postalCode'      => $loc['postal'],
				'addressCountry'  => $loc['country'],
			)
		);

		if ( ! $loc['name'] && count( $address ) < 2 ) {
			return null;
		}

		$place = array(
			'@type' => 'Place',
			'name'  => $loc['name'] ? $loc['name'] : $loc['street'],
		);
		if ( count( $address ) > 1 ) {
			$place['address'] = $address;
		}
		if ( null !== $loc['lat'] && null !== $loc['lng'] ) {
			$place['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => $loc['lat'],
				'longitude' => $loc['lng'],
			);
		}
		return $place;
	}

	/**
	 * JSON-LD <script> tag, safe to print inline.
	 *
	 * @param array $data JSON-LD.
	 * @return string
	 */
	public static function script_tag( array $data ) {
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		return $json ? '<script type="application/ld+json">' . $json . "</script>\n" : '';
	}
}
