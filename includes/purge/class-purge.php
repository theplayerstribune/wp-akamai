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
     * Callback for post-change events to trigger purges.
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
        if ( ! in_array( get_post_status( $post_id ), $purge_post_statuses, true ) ) {
            return;
        }
        $purge_post_types = apply_filters(
            'akamai_purge_post_types',
            [ 'post', 'page' ]
        );
        if ( ! in_array( get_post_type( $post_id ), $purge_post_types, true ) ) {
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
            'purge-type'  => 'invalidate', // FIXME: pull from settings!
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
        // Only run once per request.
        if ( did_action( 'akamai_to_purge_term' ) ) {
            return;
        }
        // Make sure this is a "purged taxonomy", ie a taxonomy that we
        // run purges for.
        $purge_taxonomies = apply_filters(
            'akamai_purge_taxonomies',
            (array) get_taxonomies()
        );
        if ( ! in_array( $taxonomy, $purge_taxonomies, true ) ) {
            return;
        }

        $settings = $this->plugin->get_settings();

        // Generate objects to query. TODO: break out, switch on purge method.
        $cache_tags = Cache_Tags::instance(
            $this->plugin
        )->get_tags_for_purge_term(
            $term_id,
            $taxonomy
        );
        $purge_info = [
            'action'      => $action,
            'target-type' => 'term-' . $taxonomy,
            'target-term' => get_term( $term_id ),
            'tt-id'       => $tt_id,
            'cache-tags'  => $cache_tags,
            'purge-type'  => 'invalidate', // FIXME: pull from settings!
        ];
        $purge_params = array_values( $purge_info );
        if ( ! apply_filters( 'akamai_do_purge', true, ...$purge_params ) ) {
            return;
        }

        do_action( 'akamai_to_purge', ...$purge_params );
        do_action( 'akamai_to_purge_term', ...$purge_params );
        $client = new Request(
            $this->plugin->get_edge_auth_client(),
            $this->plugin->get_user_agent()
        );
        $response = $client->purge(
            $options = $settings,
            $objects = $cache_tags
        );
        do_action( 'akamai_purged_term', $response, ...$purge_params );

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
