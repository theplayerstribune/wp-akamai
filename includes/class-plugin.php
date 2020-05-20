<?php

namespace Akamai\WordPress;

use \Akamai\Open\EdgeGrid\Authentication as Akamai_Auth;

/**
 * Plugin is the core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since   0.1.0
 * @package Akamai\WordPress
 * @author  Davey Shafik <dshafik@akamai.com>
 */
class Plugin {

    /**
     * The unique identifier of the plugin.
     *
     * @since 0.7.0
     * @var   string The string used to uniquely identify this plugin.
     */
    public static $identifier = 'akamai';

    /**
     * Plugin's current version.
     *
     * @since 0.1.0
     * @var   string $version The current version of the plugin.
     */
    public static $version = '0.7.0';

    /**
     * The name for the plugin, essentially the same as the static
     * unique identifier.
     *
     * @since 0.7.0
     * @var   string $name The name for the plugin.
     */
    public $name;

    /**
     * The basename for the plugin core class file.
     *
     * @since 0.7.0
     * @var   string $basename The basename for the plugin core class file.
     */
    public $basename;

    /**
     * The loader that's responsible for maintaining and registering all hooks
     * that power the plugin.
     *
     * @since 0.1.0
     * @var   Loader $loader Maintains and registers all hooks for the plugin.
     */
    public $loader;

    /**
     * A reference to the admin class instance.
     *
     * @since  0.7.0
     * @var    Admin $admin The admin class instance.
     */
    public $admin;

    /**
     * A reference to the purge class instance.
     *
     * @since  0.7.0
     * @var    Purge $purge The purge class instance.
     */
    public $purge;

    /**
     * A reference to the cache headers class instance.
     *
     * @since  0.7.0
     * @var    Cache_Headers $cache The cache headers class instance.
     */
    public $cache;

    /**
     * Default credentials settings.
     *
     * @since   0.7.0
     * @var     array $default_credentials The default credentials settings.
     */
    public $default_credentials = [
        'host'          => '',
        'access-token'  => '',
        'client-token'  => '',
        'client-secret' => '',
    ];

    /**
     * Default options settings.
     *
     * @since 0.7.0
     * @var   array $default_options The default options settings.
     */
    public $default_options = [
        // 'hostname'             => ..., // Handled in Akamai::get_settings().
        'unique-sitecode'      => '',
        'add-tags-to-notices'  => 0,
        'log-errors'           => 0,
        'log-purges'           => 0,
        'emit-cache-control'   => 0,
        'emit-cache-tags'      => 0,
        'cache-default-header' => '',
        'cache-related-tags'   => 1,
        'purge-on-update'      => 0,
        'purge-network'        => 'all',
        'purge-type'           => 'invalidate',
        'purge-method'         => 'tags',
        'purge-related'        => 1,
        'purge-default'        => 1,
        'purge-on-comment'     => 0,
    ];

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout
     * the plugin. Load the dependencies, define the locale, and set the hooks
     * for the admin area and the public-facing side of the site.
     *
     * @since    0.1.0
     */
    public function __construct() {
        $this->name = static::$identifier;
        $this->basename = plugin_basename(
            plugin_dir_path( __DIR__ ) . $this->name . '.php' );

        $this->load_dependencies();
        $this->loader = new Loader();
        $this->admin = Admin\Admin::instance( $this );
        $this->purge = Purge\Purge::instance( $this );
        $this->cache = Cache\Cache_Headers::instance( $this );

        $this->define_admin_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since  0.1.0
     * @access private
     */
    private function load_dependencies() {
        require_once AKAMAI_PLUGIN_PATH . 'includes/class-loader.php';
        require_once AKAMAI_PLUGIN_PATH . 'includes/purge/class-purge.php';
        require_once AKAMAI_PLUGIN_PATH . 'includes/purge/class-request.php';
        require_once AKAMAI_PLUGIN_PATH . 'includes/purge/class-context.php';
        require_once AKAMAI_PLUGIN_PATH . 'includes/cache/class-cache-tags.php';
        require_once AKAMAI_PLUGIN_PATH . 'includes/cache/class-cache-headers.php';
        require_once AKAMAI_PLUGIN_PATH . 'includes/admin/class-admin.php';
        require_once AKAMAI_PLUGIN_PATH . 'includes/admin/class-notice.php';
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since  0.1.0
     * @access private
     */
    private function define_admin_hooks() {
        // Add Admin/Settings menu hooks to the plugin.
        $this->loader->add_action(
            'admin_enqueue_scripts', $this->admin, 'enqueue_styles' );
        $this->loader->add_action(
            'admin_enqueue_scripts', $this->admin, 'enqueue_scripts' );
        $this->loader->add_action(
            'admin_menu', $this->admin, 'add_plugin_admin_menu' );
        $this->loader->add_filter(
            "plugin_action_links_{$this->basename}",
            $this->admin,
            'add_action_links'
        );

        // Save/update plugin options; load error messages on settings page.
        $this->loader->add_action(
            'admin_init', $this->admin, 'settings_update' );
        $this->loader->add_action(
            "load-{$this->admin->menu_page_id}", $this->admin, 'settings_load' );

        // Validate Credentials AJAX.
        $this->loader->add_action(
            'wp_ajax_akamai_verify_credentials',
            $this->admin,
            'handle_verify_credentials_request'
        );

        // Purging Actions/Hooks.
        // TODO: move the hooks defined in Purge\Purge here.
        $this->loader->add_action(
            'admin_notices', $this->purge, 'display_purge_notices' );
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since 0.1.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * Get an Akamai settings array, ensuring that defaults are all set.
     * Optionally pass a subset of an Akamai settings array to override
     * the saved option values.
     *
     * @since  0.7.0
     * @param  array $settings Optional. An Akamai settings array subset.
     * @return array A complete Akamai settings array.
     */
    public function get_settings( $new_settings = [] ) {
        $settings = [];

        $old_settings = get_option( $this->name );

        foreach ( $this->default_options as $key => $default_value ) {
            $settings[$key] =
                isset( $new_settings[$key] )
                    ? $new_settings[$key]
                    : ( isset( $old_settings[$key] )
                        ? $old_settings[$key]
                        : $default_value );
        }
        foreach ( $this->default_credentials as $key => $default_cred ) {
            $settings['credentials'][$key] =
                isset( $new_settings['credentials'][$key] )
                    ? $new_settings['credentials'][$key]
                    : ( isset( $old_settings['credentials'][$key] )
                        ? $old_settings['credentials'][$key]
                        : $default_cred );
        }

        // A more dynamic default...
        if ( isset( $new_settings['hostname'] ) ) {
            $settings['hostname'] = $new_settings['hostname'];
        } elseif ( isset( $old_settings['hostname'] ) ) {
            $settings['hostname'] = $old_settings['hostname'];
        } else {
            $wpurl = parse_url( get_bloginfo( 'wpurl' ) );
            $settings['hostname'] = $wpurl['host'];
        }

        return $settings;
    }

    /**
     * A helper to extract plugin option settings. Allows us to use an
     * updated list of options (may or may not be complete) to get it.
     *
     * @since	0.7.0
     * @param	string	$option_name The setting name.
     * @param	array	$new_options Optional. An Akamai settings array subset
     *                  to override system settings.
     * @return	mixed	The setting value, or default if not set.
     */
    public function setting( $option_name, $new_options = [] ) {
        $options = $this->get_settings( $new_options );
        return isset( $options[$option_name] )
            ? $options[$option_name]
            : null;
    }

    /**
     * A helper to extract plugin credential settings.
     *
     * @since	0.7.0
     * @param	string	$credential_name The setting name.
     * @param	array	$new_options Optional. An Akamai settings array subset
     *                  to override system settings.
     * @return	mixed	The setting value, or default if not set.
     */
    public function credential( $credential_name, $new_options = [] ) {
        $options = $this->get_settings( $new_options );
        return isset( $options['credentials'][$credential_name] )
            ? $options['credentials'][$credential_name]
            : null;
    }

    /**
     * Generate a plugin-specific user agent for sending API requests.
     *
     * @since  0.7.0
     * @return string A user agent entry.
     */
    public function get_user_agent() {
        return
            'WordPress/' . get_bloginfo( 'version' ) . ' ' .
            get_class( $this ) . '/' . static::$version . ' ' .
            'PHP/' . phpversion();
    }

    /**
     * Handle generating an EdgeGrid auth client based on specific credentials,
     * without having to set env vars or upload an .edgerc file. It's a bit of a
     * hack, but the auth class does not provide a more direct way initializing
     * other than to load the .edgerc file.
     *
     * @since  0.7.0
     * @param  array       $credentials Optional. An array of credentials to use
     *                     when generating the auth client.
     * @return Akamai_Auth ...
     */
    public function get_edge_auth_client( $credentials = [] ) {
        $_ENV['AKAMAI_DEFAULT_HOST'] = isset( $credentials['host'] )
            ? $credentials['host']
            : $this->credential( 'host' );
        $_ENV['AKAMAI_DEFAULT_ACCESS_TOKEN'] = isset( $credentials['access-token'] )
            ? $credentials['access-token']
            : $this->credential( 'access-token' );
        $_ENV['AKAMAI_DEFAULT_CLIENT_TOKEN'] = isset( $credentials['client-token'] )
            ? $credentials['client-token']
            : $this->credential( 'client-token' );
        $_ENV['AKAMAI_DEFAULT_CLIENT_SECRET'] = isset( $credentials['client-secret'] )
            ? $credentials['client-secret']
            : $this->credential( 'client-secret' );
        return Akamai_Auth::createFromEnv();
    }

    /**
     * Send a credential verification request to the Fast Purge v3 API.
     *
     * @since  0.7.0
     * @param  array $settings Optional. An Akamai settings array subset.
     * @return array A normalized Akamai API response.
     */
    public function purge_api_test( $settings = [] ) {
        $credentials = [];
        if ( isset( $settings['credentials'] ) ) {
            $credentials = $settings['credentials'];
        }
        $client = new Purge\Request(
            $this->get_edge_auth_client( $credentials ),
            $this->get_user_agent()
        );
        return $client->test_creds( $log_purges = $settings['log-purges'] );
    }
}
