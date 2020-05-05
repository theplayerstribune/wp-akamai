<?php

namespace Akamai\WordPress\Admin;

/**
 * Notice is a helper to encapsulate and normalize notice/message
 * delivery and logging across the plugin.
 *
 * @since   0.7.0
 * @package Akamai\WordPress\Admin
 */
class Notice {

    /**
     * A static function to create and then immediately display a notice
     * instance. Make sure to hook this action up at the right time!
     *
     * Since we are making the instance disposible, make sure you handle
     * the logic where this is called to keep it from generating
     * multiple notices.
     *
     * @since 0.7.0
     * @param string $message The notice message.
     * @param array  $classes Optional. The notice's classes. Defaults
     *               to an empty array. If one of the classes: "error",
     *               "warning", or "info"/"information" is included,
     *               then the notice's type is inferred as those, other-
     *               wise it's "success".
     * @param string $id Optional. The notice's ID. Defaults to a
     *               standard name based on the nonce.
     * @param bool   $force_log Optional. Forces this notice to log when
     *               it displays. Defaults to false.
     */
    public static function display(
        $message, $classes = [], $id = null, $force_log = false ) {

        $instance = new static( $message, $classes, $id, $force_log );
        $instance->display_notice();
    }

    /**
     * A static function to create and then immediately log a notice
     * instance.
     *
     * Since we are making the instance disposible, make sure you handle
     * the logic where this is called to keep it from generating
     * multiple notices.
     *
     * @since 0.7.0
     * @param string $message The notice message.
     * @param array  $classes Optional. The notice's classes. Defaults
     *               to an empty array. If one of the classes: "error",
     *               "warning", or "info"/"information" is included,
     *               then the notice's type is inferred as those, other-
     *               wise it's "success".
     * @param string $id Optional. The notice's ID. Defaults to a
     *               standard name based on the nonce.
     */
    public static function log( $message, $classes = [], $id = null ) {
        $instance = new static( $message, $classes, $id );
        $instance->log_notice();
    }

    /**
     * @since 0.7.0
     * @var   string $message This notice's message.
     */
    public $message = '';

    /**
     * @since 0.7.0
     * @var   array $classes The classes to attach to this notice
     *              (beyond the default).
     */
    public $classes = [];

    /**
     * @since 0.7.0
     * @var   bool $force_log Whether this notice should be logged when
     *             it's displayed.
     */
    public $force_log = false;

    /**
     * @since  0.7.0
     * @access protected
     * @var    string $nonce A random nonce for the notice.
     */
    protected $nonce = null;

    /**
     * @since  0.7.0
     * @access protected
     * @var    string|null $id The notice's ID. Used as the HTML <div>
     *                     "id" attribute and as a log id.
     */
    protected $id = null;

    /**
     * @since  0.7.0
     * @access protected
     * @var    bool $is_dismissible Whether this notice is dismissible.
     */
    protected $is_dismissible = true;

    /**
     * @since  0.7.0
     * @access protected
     * @var    bool $has_logged Whether the notice has been logged.
     */
    protected $has_logged = false;

    /**
     * @since  0.7.0
     * @access protected
     * @var    bool $has_displayed Whether the notice has been displayed.
     */
    protected $has_displayed = false;

    /**
     * Initialize a new notice. NOTE: since this uses wp_create_nonce()
     * it kind of needs to be called in the context of WordPress, ideally
     * after the init action. SORRY TO COUPLE SO TIGHTLY to all of you
     * Martin Fowler-heads out there.
     *
     * @since 0.7.0
     * @param string $message The notice message.
     * @param array  $classes Optional. The notice's classes. Defaults
     *               to an empty array. If one of the classes: "error",
     *               "warning", or "info"/"information" is included,
     *               then the notice's type is inferred as those, other-
     *               wise it's "success".
     * @param string $id Optional. The notice's ID. Defaults to a
     *               standard name based on the nonce. When logging, we
     *               can use the ID as an error type/code.
     * @param bool   $force_log Optional. Forces this notice to log when
     *               it displays. Defaults to false.
     */
    public function __construct(
        $message, $classes = [], $id = null, $force_log = false ) {

        $this->id = $id;
        $this->message = $message;
        $this->classes = $classes;
        $this->force_log = $force_log;
        $this->nonce = wp_create_nonce();
    }

    /**
     * Getter/setter for dismissability. We really want that to be on,
     * but who knows, maybe someone out there is a stickler about it.
     *
     * @since  0.7.0
     * @param  bool|null $is_dismissible Optional. When not sent, acts
     *                   as a getter. Otherwise, sets is_dismissible.
     * @return bool      The current is_dismissible setting.
     */
    public function dismissible( $is_dismissible = null ) {
        if ( $is_dismissible !== null ) {
            $this->is_dismissible = (bool) $is_dismissible;
        }
        return $this->is_dismissible;
    }

    /**
     * Getter/setter for notice's ID. Who knows why, but someone may
     * like to have an ID, and if they want that, maybe they'll want to
     * set it as well. Forces an akamai namespace.
     *
     * @since  0.7.0
     * @param  string|null $id Optional. When not sent, acts as a getter.
     *                     Otherwise, sets id.
     * @return string      The current id setting. If there is none, it
     *                     is memoized as a default id with nonce.
     */
    public function id( $id = null ) {
        if ( $id !== null ) {
            $this->id = (string) $id;
        }
        if ( $this->id == null ) {
            $this->id = "notice-{$this->nonce}";
        }
        return $this->id;
    }

    /**
     * Getter/setter for notice's ID that forces an akamai namespace.
     * Best for templating.
     *
     * @since  0.7.0
     * @param  string|null $id Optional. When not sent, acts as a getter.
     *                     Otherwise, sets id.
     * @return string      The current id setting. If there is none, it
     *                     is memoized as a default id with nonce.
     */
    public function id_attr( $id = null ) {
        return 'akamai-' . $this->id( $id );
    }

    /**
     * Getter for notice's nonce.
     *
     * @since  0.7.0
     * @return string The nonce.
     */
    public function nonce() {
        return $this->nonce;
    }

    /**
     * A helper function to check the classes for the notice.
     *
     * @since  0.7.0
     * @param  string $class A single class to check.
     * @return bool   Whether that class is in this notice's classes.
     */
    public function has_class( $class ) {
        $index = array_search( $class, $this->classes, true );
        return $index !== false;
    }

    /**
     * Gets the notice type, inferred from its classes. One of:
     * success, information, warning, or error.
     *
     * @since  0.7.0
     * @return string The notice's type.
     */
    public function type() {
        $type = 'success';
        if ( $this->has_class( 'error' ) ) {
            $type = 'error';
        } elseif ( $this->has_class( 'warning' ) ) {
            $type = 'warning';
        } elseif (
            $this->has_class( 'info' ) || $this->has_class( 'information' )
        ) {
            $type = 'information';
        }
        return $type;
    }

    /**
     * Generates a class attribute (ie, a classname) for HTML from the
     * given classes for the notice.
     *
     * @since  0.7.0
     * @return string The complete class attribute to be templated.
     */
    public function classname() {
        $type = $this->type();
        $class_list = count( $this->classes ) > 0
            ? ' ' . implode( ' ', $this->classes )
            : '';
        $is_dismissible = $this->dismissible() ? ' is-dismissible' : '';
        return "notice notice-{$type}{$class_list}{$is_dismissible}";
    }

    /**
     * Includes the notice template with the current instance's settings.
     * This will print it to the response. Make sure to hook this action
     * up at the right time!
     *
     * By default, it will log as well (based on the internal setting)
     * and will only display if it has not displayed, excepting an
     * override is passed.
     *
     * @since  0.7.0
     * @param  bool $override_has_displayed Optional. Defaults to false.
     * @return void
     */
    public function display_notice( $override_has_displayed = false ) {
        if ( $this->force_log ) {
            $this->log_notice();
        }
        if ( $this->has_displayed && ! $override_has_displayed ) {
            return;
        }
        include AKAMAI_PLUGIN_PATH . 'admin/partials/admin-notice.php';
        return;
    }

    /**
     * Logs the current notice to error_log(). Ensures that any given
     * notice is only logged once.
     *
     * @since  0.7.0
     * @param  bool $override_has_logged Optional. Defaults to false.
     * @return void
     */
    public function log_notice( $override_has_logged = false ) {
        if ( $this->has_logged && ! $override_has_logged ) {
            return;
        }
        $type = $this->type();
        $payload = [
            'type'    => $type,
            'message' => $this->message,
        ];
        if ( 'warning' === $type ) {
            $payload['error'] = 'akamai:warning:' . $this->id();
        }
        if ( 'error' === $type ) {
            $payload['error'] = 'akamai:' . $this->id();
        }
        error_log( print_r( $payload, true ) );
        $this->has_logged = true;
        return;
    }
}
