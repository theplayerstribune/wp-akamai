<?php

/**
 * The plugin bootstrap file.
 *
 * This file is read by WordPress to generate the plugin information in the
 * plugin admin area. This file also includes all of the dependencies used by
 * the plugin, registers the activation and deactivation functions, and defines
 * a function that starts the plugin.
 *
 * @link              https://developer.akamai.com
 * @since             0.2.0
 * @package           Akamai\WordPress
 * @author            Davey Shafik <dshafik@akamai.com>
 *
 * @wordpress-plugin
 * Plugin Name:       Akamai for WordPress
 * Plugin URI:        http://github.com/akamai/wp-akamai
 * Description:       Akamai for WordPress Plugin. Control Akamai CDN and more.
 * Version:           0.7.0
 * Author:            Akamai Technologies
 * Author URI:        https://developer.akamai.com
 * License:           Apache-2.0
 * License URI:       http://www.apache.org/licenses/LICENSE-2.0.txt
 * Text Domain:       akamai
 */

use \Akamai\WordPress\{Activator, Deactivator, Plugin};

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

if ( ! defined( 'AKAMAI_PLUGIN_PATH' ) ) {
    define( 'AKAMAI_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
    define( 'AKAMAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
    define( 'AKAMAI_MIN_PHP', '5.3' );
}

if ( version_compare( phpversion(), AKAMAI_MIN_PHP, '<' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error">' .
            __(
                 'Error: "Akamai for WordPress" ' .
                 'requires a newer version of PHP to be running.',
                 'akamai'
            ) .
            '<br/>' . __(
                'Minimal version of PHP required: ',
                'akamai'
            ) . '<strong>' . AKAMAI_MIN_PHP . '</strong>' .
            '<br/>' . __( 'Your server\'s PHP version: ', 'akamai' ) .
            '<strong>' . phpversion() . '</strong>' .
            '</div>';
    } );

    return false;
}

require_once 'vendor/akamai-open/edgegrid-auth/src/Authentication.php';
require_once 'vendor/akamai-open/edgegrid-auth/src/Authentication/Timestamp.php';
require_once 'vendor/akamai-open/edgegrid-auth/src/Authentication/Nonce.php';
require_once 'vendor/akamai-open/edgegrid-auth/src/Authentication/Exception.php';
require_once 'vendor/akamai-open/edgegrid-auth/src/Authentication/Exception/ConfigException.php';
require_once 'vendor/akamai-open/edgegrid-auth/src/Authentication/Exception/SignerException.php';
require_once 'vendor/akamai-open/edgegrid-auth/src/Authentication/Exception/SignerException/InvalidSignDataException.php';

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-akamai-activator.php
 */
function activate_akamai() {
    require_once AKAMAI_PLUGIN_PATH . 'includes/class-activator.php';
    Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-akamai-deactivator.php
 */
function deactivate_akamai() {
    require_once AKAMAI_PLUGIN_PATH . 'includes/class-deactivator.php';
    Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_akamai');
register_deactivation_hook( __FILE__, 'deactivate_akamai');

/**
 * The core plugin classes that are used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require AKAMAI_PLUGIN_PATH . 'includes/class-plugin.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    0.1.0
 */
function run_akamai() {
    $plugin = new Plugin();
    $plugin->run();
}

run_akamai();
