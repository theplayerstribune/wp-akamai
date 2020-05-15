<?php

namespace Akamai\WordPress;

/**
 * Trait Akamai\WordPress\Hook_Loader
 *
 * Implements some simple logic to emit a list of hooks defined in a
 * class constructor according to the needs of the Loader class API
 * automatically.
 *
 * @since 0.7.0
 */
trait Hook_Loader {

    /**
     * Defines a list of action hooks, which must be structured
     * according to the Loader::add_action() interface:
     *
     * [
     *     [
     *         $hook_name,
     *         $callback_context,  // Usually the implementing instance.
     *         $callback_method,   // Refers to a method name in the
     *                             // implementing class.
     *         $priority = 10,     // Optional.
     *         $accepted_args = 1, // Optional.
     *     ],
     *     ...
     * ]
     *
     * @since 0.7.0
     * @return array The list of action hooks.
     */
    public $action_hooks = [];

    /**
     * Defines a list of filter hooks, which must be structured
     * according to the Loader::add_action() interface:
     *
     * [
     *     [
     *         $hook_name,
     *         $callback_context,  // Usually the implementing instance.
     *         $callback_method,   // Refers to a method name in the
     *                             // implementing class.
     *         $priority = 10,     // Optional.
     *         $accepted_args = 1, // Optional.
     *     ],
     *     ...
     * ]
     *
     * @since 0.7.0
     * @return array The list of filter hooks.
     */
    public $filter_hooks = [];

    /**
     * Returns the list of action hooks.
     *
     * @since  0.7.0
     * @return array The list of action hooks.
     */
    public function actions() : array {
        return $this->action_hooks;
    }

    /**
     * Returns the list of filter hooks.
     *
     * @since  0.7.0
     * @return array The list of filter hooks.
     */
    public function filters() : array {
        return $this->filter_hooks;
    }
}
