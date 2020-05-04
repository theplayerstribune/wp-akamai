<?php

namespace Akamai\WordPress;

/**
 * Akamai_Activator is fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since   0.1.0
 * @package Akamai\WordPress
 * @author  Davey Shafik <dshafik@akamai.com>
 */
class Akamai_Activator {

    /**
     * Runs on plugin activation.
     *
     * @since    0.1.0
     */
    public static function activate() {
        add_option( 'akamai-version', Akamai::$version );
    }

}
