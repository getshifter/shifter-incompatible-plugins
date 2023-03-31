<?php
/*
Plugin Name: Shifter - Incompatible Plugins
Plugin URI: https://github.com/getshifter/shifter-incompatible-plugins
Description: Shifter incompatible plugins
Version: 0.2.1
Author: Shifter Team
Author URI: https://getshifter.io
License: GPLv2 or later
*/

if (!defined('ABSPATH')) {
    exit; // don't access directly
};

class ShifterIncompatiblePlugins
{
    const STATUS      = 'incompatible';
    const LIST_URL    = 'https://download.getshifter.io/incompatible-plugins.json';

    static $instance;
    private $support_url = 'https://support.getshifter.io/articles/3086606-incompatible-wordpress-plugins';

    public function __construct()
    {
    }

    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c();
        }
        return self::$instance;
    }

    private function _get_incompatible_list()
    {
        static $incompatible_plugins;

        if (!isset($incompatible_plugins)) {
            $transientKey = 'ShifterIncompatiblePlugins::IncompatibleList';
            if (!($incompatible = get_transient($transientKey))) {
                $incompatible = [];
                $url = apply_filters('ShifterIncompatiblePlugins::ListUrl', self::LIST_URL);
                $response = wp_remote_get($url, ['timeout' => 30]);
                if (!is_wp_error($response) && $response['response']['code'] === 200) {
                    $incompatible = json_decode($response['body']);
                    set_transient($transientKey, $incompatible_plugins, 24 * HOUR_IN_SECONDS);
                }
            }
            if (isset($incompatible->supportUrl)) {
                $this->support_url = esc_url($incompatible->supportUrl);
            }
            $incompatible_plugins = isset($incompatible->plugins) ? $incompatible->plugins : $incompatible;
            $incompatible_plugins = apply_filters('ShifterIncompatiblePlugins::List', $incompatible_plugins);
        }
        return $incompatible_plugins;
    }

    private function _get_all_plugins()
    {
        if (!function_exists('get_plugins')) {
            include_once ABSPATH.'/wp-admin/includes/plugin.php';
        }
        return get_plugins();
    }

    public function incompatible()
    {
        static $incompatible;

        if (!isset($incompatible)) {
            $incompatible = [];
            foreach ($this->_get_all_plugins() as $plugin_name => $plugin_detail) {
                foreach ($this->_get_incompatible_list() as $incompatible_plugin) {
                    if (preg_match('#^'.preg_quote($incompatible_plugin).'/?#', $plugin_name)) {
                        $incompatible[$plugin_name] = $plugin_detail;
                        break;
                    }
                }
            }
        }
        return apply_filters('ShifterIncompatiblePlugins::Incompatible', $incompatible);
    }

    private function _chk_status()
    {
        return isset($_REQUEST['plugin_status']) && self::STATUS === esc_html($_REQUEST['plugin_status']);
    }

    public function admin_notice__warning()
    {
        $class = 'notice notice-warning';
        $message = sprintf(
            __(
                'It shows list of known incompatible plugins we highly reccomend you to disable on Shifter because of known issues in generating process presently.<br>'.
                'Known incompatible plugins: <a href="%s" target="_brank">Shifter - Incompatible WordPress plugins</a>'
            ),
            apply_filters('ShifterIncompatiblePlugins::SupportURL', $this->support_url)
        );

        printf(
            '<div class="%1$s"><p>%2$s</p></div>',
            esc_attr($class),
            $message
        );
    }

    public function admin_notice()
    {
        if (count($this->incompatible()) > 0) {
            add_action('admin_notices', [$this, 'admin_notice__warning']);
        }
    }

    public function pre_current_active_plugins($all_plugins)
    {
        global $status, $wp_list_table;

        if ($this->_chk_status()) {
            $incompatible_plugins = $this->incompatible();
            $status = self::STATUS;

            $page = $wp_list_table->get_pagenum();
            $total_this_page = count($incompatible_plugins);
            $plugins_per_page = $wp_list_table->get_items_per_page(
                str_replace('-', '_', 'plugins_per_page'),
                999
            );

            if ($total_this_page > $plugins_per_page) {
                $start = ($page - 1) * $plugins_per_page;
                $incompatible_plugins = array_slice(
                    $incompatible_plugins,
                    $start,
                    $plugins_per_page
                );
            }

            $wp_list_table->items = $incompatible_plugins;
            $wp_list_table->set_pagination_args(
                [
                    'total_items' => $total_this_page,
                    'per_page'    => $plugins_per_page,
                ]
            );
        }
    }

    public function views_plugins($status_links)
    {
        global $totals, $status;

        $current_class_string = ' class="current" aria-current="page"';

        if ($this->_chk_status()) {
            $status = self::STATUS;
            foreach ($status_links as $type => $text) {
                if (strstr($text, $current_class_string)) {
                    $status_links[$type] = str_replace(
                        $current_class_string,
                        '',
                        $text
                    );
                    break;
                }
            }
        }

        $type = self::STATUS;
        $count = count($this->incompatible());
        $text = _n(
            'Incompatible <span class="count">(%s)</span>',
            'Incompatibles <span class="count">(%s)</span>',
            $count
        );
        $status_link = sprintf(
            "<a href='%s'%s>%s</a>",
            add_query_arg('plugin_status', $type, 'plugins.php'),
            ( $type === $status ) ? $current_class_string : '',
            sprintf($text, number_format_i18n($count))
        );

        $status_links = array_merge(
            [$type => $status_link],
            $status_links
        );

        return $status_links;
    }

    public function wp_redirect($location)
    {
        if ($this->_chk_status()) {
            if (strstr($location, 'plugins.php') && strstr($location, 'plugin_status=all')) {
                $location = str_replace(
                    'plugin_status=all',
                    'plugin_status='.self::STATUS,
                    $location
                );
            }
        }
        return $location;
    }

    public function admin_init()
    {
        if (count($this->incompatible()) > 0) {
            add_action('load-plugins.php', [$this, 'admin_notice']);
            add_action('pre_current_active_plugins', [$this, 'pre_current_active_plugins']);
            add_filter('views_plugins', [$this, 'views_plugins']);
            add_filter('wp_redirect', [$this, 'wp_redirect']);
        }
    }
}

if (is_admin()) {
    $incompatible = ShifterIncompatiblePlugins::get_instance();
    add_action('admin_init', [$incompatible, 'admin_init']);
}
