<?php

namespace Akamai\WordPress\Cache;

/**
 * Cache_Headers is a singleton for managing cache header behavior.
 *
 * Generates the default values for the Cache-Control and Edge-Cache-Tag
 * headers, but is easily extensible to include more. Also manages some
 * logic around determining the current request's page type and template.
 *
 * @since   0.7.0
 * @package Akamai\WordPress\Cache
 */
class Cache_Headers {

    /**
     * The one instance of Cache_Tags.
     *
     * @since 0.7.0
     * @var   Cache_Tags
     */
    private static $instance;

    /**
     * Instantiate or return the one Cache_Tags instance.
     *
     * @since  0.7.0
     * @param  string     $plugin The Plugin class instance.
     * @return Cache_Tags The created instance.
     */
    public static function instance( $plugin ) {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self( $plugin );
        }
        return self::$instance;
    }

    /**
     * A reference to the Akamai Plugin class instance.
     *
     * @since  0.7.0
     * @access protected
     * @var    string $plugin The Akamai Plugin class instance.
     */
    protected $plugin;

    /**
     * Instantiates an instance.
     *
     * @since  0.7.0
     * @access protected
     * @param  string $plugin The Akamai class instance.
     */
    protected function __construct( $plugin ) {
        $this->plugin = $plugin;

        // TODO: send these back to the plugin loader.
        add_action( 'wp', [ $this, 'emit_cache_control' ], 102 );
    }

    /**
     * Emits (sends) a Cache-Control header. This header will overwrite
     * any previously set one (thus should hook into a large (low)
     * priority for the 'wp' action), and defaults to the values set on
     * the setting page. However, this can be changed in the filter
     * 'akamai_cache_control_header'.
     *
     * It is run whenever the user is not logged in to admin, and
     * according to the outcome of the filter
     * 'akamai_do_emit_cache_control' (defaulting to the value in the
     * settings).
     *
     * @since 0.7.0
     */
    public function emit_cache_control() {
        /**
         * If a user is logged in, surrogate control headers should be ignored. We do not want to cache any logged in
         * user views. WordPress sets a "Cache-Control:no-cache, must-revalidate, max-age=0" header for logged in views
         * and this should be sufficient for keeping logged in views uncached.
         */
        if ( is_user_logged_in() ) {
            return;
        }
        $do_emit = apply_filters(
            'akamai_do_emit_cache_control',
            $this->plugin->setting( 'emit-cache-control' )
        );
        if ( ! $do_emit ) {
            return;
        }

        $cache_control = apply_filters(
            'akamai_cache_control_header',
            $this->plugin->setting( 'cache-default-header' )
        );

        $this->emit_header( 'Cache-Control', $cache_control );
    }

    /**
     * A helper function to automate the process of setting headers.
     * Allows us to semantically define them, format them, and run pre-
     * and post- hooks (action filters).
     *
     * @since  0.7.0
     * @param  string $name The header's name.
     * @param  string $value The header's value.
     * @param  bool   $replace Optional. Whether to overwrite an
     *                existing header. Defaults to true.
     * @param  string $delim Optional. The delimiter to use when passing
     *                an array of value (will always be followed by a
     *                space as well). Defaults to ';'.
     */
    public function emit_header( $name, $value, $replace = true, $delim = ';' ) {
        if ( is_array( $value ) ) {
            $value = join( "{$delim} ", $value );
        }
        $snake_name = str_replace( '-', '_', mb_strtolower( $name, 'UTF-8' ) );

        do_action( "akamai_pre_emit_{$snake_name}", $value, $replace );
        header( "{$name}: {$value}", $replace );
        do_action( "akamai_post_emit_{$snake_name}", $value, $replace );
    }
}