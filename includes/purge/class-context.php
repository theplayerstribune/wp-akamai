<?php

namespace Akamai\WordPress\Purge;

/**
 * Class Akamai\WordPress\Purge\Context
 *
 * Context implements a mutable set of metadata describing a purge
 * request to the Akamai Fast Purge v3 API. The metadata, stored as
 * properties, can be queried or updated as necessary, and the class
 * includes helpers for logging and profiling.
 *
 * @since   0.7.0
 * @package Akamai\WordPress\Purge
 */
class Context {

    /**
     * The list of properties to store in a context. Should completely
     * and explicitly describe a purge event.
     *
     * @since 0.7.0
     * @access private
     *
     * @var string|null $trigger_action
     * @var mixed|null  $object
     * @var int|null    $object_id
     * @var string|null $object_type
     * @var string|null $object_group
     * @var string|null $hostname
     * @var string|null $purge_type
     * @var string|null $purge_method
     * @var string|null $purge_network
     * @var array|null  $purge_objects
     * @var string|null $version
     * @var array       $meta
     */
    private $trigger_action = null;
    private $object = null;
    private $object_id = null;
    private $object_type = null;
    private $object_group = null;
    private $hostname = null;
    private $purge_type = null;
    private $purge_method = null;
    private $purge_network = null;
    private $purge_objects = null;
    private $version = null;
    private $meta = [];

    /**
     * Initialize a new Purge\Context.
     *
     * @param string $action The name of the triggering action.
     * @param mixed  $object The WP object that triggered the purge.
     *               Must be one of WP_User, WP_Term, or WP_Post.
     * @param Akamai\WordPress\Plugin $plugin An Akamai plugin instance,
     *               necessary for pulling settings.
     */
    public function __construct( $action, $object, $plugin ) {
        $this->trigger_action = $action;
        $this->object         = $object;
        $this->hostname       = $plugin->setting( 'hostname' );
        $this->purge_type     = $plugin->setting( 'purge-type' );
        $this->purge_method   = $plugin->setting( 'purge-method' );
        $this->purge_network  = $plugin->setting( 'purge-network' );
        $this->version        = $plugin->setting( 'version' );
        $this->purge_objects  = [];

        $this->purge_type = ! empty( $this->purge_type )
            ? $this->purge_type
            : 'invalidate';
        $this->purge_method = ! empty( $this->purge_method )
            ? $this->purge_method
            : 'url';
        $this->purge_network = ! empty( $this->purge_network )
            ? $this->purge_network
            : 'staging';
        $this->version = ! empty( $this->version )
            ? $this->version
            : '-';
    }

    /**
     * A getter for the trigger_action property.
     *
     * @since  0.7.0
     * @return string The trigger action name.
     */
    public function trigger_action() {
        return (string) $this->trigger_action;
    }

    /**
     * A setter for the object property. Clears out dependent props as
     * well.
     *
     * @since  0.7.0
     * @param  mixed   $object The trigger object.
     * @return Context This context instance, so you can chain.
     */
    public function set_object( $object ) {
        $this->object = $object;
        $this->object_id = null;
        $this->object_type = null;
        $this->object_group = null;
        return $this;
    }

    /**
     * A getter for the object property.
     *
     * @since  0.7.0
     * @return mixed The trigger object.
     */
    public function get_object() {
        return $this->object;
    }

    /**
     * A getter for the object_type property. Pulls the type from the
     * stored object property's class, storing as a string.
     *
     * @since  0.7.0
     * @return string The object type, one of 'post', 'term' or 'user'.
     */
    public function object_type() {
        if ( is_null( $this->object_type ) ) {
            if ( is_a( $this->object, 'WP_User' ) ) {
                $this->object_type = 'user';
            } else if ( is_a( $this->object, 'WP_Term' ) ) {
                $this->object_type = 'term';
            } else {
                $this->object_type = 'post';
            }
        }
        return (string) $this->object_type;
    }

    /**
     * A getter for the object_id property.
     *
     * @since  0.7.0
     * @return int The object ID.
     */
    public function object_id() {
        if ( is_null( $this->object_id ) ) {
            switch ( $this->object_type() ) {
                case 'term':
                    $this->object_id = $this->object->term_id;
                    break;
                case 'user':
                case 'post':
                default:
                    $this->object_id = $this->object->ID;
            }
        }
        return (int) $this->object_id;
    }

    /**
     * A getter for the object_group property.
     *
     * @since  0.7.0
     * @return string The object group: a classifier based on the
     *                object's type: either its post_type, tax, or roles.
     */
    public function object_group() {
        if ( is_null( $this->object_group ) ) {
            switch ( $this->object_type() ) {
                case 'term':
                    $this->object_group = $this->object->taxonomy;
                    break;
                case 'user':
                    $this->object_group =
                        implode( ':', $this->object->roles );
                    break;
                case 'post':
                default:
                    $this->object_group = $this->object->post_type;
            }
        }
        return (string) $this->object_group;
    }

    /**
     * A getter and setter for the hostname property. If a value is
     * passed, it sets the property. The current property is always
     * returned.
     *
     * @since  0.7.0
     * @param  string $hostname The hostname
     * @return string The hostname.
     */
    public function hostname( $hostname = null ) {
        if ( ! is_null( $hostname ) ) {
            $this->hostname = $hostname;
        }
        return (string) $this->hostname;
    }

    /**
     * A getter and setter for the purge_type property. If a value is
     * passed, it sets the property. The current property is always
     * returned.
     *
     * @since  0.7.0
     * @param  string $purge_type The purge_type
     * @return string The purge_type.
     */
    public function purge_type( $type = null ) {
        if ( ! is_null( $type ) ) {
            $this->purge_type = $type;
        }
        return (string) $this->purge_type;
    }

    /**
     * A getter and setter for the purge_method property. If a value is
     * passed, it sets the property. The current property is always
     * returned, after a quick validation.
     *
     * @since  0.7.0
     * @param  string $purge_method The purge_method
     * @return string The purge_method.
     */
    public function purge_method( $method = null ) {
        if ( ! is_null( $method ) ) {
            $this->purge_method = $method;
        }
        $method = $this->purge_method;

        // Transition setting values to actual sent values.
        if ( 'arl' === $method ) {
            $method = 'url';
        }

        return (string) $method;
    }

    /**
     * A getter and setter for the purge_network property. If a value is
     * passed, it sets the property. The current property is always
     * returned, after a quick validation.
     *
     * @since  0.7.0
     * @param  string $purge_network The purge_network
     * @return string The purge_network.
     */
    public function purge_network( $network = null ) {
        if ( ! is_null( $network ) ) {
            $this->purge_network = $network;
        }
        $network = $this->purge_network;

        // Transition setting values to actual sent values.
        if ( 'all' === $network ) {
            $network = '';
        }

        return (string) $network;
    }

    /**
     * A getter and setter for the purge_objects property. If a value is
     * passed, it sets the property. The current property is always
     * returned.
     *
     * @since  0.7.0
     * @param  array $purge_objects The purge_objects
     * @return array The purge_objects.
     */
    public function purge_objects( $objects = null ) {
        if ( ! is_null( $objects ) ) {
            $this->purge_objects = $objects;
        }
        return (array) $this->purge_objects;
    }

    /**
     * A getter for the meta property.
     *
     * @since  0.7.0
     * @return array The meta property.
     */
    public function meta() {
        return $this->meta;
    }

    /**
     * A setter for general/optional metadata.
     *
     * @since  0.7.0
     * @param  string $name The name of the metadata.
     * @param  string $value The value of the metadata.
     * @return array  The meta property.
     */
    public function set_meta( $name, $value ) {
        $this->meta[ $name ] = $value;
        return $this->meta;
    }

    /**
     * Templates the purge path for the Akamai Fast Purge v3 API.
     *
     * @since  0.7.0
     * @return string The path.
     */
    public function path() {
        return
            "/ccu/v3/{$this->purge_type()}" .
            "/{$this->purge_method()}" .
            "/{$this->purge_network()}";
    }

    /**
     * Get the context as a PHP array (hash). Map property names to
     * dasherized versions to align with settings properties, so a
     * settings hash and a context hash are interchangeable. Map values
     * the results of getters to ensure data consistency and validation.
     *
     * @since  0.7.0
     * @return array A list of all the gettable properties in the context.
     */
    public function to_hash() {
        $properties = array_keys( get_object_vars( $this ) );
        $hash = [];
        foreach ( $properties as $prop ) {
            if ( method_exists( $this, $prop ) ) {
                $hash[ $this->dashes( $prop ) ] =
                    call_user_func( [ $this, $prop ] );
            }
        }
        return $hash;
    }

    /**
     * The default format for the Context::to_string() method.
     *
     * @since 0.7.0
     * @static
     * @var string $default_log_format
     */
    public static $default_log_format =
        'purgectx/%9$s => %1$s:%3$s/%4$s/%2$s%8$s ; p:%5$s ; h:%6$s ; o:%7$s';

    /**
     * A string serializer for the context. Useful for logging.
     *
     * @since  0.7.0
     * @param  string Optional. A C-printf format string to apply to the
     *                context. Defaults to Context::$default_log_format.
     * @return string The formatted serialization string.
     */
    public function to_string( $format = '' ) {
        $props = $this->to_hash();
        $meta = empty( $props['meta'] )
            ? ''
            : '?' . http_build_query( $props['meta'] );

        if ( empty( $format ) ) {
            $format = static::$default_log_format;
        }
        return sprintf(
            $format,
            /* 1 */ (string) $props['trigger-action'],
            /* 2 */ (string) $props['object-id'],
            /* 3 */ (string) $props['object-type'],
            /* 4 */ (string) $props['object-group'],
            /* 5 */ (string) $this->path(),
            /* 6 */ (string) $props['hostname'],
            /* 7 */ implode( ',', $props['purge-objects'] ),
            /* 8 */ $meta,
            /* 9 */ (string) $this->version
        );
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
}
