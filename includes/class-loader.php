<?php

namespace Akamai\WordPress;

/**
 * Loader registers all actions and filters for the plugin.
 *
 * Maintains a list of all hooks that are registered throughout
 * the plugin, and registers them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @since   0.1.0
 * @package Akamai\WordPress
 * @author  Davey Shafik <dshafik@akamai.com>
 */
class Loader {

    /**
     * The array of actions registered with WordPress.
     *
     * @since  0.1.0
     * @access protected
     * @var    array $actions The actions registered with WordPress to
     *               fire when the plugin loads.
     */
    protected $actions;

    /**
     * The array of filters registered with WordPress.
     *
     * @since  0.1.0
     * @access protected
     * @var    array $filters The filters registered with WordPress to
     *               fire when the plugin loads.
     */
    protected $filters;

    /**
     * Initialize collections used to maintain the actions and filters.
     *
     * @since    0.1.0
     */
    public function __construct() {
        $this->actions = [];
        $this->filters = [];
    }

    /**
     * Add a new action to be registered with WordPress.
     *
     * @since 0.1.0
     *
     * @param string $hook The name of the WordPress action that is
     *               being registered.
     * @param mixed  $callable The callable to run on the hook.
     * @param int    $priority Optional: hook priority. Default is 10.
     * @param int    $accepted_args Optional: arguments passed to the
     *               $callback. Default is 1.
     */
    public function add_action(
        $hook, $callable, $priority = 10, $accepted_args = 1 ) {

        $this->actions = $this->add(
            $this->actions,
            $hook,
            $callable,
            $priority,
            $accepted_args
        );
    }

    /**
     * Add a new filter to be registered with WordPress.
     *
     * @since 0.1.0
     *
     * @param string $hook The name of the WordPress filter that is
     *               being registered.
     * @param mixed  $callable The callable to run on the hook.
     * @param int    $priority Optional: hook priority. Default is 10.
     * @param int    $accepted_args Optional: arguments passed to the
     *               $callback. Default is 1.
     */
    public function add_filter(
        $hook, $callable, $priority = 10, $accepted_args = 1 ) {

        $this->filters = $this->add(
            $this->filters,
            $hook,
            $callable,
            $priority,
            $accepted_args
        );
    }

    /**
     * A utility function that is used to register the actions and hooks
     * into a single collection.
     *
     * @since  0.1.0
     * @access private
     *
     * @param  array  $hooks The collection of hooks that is being
     *                registered (that is, actions or filters).
     * @param  string $hook The name of the WordPress action that is
     *                being registered.
     * @param  mixed  $callable The callable to run on the hook.
     * @param  int    $priority Optional: hook priority. Default is 10.
     * @param  int    $accepted_args Optional: arguments passed to the
     *                $callback. Default is 1.
     *
     * @return array  The collection of actions and filters registered
     *                with WordPress.
     */
    private function add(
        $hooks, $hook, $callable, $priority, $accepted_args ) {

        $hooks[] = [
            'hook'          => $hook,
            'callable'      => $callable,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        ];

        return $hooks;

    }

    /**
     * Register the filters and actions with WordPress.
     *
     * @since 0.1.0
     */
    public function run() {
        foreach ( $this->filters as $hook ) {
            add_filter(
                $hook['hook'],
                $hook['callable'],
                $hook['priority'],
                $hook['accepted_args']
            );
        }
        foreach ( $this->actions as $hook ) {
            add_action(
                $hook['hook'],
                $hook['callable'],
                $hook['priority'],
                $hook['accepted_args']
            );
        }

    }
}
