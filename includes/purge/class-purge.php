<?php

namespace Akamai\WordPress\Purge;

use \Akamai\WordPress\Admin\Admin;
use \Akamai\WordPress\Admin\Notice;
use \Akamai\WordPress\Cache\Cache_Tags;

/**
 * Purge is a singleton for managing purge behavior.
 *
 * Lists default purge actions, implements standard business logic and
 * hooks around purges, and fires off purge requests. Based on
 * \Purgely_Purges from the Fastly WP plugin.
 *
 * @since   0.7.0
 * @package Akamai\WordPress\Purge
 */
class Purge {

    /**
     * The one instance of Purge.
     *
     * @since 0.7.0
     * @var   Purge
     */
    private static $instance;

    /**
     * Instantiate or return the one Purge instance.
     *
     * @since  0.7.0
     * @param  string $plugin The Plugin class instance.
     * @return Purge  The created instance.
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
     * A reference to the cache tags class instance.
     *
     * @since  0.7.0
     * @var    Cache_Tags $tagger The cache tags class instance.
     */
    public $tagger;

    /**
     * Initiate actions.
     *
     * @since  0.7.0
     * @access protected
     * @param  string $plugin The Plugin class instance.
     */
    protected function __construct( $plugin ) {
        $this->plugin = $plugin;
        $this->tagger = Cache_Tags::instance( $plugin );

        // TODO: send these back to the plugin loader.
        // TODO: add auther/user update purges!
        $instance = $this; // Just to be PHP < 6.0 compliant.
        foreach ( $this->purge_post_actions() as $action ) {
            add_action(
                $action,
                function ( $post_id ) use ( $instance, $action ) {
                    return $instance->purge_post( $post_id, $action );
                },
                10,
                1
            );
        }
        foreach ( $this->purge_term_actions() as $action ) {
            add_action(
                $action,
                function ( $term_id, $tt_id, $taxonomy ) use ( $instance, $action ) {
                    return $instance->purge_term( $term_id, $action, $tt_id, $taxonomy );
                },
                10,
                3
            );
        }
    }

    /**
     * Generate a purge context metadata object from current purge settings
     * and the specifics of the triggering update and the underlying WP
     * object which is being updated. This metadata is used:
     *
     *   - to trigger/inform more specific customization filters;
     *   - to allow filters to change the type of purge meta as necessary;
     *   - by the purge client to generate an actual request;
     *   - for logging and profiling purposes.
     *
     * TODO: upgrade the current system into an actual purge context class
     *       with its own methods / biz logic.
     *
     * @since  0.7.0
     * @param  string $action The name of the action that triggered the
     *                purge.
     * @param  object $target The WP object on which the purge was
     *                triggered. Can be a WP_Post, WP_Term, or WP_User.
     * @return Purge_Context The basic, mutable purge metadata instance
     *                       to handle a triggered purge.
     */
    public function purge_info( $action, $object_type, $object_id, $taxonomy = '' ) {
        $info = [
            'action' => $action,
            'purge-type' => 'invalidate', // FIXME: pull from settings!
        ];


        switch ( $object_type ) {
            case 'post':
                $info['target-type'] = 'post-' . \get_post_type( $object_id );
                $info['target-object'] = \get_post( $object_id );
                $info['purge-objects'] = $this->tagger->get_tags_for_purge_post( $object_id );
                break;
            case 'term':
                $info['target-type'] = 'term-' . $taxonomy;
                $info['target-object'] = \get_term( $object_id );
                $info['purge-objects'] =
                    $this->tagger->get_tags_for_purge_term( $object_id, $taxonomy );
                break;
        }

        return $purge_info;
    }

    /**
     * A wrapper for the akamai_do_purge filter, which is the most
     * important in the purge process: it allows the user to hook into
     * a purge context and either block it, force it regardless of the
     * default setting, and edit it as necessary, right before the purge
     * request is sent.
     *
     * @since  0.7.0
     * @param  Purge_Context $purge_ctx The triggered context, passed by
     *                       reference.
     * @return bool Whether to fire the purge request (final answer).
     */
    public function do_purge( &$purge_ctx ) {
        $do_purge = $this->plugin->settings( 'purge-on-update' );

        /**
         * Filter: akamai_do_purge
         *
         * @since 0.7.0
         * @param bool  $do_purge Whether to fire the purge.
         * @param array $purge_params A list of params, passed as a hash
         *              to be mutable references. Currently, the only
         *              param is $purge_params['ctx'], the Purge_Context.
         */
        return apply_filters(
            'akamai_do_purge', $do_purge, [ 'ctx' => $purge_ctx ] );
    }

    /**
     * A business logic wrapper for firing a purge request. Structures
     * the request and handles attached action hooks.
     *
     * @since  0.7.0
     * @param  Purge_Context The purge context, ready to go.
     * @return array A normalized Akamai API response for the request.
     */
    public function purge_request( $object_type, $purge_ctx ) {
        $client = new Request(
            $this->plugin->get_edge_auth_client(),
            $this->plugin->get_user_agent()
        );

        /**
         * Action: akamai_pre_purge_{$OBJECT_TYPE}
         * Action: akamai_pre_purge
         *
         * Eg:
         *     - akamai_pre_purge_post
         *     - akamai_pre_purge_term
         *     - akamai_pre_purge_user
         *
         * First a specific hook fires for the object type, then a
         * general one fires for all purges.
         *
         * @since 0.7.0
         * @param Purge_Context $purge_ctx The metadata for the request,
         *                      right before it fires.
         */
        do_action( "akamai_pre_purge_{$object_type}", $purge_ctx );
        do_action( 'akamai_pre_purge', $purge_ctx );

        $response = $client->purge(
            $options = $this->plugin->get_settings(),
            $objects = $purge_ctx['purge-objects']
        );

        /**
         * Action: akamai_post_purge_{$OBJECT_TYPE}
         * Action: akamai_post_purge
         *
         * Eg:
         *     - akamai_post_purge_post
         *     - akamai_post_purge_term
         *     - akamai_post_purge_user
         *
         * First a specific hook fires for the object type, then a
         * general one fires for all purges.
         *
         * @since 0.7.0
         * @param array         $response The normalized Akamai API
         *                      response for the purge request.
         * @param Purge_Context $purge_ctx The metadata for the request,
         *                      right after it fires.
         */
        do_action( "akamai_post_purge_{$object_type}", $response, $purge_ctx );
        do_action( 'akamai_post_purge', $response, $purge_ctx );

        return $response;
    }

    /**
     * Callback for post-change events to trigger purges.
     *
     * @since 0.7.0
     * @param int    $post_id The post ID for the triggered post.
     * @param string $action The action that triggered the purge.
     */
    public function purge_post( $post_id, $action ) {
        $purge_info = $this->purge_info( $action, 'post', $post_id );

        if (
            did_action( 'akamai_pre_purge_post' )
            || ! $this->has_purge_post_status( $post_id )
            || ! $this->has_purge_post_type( $post_id )
            || ! $this->do_purge( $purge_info ) ) {
            return;
        }

        $this->purge_request( $object_type, $purge_info );

        $instance = $this; // Be nice, support PHP 5.
        add_filter(
            'redirect_post_location',
            function( $location ) use ( $response, $instance, $purge_info ) {
                return $instance->add_notice_query_arg(
                    $location,
                    $response,
                    $purge_info,
                    'redirect_post_location'
                );
            },
            100
        );
    }

    /**
     * Callback for term-change events to trigger purges.
     *
     * @since 0.7.0
     * @param int    $term_id The term_id for the triggered term.
     * @param string $action The action that triggered the purge.
     * @param int    $tt_id The term-taxonomy ID for the .
     * @param int    $taxonomy The action that triggered the purge.
     */
    public function purge_term( $term_id, $action, $tt_id, $taxonomy ) {
        $purge_info = $this->purge_info( $action, 'term', $term_id, $taxonomy );
        $purge_info['tt-id'] = $tt_id;

        if (
            did_action( 'akamai_pre_purge_term' )
            || ! $this->has_purge_term_taxonomy( $taxonomy )
            || ! $this->do_purge( $purge_info )
        ) {
            return;
        }

        $this->purge_request( $object_type, $purge_info );

        $instance = $this; // Be nice, support PHP 5.
        add_filter(
            'redirect_term_location',
            function( $location ) use ( $response, $instance, $purge_info ) {
                return $instance->add_notice_query_arg(
                    $location,
                    $response,
                    $purge_info,
                    'redirect_term_location'
                );
            },
            100
        );
    }

    /**
     * Add query args to set notices and other changes after a submit/update
     * that triggered a purge.
     *
     * By removing itself after running, it ensures that the hook is run
     * dynamically and once.
     *
     * @since  0.1.0
     * @param  string $location The Location header of the redirect: passed in
     *                by the filter hook.
     * @param  string $response The HTTP response code of the redirect: passed
     *                in by the filter hook.
     * @return array  $purge_info General information about the purge.
     * @param  string $filter_name The filter this is being fired in, so it can
     *                then be removed.
     */
    public function add_notice_query_arg(
        $location, $response, $purge_info, $filter_name ) {
        remove_filter( $filter_name, [ $this, 'add_notice_query_arg' ], 100 );
        if ( $response['error'] ) {

            /**
             * Filter: akamai_purge_notice_failure
             *
             * @since 0.7.0
             * @param string $message The message that will be shown in
             *               the notice.
             */
            $message = apply_filters(
                'akamai_purge_notice_failure',
                'Unable to purge cache: ' . $response['error']
            );
            return add_query_arg(
                [ 'akamai-cache-purge-error' => urlencode( $message ) ],
                $location
            );
        }
        $message = 'This object and all related cache objects purged.';
        if ( $this->plugin->setting( 'add-tags-to-notices' ) ) {
            $message .= ' ' . $this->format_cache_tags_for_message(
                $purge_info['cache-tags']
            );
        }

        /**
         * Filter: akamai_purge_notice_success
         *
         * @since 0.7.0
         * @param string $message The message that will be shown in the
         *               notice.
         */
        $message = apply_filters( 'akamai_purge_notice_success', $message );
        return add_query_arg(
            [ 'akamai-cache-purge-success' => urlencode( $message ) ],
            $location
        );
    }

    /**
     * Format those cache tags to show to people.
     *
     * @since  0.7.0
     * @param  array $cache_tags A list of cache tags to format.
     * @return string The formatted string representation for HTML.
     */
    public function format_cache_tags_for_message( $cache_tags ) {
        $f_tags = array_map(
            function( $tag ) {
                return "<code>{$tag}</code>";
            },
            $cache_tags
        );
        return 'Purged cache tags include:<br>' . join( ', ', $f_tags );
    }

    /**
     * Checks if queries have been set to create notices in the current page
     * load, and if so display them.
     *
     * @since 0.1.0
     */
    public function display_purge_notices() {
        if ( isset( $_GET['akamai-cache-purge-error'] ) ) {
            Notice::display(
                $message = $_GET['akamai-cache-purge-error'],
                $classes = [ 'error', 'purge' ],
                $id = 'cache-purge-failure',
                $force_log = $this->plugin->setting( 'log-errors' )
            );
        }
        if ( isset( $_GET['akamai-cache-purge-success'] ) ) {
            $message = $_GET['akamai-cache-purge-success'];
            $classes = [ 'purge', 'updated' ];
            if ( strpos( $message, '<code>') !== false ) {
                $classes[] = 'has-code';
            }
            Notice::display(
                $message,
                $classes,
                $id = 'cache-purge-success'
            );
        }
    }

    /**
     * A list of post actions to initiate purge.
     *
     * @since  0.7.0
     * @return array List of actions.
     */
    public function purge_post_actions() {
        /**
         * Filter: akamai_purge_post_actions
         *
         * @since 0.7.0
         * @param array $actions The list of named actions that will
         *              trigger a post purge.
         */
        return apply_filters(
            'akamai_purge_post_actions',
            [
                'save_post',
                'deleted_post',
                'trashed_post',
                'delete_attachment',
                'future_to_publish',
            ]
        );
    }

    /**
     * A list of term actions to initiate purge.
     *
     * @since  0.7.0
     * @return array List of actions.
     */
    public function purge_term_actions() {
        /**
         * Filter: akamai_purge_term_actions
         *
         * @since 0.7.0
         * @param array $actions The list of named actions that will
         *              trigger a term purge.
         */
        return apply_filters(
            'akamai_purge_term_actions',
            [
                'edit_term',
                'delete_term',
            ]
        );
    }

    /**
     * Check a given post against the filtered post statuses that will
     * allow it to be purged.
     *
     * @since  0.7.1
     * @param  int  $post_id The ID of the post to check.
     * @return bool Whether to fire a purge (given all checks pass).
     */
    public function has_purge_post_status( $post_id ) {
        $default_statuses = [ 'publish', 'trash', 'future', 'draft' ];

        /**
         * Filter: akamai_purge_post_statuses
         *
         * @since 0.7.0
         * @param array $statuses The list of post-update post statuses
         *              that a purge will fire for.
         */
        $statuses = \apply_filters(
            'akamai_purge_post_statuses', $default_statuses );
        return in_array( \get_post_status( $post_id ), $statuses, true );
    }

    /**
     * Check a given post against the filtered types that will allow it
     * to be purged.
     *
     * @since  0.7.1
     * @param  int  $post_id The ID of the post to check.
     * @return bool Whether to fire a purge (given all checks pass).
     */
    public function has_purge_post_type( $post_id ) {
        $default_types = [ 'post', 'page' ];

        /**
         * Filter: akamai_purge_post_types
         *
         * @since 0.7.0
         * @param array $types The list of post types that a purge will
         *              fire for. Must add custom post types here!
         */
        $types = \apply_filters(
            'akamai_purge_post_types', $default_types );
        return in_array( \get_post_type( $post_id ), $types, true );
    }

    /**
     * Check a given term against the filtered taxonomies that will
     * allow it to be purged.
     *
     * @since  0.7.1
     * @param  int  $taxonomy The taxonomy of the term to check.
     * @return bool Whether to fire a purge (given all checks pass).
     */
    public function has_purge_term_taxonomy( $taxonomy ) {

        /**
         * Filter: akamai_purge_taxonomies
         *
         * @since 0.7.0
         * @param array $taxonomies The list of term taxonomies that a
         *              purge will fire for. Must add custom term
         *              taxonomies here!
         */
        $taxes = \apply_filters(
            'akamai_purge_taxonomies', (array) get_taxonomies() );
        return in_array( $taxonomies, $taxes, true );
    }
}
