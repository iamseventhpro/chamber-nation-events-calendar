=== Chamber Nation Events Calendar ===
Contributors: chambernation
Tags: events, calendar, chamber of commerce, schema, seo
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An SEO-friendly events calendar for chambers of commerce on ChamberOrganizer or ECTownUSA, with schema.org Event markup.

== Description ==

Chamber Nation Events Calendar shows your chamber's events from ChamberOrganizer or ECTownUSA on your WordPress site.

Most embeddable calendars load events in the visitor's browser, which search engines and AI assistants often can't see. This plugin fetches events on your server and outputs them as plain HTML, so the event names, dates, times and locations are in the page source.

**Features**

* Month grid with event dots, day selection and previous/next month navigation. Every view is a real, crawlable link.
* Visitor category filter, or lock the calendar to one category.
* "All upcoming events" mode.
* When a month has no events, the calendar finds the next upcoming one.
* 1, 2 or 3 column event cards, with images and an optional short description.
* Event details open in a popup or a new tab.
* Optional "Submit an Event" and "Event Registration" buttons.
* schema.org `Event` JSON-LD (inside an `ItemList`) for every listed event: dates with the correct timezone offset, location and geo coordinates, organizer, images and registration offers.
* Separate canonical URLs and titles for each month, and `noindex` for single-day views. Works alongside Yoast SEO, Rank Math and All in One SEO.
* API responses are cached, and the last good result is kept as a fallback if the API is unavailable.
* Themes can override the templates.
* `/llms.txt` and `/llms-full.txt` list upcoming events for AI assistants.

**Installation**

1. Download `chamber-nation-events-calendar.zip` from the latest release: https://github.com/iamseventhpro/chamber-nation-events-calendar/releases/latest
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, choose the zip, then **Install Now** and **Activate**.
3. Later updates appear on the Plugins screen like any other plugin.

**Usage**

1. Go to **CN Events** in the dashboard and enter your Organization ID.
2. Add the `[cn_events]` shortcode to any page.

You can override any setting for a single page with shortcode attributes, for example:

`[cn_events category="1152" columns="2" show_all_upcoming="yes" open_method="new_tab"]`

Available attributes: `org_id`, `server`, `cname`, `category`, `columns`, `first_day`, `image_fit`, `show_images`, `show_placeholder`, `placeholder_image`, `show_calendar_mobile`, `show_excerpt`, `show_past`, `show_all_upcoming`, `open_method`, `allow_submit`, `submit_label`, `show_registration`, `reg_label`, `organizer_name`, `organizer_url`, `default_country`.

**Template overrides**

Copy `templates/calendar.php` or `templates/event-card.php` into `your-theme/cn-events/` to customize the markup.

== External services ==

This plugin gets event data from the Chamber Widgets API, which serves event information published by your chamber on ChamberOrganizer or ECTownUSA. It can't display events without this service.

* **What is sent:** your configured Organization ID, the year and month being viewed, and the selected category ID. The request comes from your web server, not the visitor's browser. It includes your site's home URL as the HTTP Referer. No visitor personal data is sent.
* **When:** when a calendar page is viewed and the cached result has expired (every 30 minutes by default), and when the CN Events settings page is opened.
* **Endpoints:** `https://auth.chamberwidgets.com/cn-api/{platform}/calendar/events` and `https://auth.chamberwidgets.com/cn-api/{platform}/calendar/categories`
* Service provider: Chamber Nation. Privacy policy and terms and conditions: https://www.chambernation.com/website---app-privacy-policy

**Update checks (GitHub):** in the WordPress dashboard, the plugin asks the GitHub API (`https://api.github.com/repos/iamseventhpro/chamber-nation-events-calendar/releases/latest`) for the latest release, about twice a day, so it can offer updates. No site or user data is sent beyond a standard HTTP request. GitHub terms: https://docs.github.com/site-policy/github-terms/github-terms-of-service. Privacy: https://docs.github.com/site-policy/privacy-policies/github-general-privacy-statement

In addition, the visitor's browser loads the following directly from your chamber's platform domain (chamberorganizer.com, ectownusa.net, or your custom chamber domain):

* Event images and sponsor logos.
* The event details page, shown in a popup iframe or new tab when a visitor opens an event.
* The "Submit an Event" and "Event Registration" pages, when those buttons are enabled and clicked.

ChamberOrganizer and ECTownUSA are Chamber Nation services, covered by the same privacy policy and terms and conditions: https://www.chambernation.com/website---app-privacy-policy

== Frequently Asked Questions ==

= Do I need an account? =

Yes. You need a ChamberOrganizer or ECTownUSA chamber account and its Organization ID.

= Why are events not updating immediately? =

API results are cached. Use **CN Events → Clear event cache**, or lower the cache duration.

= Does it work with page builders? =

Yes. Any builder that can render a shortcode will work.

== Screenshots ==

1. Month view with the event list.
2. CN Events settings page.

== Changelog ==

= 1.1.0 =
* New: /llms.txt and /llms-full.txt list upcoming events for AI assistants (shared with the Chamber Nation Member Directory plugin).
* Rewrite rules refresh automatically after updates.

= 1.0.0 =
* Initial release.
