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
     * Initiate actions.
     *
     * @since  0.7.0
     * @access protected
     * @param  string $plugin The Plugin class instance.
     */
    protected function __construct( $plugin ) {
        $this->plugin = $plugin;

        // TODO, send these back to the plugin loader.
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
                    // error_log( [ 'PURGE', $term_id, $tt_id, $taxonomy, $action ] );
                    // FIXME ...
                },
                10,
                3
            );
        }
    }

    /**
     * Callback for post changing events to trigger purges. THIS IS A WIP.
     *
     * @since 0.7.0
     * @param int    $post_id The post ID for the triggered post.
     * @param string $action The action that triggered the purge.
     */
    public function purge_post( $post_id, $action ) {
        // Only run once per request.
        if ( did_action( 'akamai_to_purge_post' ) ) {
            return;
        }
        $purge_post_statuses = apply_filters(
            'akamai_purge_post_statuses',
            [ 'publish', 'trash', 'future', 'draft' ]
        );
        if ( ! in_array( get_post_status( $post_id ), $purge_post_statuses ) ) {
            return;
        }

        $settings = $this->plugin->get_settings();

        // Generate objects to query. TODO: break out, switch on purge method.
        $cache_tags =
            Cache_Tags::instance( $this->plugin )->get_tags_for_purge_post( $post_id );
        $purge_info = [
            'action'      => $action,
            'target-type' => 'post-' . get_post_type( $post_id ),
            'target-post' => get_post( $post_id ),
            'cache-tags'  => $cache_tags,
            'purge-type'  => 'invalidate',
        ];
        $purge_params = array_values( $purge_info );
        if ( ! apply_filters( 'akamai_do_purge', true, ...$purge_params ) ) {
            return;
        }

        do_action( 'akamai_to_purge', ...$purge_params );
        do_action( 'akamai_to_purge_post', ...$purge_params );
        $client = new Request(
            $this->plugin->get_edge_auth_client(),
            $this->plugin->get_user_agent()
        );
        $response = $client->purge(
            $options = $settings,
            $objects = $cache_tags
        );
        do_action( 'akamai_purged_post', $response, ...$purge_params );

        if ( $response['error'] ) {
            $instance = $this; // Be nice, support PHP 5.
            add_filter(
                'redirect_post_location',
                function( $location ) use ( $response, $instance ) {
                    return $instance->add_error_query_arg(
                        $location,
                        $response
                    );
                },
                100
            );
        } else {
            add_filter(
                'redirect_post_location',
                [ $this, 'add_success_query_arg' ],
                100
            );
        }
    }

    /**
     * Add query args to set notices and other changes after a submit/update
     * that triggered a purge. MERGE WITH BELOW.
     *
     * By removing itself after running, it ensures that the hook is run
     * dynamically and once.
     *
     * @since  0.1.0
     * @param  string $location The Location header of the redirect: passed in
     *                by the filter hook.
     * @param  string $response The HTTP response code of the redirect: passed
     *                in by the filter hook.
     * @return string
     */
    public function add_error_query_arg( $location, $response ) {
        remove_filter(
            'redirect_post_location', [ $this, 'add_error_query_arg' ], 100 );
        return add_query_arg(
            [ 'akamai-cache-purge-error' => urlencode( $response['error'] ) ],
            $location
        );
    }

    /**
     * Add query args to set notices and other changes after a submit/update
     * that triggered a purge. MERGE WITH ABOVE.
     *
     * By removing itself after running, it ensures that the hook is run
     * dynamically and once.
     *
     * @since  0.1.0
     * @param  string $location The Location header of the redirect: passed in
     *                by the filter hook.
     * @return string The updated location.
     */
    public function add_success_query_arg( $location ) {
        remove_filter(
            'redirect_post_location', [ $this, 'add_success_query_arg' ], 100 );
        return add_query_arg(
            [ 'akamai-cache-purge-success' => 'true' ],
            $location
        );
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
                $message = 'Unable to purge cache: ' .
                           $_GET['akamai-cache-purge-error'],
                $classes = [ 'error', 'purge' ],
                $id = 'cache-purge-error',
                $force_log = $this->plugin->setting( 'log-errors' )
            );
        }
        if ( isset( $_GET['akamai-cache-purge-success'] ) ) {
            Notice::display(
                $message = 'Post and all related cache objects purged.',
                $classes = [ 'purge', 'updated' ]
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
        return apply_filters(
            'akamai_purge_term_actions',
            [
                'edit_term',
                'delete_term',
            ]
        );
    }
}
