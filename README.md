# Chamber Nation Events Calendar

A WordPress events calendar for chambers of commerce on **ChamberOrganizer** or **ECTownUSA**. Events are fetched on the server and output as plain HTML, with **schema.org Event** markup, so search engines and AI assistants can read every event.

## Install

1. Download **[chamber-nation-events-calendar.zip](https://github.com/iamseventhpro/chamber-nation-events-calendar/releases/latest/download/chamber-nation-events-calendar.zip)** from the [latest release](https://github.com/iamseventhpro/chamber-nation-events-calendar/releases/latest).
   Use the release zip, not GitHub's green **Code → Download ZIP** button. That button creates a folder with the wrong name, and automatic updates won't work.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, choose the zip, then click **Install Now** and **Activate**.
3. Go to **CN Events** in the dashboard and enter your chamber's **Organization ID**.
4. Add the shortcode to any page:

   ```
   [cn_events]
   ```

Requires WordPress 6.2+ and PHP 7.4+.

## Updates

The plugin checks this repository's releases about twice a day. New versions show up on the **Plugins** screen with the usual **Update now** button, and WordPress auto-updates work too.

## Features

- Month grid with event dots, day selection, and previous/next month navigation. Every view is a real, crawlable URL (`?cn_month=2026-10`, `?cn_day=2026-10-10`, `?cn_cat=1152`). No AJAX.
- A category filter for visitors, or lock the calendar to one category.
- "All upcoming events" mode. When a month is empty, the calendar finds the next upcoming event.
- Event cards in 1, 2 or 3 columns, with images and an optional short description.
- Event details open in a popup or a new tab.
- Optional **Submit an Event** and **Event Registration** buttons.
- schema.org `Event` JSON-LD for every listed event: dates with the correct timezone offset, place, address and coordinates, organizer, images, registration offer, and cancelled status.
- Its own canonical URL and title for each month, and `noindex` on single-day views. Works with Yoast SEO, Rank Math and All in One SEO.
- Cached API responses, with the last good result kept as a fallback if the API is down.
- `/llms.txt` and `/llms-full.txt` for AI assistants, listing upcoming events. Shared with the [Member Directory](https://github.com/iamseventhpro/chamber-nation-member-directory) plugin.

## Shortcode options

Any setting from the **CN Events** page can be overridden per page:

```
[cn_events category="1152" columns="2" show_all_upcoming="yes" open_method="new_tab"]
```

| Attribute | Values |
|---|---|
| `org_id` | Organization code, e.g. `HEND` |
| `server` | `org` (ChamberOrganizer) or `ectown` (ECTownUSA) |
| `cname` | Custom chamber domain for links, e.g. `https://mms.yourchamber.com` |
| `category` | Category ID. Blank shows all events with a visitor filter |
| `columns` | `1`, `2` or `3` |
| `first_day` | `monday` or `sunday` |
| `image_fit` | `contain` or `cover` |
| `show_images`, `show_placeholder`, `show_excerpt`, `show_calendar_mobile` | `yes` / `no` |
| `show_past`, `show_all_upcoming` | `yes` / `no` |
| `open_method` | `modal` or `new_tab` |
| `allow_submit`, `show_registration` | `yes` / `no` |
| `submit_label`, `reg_label` | Button text |
| `organizer_name`, `organizer_url`, `default_country` | Structured-data defaults |
| `placeholder_image` | Image URL |

## Customizing

Copy `templates/calendar.php` or `templates/event-card.php` into `your-theme/cn-events/` to change the markup. Developers can use these filters: `cnec_event`, `cnec_schema_event`, `cnec_schema_item_list`, `cnec_http_args`, `cnec_timezone_map`, `cnec_servers`, `cnec_template`, `cnec_time_format`.

## External services

Event data comes from the Chamber Nation API at `auth.chamberwidgets.com`. Event images, detail pages and registration pages load from your chamber's platform domain. Update checks go to `api.github.com`. See [readme.txt](readme.txt) for details and the [Chamber Nation privacy policy and terms](https://www.chambernation.com/website---app-privacy-policy).

## Releasing a new version (maintainers)

1. Update the version in `chamber-nation-events-calendar.php` (the `Version:` header and `CNEC_VERSION`) and in `readme.txt` (`Stable tag`, plus a changelog entry).
2. Commit, then tag and push:

   ```bash
   git tag v1.0.1 && git push origin main v1.0.1
   ```

3. The **Release** workflow checks the version numbers, lints the PHP, builds `chamber-nation-events-calendar.zip`, and publishes the GitHub release. Sites pick up the update within about 12 hours.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
