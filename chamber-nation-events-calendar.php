<?php
/**
 * Plugin Name:       Chamber Nation Events Calendar
 * Plugin URI:        https://github.com/iamseventhpro/chamber-nation-events-calendar
 * Description:       Server-rendered events calendar for ChamberOrganizer / ECTownUSA chambers, with schema.org Event markup for search engines and AI crawlers.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Chamber Nation
 * Author URI:        https://chambernation.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       chamber-nation-events-calendar
 * Update URI:        https://github.com/iamseventhpro/chamber-nation-events-calendar
 *
 * @package ChamberNationEventsCalendar
 */

defined( 'ABSPATH' ) || exit;

define( 'CNEC_VERSION', '1.0.0' );
define( 'CNEC_FILE', __FILE__ );
define( 'CNEC_DIR', plugin_dir_path( __FILE__ ) );
define( 'CNEC_URL', plugin_dir_url( __FILE__ ) );

require_once CNEC_DIR . 'includes/class-cnec-settings.php';
require_once CNEC_DIR . 'includes/class-cnec-api.php';
require_once CNEC_DIR . 'includes/class-cnec-schema.php';
require_once CNEC_DIR . 'includes/class-cnec-calendar.php';
require_once CNEC_DIR . 'includes/class-cnec-seo.php';
require_once CNEC_DIR . 'includes/class-cnec-admin.php';
require_once CNEC_DIR . 'includes/class-cnec-updater.php';
require_once CNEC_DIR . 'includes/class-cnec-plugin.php';

CNEC_Plugin::init();
