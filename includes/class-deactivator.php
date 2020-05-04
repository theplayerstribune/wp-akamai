<?php

namespace Akamai\WordPress;

use \Akamai\WordPress\Admin\Admin;

/**
 * Deactivator is fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since   0.1.0
 * @package Akamai\WordPress
 * @author  Davey Shafik <dshafik@akamai.com>
 */
class Deactivator {

    /**
     * Runs on plugin deactivation. Deletes extant options.
     *
     * @since    0.1.0
     */
    public static function deactivate() {
        delete_option( 'akamai-version' );
        delete_option( Admin::instance()->name() );
    }

}
