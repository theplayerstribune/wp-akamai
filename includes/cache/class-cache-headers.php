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
     * General template types.
     *
     * @since 0.7.0
     * @var   array
     */
    static public $template_types = [
        'single',
        'preview',
        'front_page',
        'page',
        'archive',
        'date',
        'year',
        'month',
        'day',
        'time',
        'author',
        'category',
        'tag',
        'tax',
        'search',
        'feed',
        'comment_feed',
        'trackback',
        'home',
        '404',
        'paged',
        'admin',
        'attachment',
        'singular',
        'robots',
        'posts_page',
        'post_type_archive',
    ];

    /**
     * A reference to the Akamai Plugin class instance.
     *
     * @since  0.7.0
     * @access protected
     * @var    string $plugin The Akamai Plugin class instance.
     */
    protected $plugin;

    /**
     * A reference to the cache tags class instance.
     *
     * @since  0.7.0
     * @var    Cache_Tags $tagger The cache tags class instance.
     */
    public $tagger;

    /**
     * Instantiates an instance.
     *
     * @since  0.7.0
     * @access protected
     * @param  string $plugin The Akamai class instance.
     */
    protected function __construct( $plugin ) {
        $this->plugin = $plugin;
        $this->tagger = Cache_Tags::instance( $plugin );

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

        // Identify the current template type(s).
        $template_types = $this->template_types( $wp_query );

        // Gather tags, then merge, de-dupe, and prune.
        $universal_tags = $this->tagger->get_tags_for_emit_universal();
        $template_tags = array_map(
            [ $this->tagger, 'get_template_tag' ],
            $template_types
        );
        $object_tags = [];
        if (
            in_array( 'post', $template_types ) ||
            in_array( 'page', $template_types ) ||
            in_array( 'attachment', $template_types ) ) {
            $object_tags = $this->tagger->get_tags_for_emit_post(
                $wp_query->post, $include_related );
        } else {
            // Default is to get tags for all posts on page.
            // NOTE: not getting related for a list of posts...Good?
            foreach ( $wp_query->posts as $post ) {
                $object_tags = array_merge(
                    $object_tags,
                    $this->tagger->get_tags_for_emit_post( $post, false )
                );
            }
        }
        if ( in_array( 'term', $template_types ) ) {
            $term = $wp_query->get_queried_object();
            $object_tags = $this->tagger->get_tags_for_emit_term(
                $term, $term->taxonomy, $include_related );
        }

        $cache_tags = array_merge(
            $universal_tags,
            $template_tags,
            $object_tags
        );
        $cache_tags = array_unique( $cache_tags );
        $cache_tags = array_filter( $cache_tags );

        /**
         * Filter: akamai_emit_{$TEMPLATE}_cache_tags
         *
         * Before running the catch-all cache tags filter, run one
         * specific to the current template types!
         *
         * Eg:
         *     - akamai_emit_post_cache_tags
         *     - akamai_emit_video_cache_tags (CPT)
         *     - akamai_emit_feed_cache_tags
         *     - akamai_emit_category_cache_tags
         *     - akamai_emit_home_cache_tags
         *
         * @since 0.7.0
         *
         * @param array         $cache_tags The list of cache tags to emit.
         * @param \WP_Query     $wp_query The main query object.
         * @param Cache_Headers $cache This instance. Good helpers!
         */
        foreach ( $template_types as $template ) {
            $cache_tags = apply_filters(
                "akamai_emit_{$template}_cache_tags",
                $cache_tags,
                $wp_query,
                $this::$instance
            );
        }

        // TODO: if one of the tags is an always purge tag, remove all
        //       the other tags. Possibly implement as a 10 priority
        //       filter below that can be removed by referencing the
        //       singleton instance callable.

        /**
         * Filter: akamai_emit_cache_tags
         *
         * @since 0.7.0
         *
         * @param array         $cache_tags The final list of tags to emit.
         * @param \WP_Query     $wp_query The main query object.
         * @param Cache_Headers $cache This instance. Good helpers!
         */
        $cache_tags = apply_filters(
            'akamai_emit_cache_tags',
            $cache_tags,
            $wp_query,
            $this::$instance
        );

        $this->emit_header(
            'Edge-Cache-Tag',
            $cache_tags,
            $replace = true,
            $delim = ','
        );
    }

    /**
     * Determine the type of WordPress template being displayed.
     *
     * @since  0.7.0
     * @param  \WP_Query $wp_query The query object to inspect.
     * @return array     The template name(s). If can not be determined,
     *                   is [ 'other' ]. In the case of single CPT or
     *                   custom term pages, is 'post' and CPT / 'term'
     *                   custom term; by default [ 'post', 'post' ] and
     *                   [ 'term', 'tag'/'category ].
     */
    public function template_types( $wp_query ) {

        /**
         * This function has the potential to be called in the admin
         * context. Unfortunately, in the admin context, $wp_query isn't
         * a \WP_Query object. Bad things happen when call_user_func is
         * applied below. As such, lets' be cautious and make sure that
         * the $wp_query object is indeed a \WP_Query object.
         */
        if ( ! is_a( $wp_query, 'WP_Query' ) ) {
            return [ 'admin' ];
        }

        // Duck-type using an "is_TYPE" call: if it is a callable
        // function, call and see if it returns true.
        $template_type = 'other';
        foreach ( $this::$template_types as $type ) {
            $cb = [ $wp_query, "is_{$type}" ];
            if ( method_exists( ...$cb ) && is_callable( $cb ) ) {
                if ( true === call_user_func( $cb ) ) {
                    $template_type = $type;
                    break;
                }
            }
        }

        // Dasherize!
        $template_type = str_replace( '_', '-',  $template_type );

        // More info:
        //   - break out single/singular into the actual post type.
        //   - break out tag/category/tax into the actual term type.
        //   - could do more!
        $template_types = [];
        switch ( $template_type ) {
            case 'single':
            case 'singular':
                $template_types = [
                    'post',
                    $wp_query->post->post_type
                ];
                break;
            case 'tag':
            case 'category':
            case 'tax':
                $template_types = [ 'term' ];
                $obj = $wp_query->get_queried_object();
                if ( !empty( $obj->term_id ) && !empty( $obj->taxonomy ) ) {
                    $template_types[] = $obj->taxonomy;
                }
                break;
            default:
                $template_types = [ $template_type ];
        }

        return $template_types;
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
        $snake_name = $this->snake( $name );

        /**
         * Action: akamai_pre_emit_{$HEADER_NAME}
         * Action: akamai_post_emit_{$HEADER_NAME}
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

    /**
     * A small helper for dasherizing strings.
     *
     * @since  0.7.0
     * @param  string $val The string to dasherize.
     * @return string The dasherized string.
     */
    public function dashes( $val ) {
        return str_replace( '_', '-', mb_strtolower( $val, 'UTF-8' ) );
    }

    /**
     * A small helper for snake-casing strings.
     *
     * @since  0.7.0
     * @param  string $val The string to snake-case.
     * @return string The snake-cased string.
     */
    public function snake( $val ) {
        return str_replace( '-', '_', mb_strtolower( $val, 'UTF-8' ) );
    }
}
