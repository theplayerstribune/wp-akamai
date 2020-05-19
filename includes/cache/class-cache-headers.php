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
        add_action( 'wp', [ $this, 'emit_cache_tags' ], 102 );
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
     * @global \WP_Query $wp_query The main query object for the page.
     */
    public function emit_cache_control() {
        global $wp_query;

        /**
         * If a user is logged in, cache control headers should be
         * ignored. We do not want to cache any logged in
         * user views. WordPress sets a "Cache-Control:no-cache,
         * must-revalidate, max-age=0" header for logged in views and
         * should be sufficient for keeping logged in views uncached.
         */
        if ( is_user_logged_in() ) {
            return;
        }

        /**
         * Filter: akamai_do_emit_cache_control
         *
         * @since 0.7.0
         *
         * @param bool          $do_emit Whether to emit the header.
         * @param \WP_Query     $wp_query The main query object.
         * @param Cache_Headers $cache This instance. Good helpers!
         */
        $do_emit = apply_filters(
            'akamai_do_emit_cache_control',
            $this->plugin->setting( 'emit-cache-control' ),
            $wp_query,
            $this::$instance
        );
        if ( ! $do_emit ) {
            return;
        }

        /**
         * Filter: akamai_cache_control_header
         *
         * @since 0.7.0
         *
         * @param string        $header The Cache-Control header value.
         * @param \WP_Query     $wp_query The main query object.
         * @param Cache_Headers $cache This instance. Good helpers!
         */
        $cache_control = apply_filters(
            'akamai_cache_control_header',
            $this->plugin->setting( 'cache-default-header' ),
            $wp_query,
            $this::$instance
        );

        $this->emit_header( 'Cache-Control', $cache_control );
    }

    /**
     * Emits (sends) an Edge-Cache-Tag header. This header will overwrite
     * any previously set one (thus should hook into a large (low)
     * priority for the 'wp' action).
     *
     * This is a complex action that uses the current query's info to
     * determine the page type and template, and then adds all other
     * tags that it should to a list, which can then be filtered:
     * akamai_cache_tags_header.
     *
     * It is run whenever the user is not logged in to admin, and
     * according to the outcome of the filter
     * 'akamai_do_emit_cache_tags' (defaulting to the value in the
     * settings).
     *
     * @since 0.7.0
     * @global \WP_Query $wp_query The main query object for the page.
     */
    public function emit_cache_tags() {
        global $wp_query;

        /**
         * If a user is logged in, cache control headers should be
         * ignored. We do not want to cache any logged in
         * user views. WordPress sets a "Cache-Control:no-cache,
         * must-revalidate, max-age=0" header for logged in views and
         * should be sufficient for keeping logged in views uncached.
         */
        if ( is_user_logged_in() ) {
            return;
        }

        /**
         * Filter: akamai_do_emit_cache_tags
         *
         * @since 0.7.0
         *
         * @param bool          $do_emit Whether to emit the header.
         * @param \WP_Query     $wp_query The main query object.
         * @param Cache_Headers $cache This instance. Good helpers!
         */
        $do_emit = apply_filters(
            'akamai_do_emit_cache_tags',
            $this->plugin->setting( 'emit-cache-tags' ),
            $wp_query,
            $this::$instance
        );
        if ( ! $do_emit ) {
            return;
        }

        /**
         * Filter: akamai_cache_include_related_tags
         *
         * @since 0.7.0
         *
         * @param bool          $do_emit Whether to include related
         *                      objects tags.
         * @param \WP_Query     $wp_query The main query object.
         * @param Cache_Headers $cache This instance. Good helpers!
         */
        $include_related = apply_filters(
            'akamai_cache_include_related_tags',
            $this->plugin->setting( 'cache-related-tags' ),
            $wp_query,
            $this::$instance
        );

        // TODO: build out logic for determining page type and template.
        // TODO: turn this into the a list of tags.
        // TODO: hook into all necessary to build a tag list, then merge
        //       and filter below.

        $cache_tags = [
            'tpt-all',
            'tpt-tm-feed'
        ];
        // $cache_tags = Cache_Tags::instance()->tags_from_query(
        //     $wp_query,
        //     $include_related
        // );

        $this->emit_header(
            'Edge-Cache-Tag',
            $cache_tags,
            $replace = true,
            $delim = ','
        );
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

        /**
         * Action: akamai_pre_emit_{HEADER_NAME}
         * Action: akamai_post_emit_{HEADER_NAME}
         *
         * Eg:
         *    - akamai_pre_emit_cache_control
         *    - akamai_pre_emit_edge_cache_tag
         *
         * @since 0.7.0
         *
         * @param string $value The value of the header.
         * @param bool   $replace Whether this header will replace (or
         *               be in a addition to) any headers already set
         *               with the same name.
         */
        do_action( "akamai_pre_emit_{$snake_name}", $value, $replace );
        header( "{$name}: {$value}", $replace );
        do_action( "akamai_post_emit_{$snake_name}", $value, $replace );
    }
}
