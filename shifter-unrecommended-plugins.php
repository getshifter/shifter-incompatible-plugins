<?php
/*
Plugin Name: Shifter - Unrecommended Plugins
Plugin URI: https://github.com/getshifter/shifter-unrecommended-plugins
Description: Shifter unrecommended plugins
Version: 0.0.2
Author: Shifter Team
Author URI: https://getshifter.io
License: GPLv2 or later
*/

if (!defined('ABSPATH')) {
    exit; // don't access directly
};

if (is_admin()) {
    $unrecommended = ShifterUnrecommendedPlugins::get_instance();
    add_action('admin_init', [$unrecommended, 'admin_init']);
}

class ShifterUnrecommendedPlugins
{
    const UNRECOMMEND_STATUS = 'unrecommend';
    const UNRECOMMEND_PLUGIN_LIST_URL = 'https://download.getshifter.io/unrecomended-plugins.json';
    static $instance;

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

    private function _get_unrecommended_list()
    {
        $url = apply_filters('Shifter/unrecommendPluginsListUrl', self::UNRECOMMEND_PLUGIN_LIST_URL);
        $transientKey = 'Shifter/UnrecommendedPluginsList-'.$url;
        if (!($unrecommendedPlugins = get_transient($transientKey))) {
            $unrecommendedPlugins = [];
            $response = wp_remote_get($url, ['timeout' => 30]);
            if (!is_wp_error($response) && $response['response']['code'] === 200) {
                $unrecommendedPlugins = json_decode($response['body']);
            }
            set_transient($transientKey, $unrecommendedPlugins, 24 * HOUR_IN_SECONDS);
        }
        return apply_filters('Shifter/unrecommendPluginsList', $unrecommendedPlugins);
    }

    private function _get_all_plugins()
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH.'/wp-admin/includes/plugin.php';
        }
        return get_plugins();
    }

    public function unrecommended()
    {
        static $unrecommended;

        if (!isset($unrecommended)) {
            $unrecommended = [];
            foreach ($this->_get_all_plugins() as $plugin_name => $plugin_detail) {
                foreach ($this->_get_unrecommended_list() as $unrecommended_plugin) {
                    if (preg_match('#^'.preg_quote($unrecommended_plugin).'/?#', $plugin_name)) {
                        $unrecommended[$plugin_name] = $plugin_detail;
                        break;
                    }
                }
            }
        }
        return $unrecommended;
    }

    private function _chk_status()
    {
        return self::UNRECOMMEND_STATUS === $_REQUEST['plugin_status'];
    }

    public function pre_current_active_plugins($all_plugins)
    {
        global $status, $wp_list_table;
        if ($this->_chk_status()) {
            $unrecommended = $this->unrecommended();
            $status = self::UNRECOMMEND_STATUS;

            $page = $wp_list_table->get_pagenum();
            $plugins_per_page = $wp_list_table->get_items_per_page(str_replace( '-', '_', 'plugins_per_page' ), 999);
            $start = ($page - 1) * $plugins_per_page;
            if ($total_this_page > $plugins_per_page) {
                $unrecommended = array_slice($unrecommended, $start, $plugins_per_page);
            }
            $wp_list_table->items = $unrecommended;
            $wp_list_table->set_pagination_args(
                [
                    'total_items' => count($unrecommended),
                    'per_page'    => $plugins_per_page,
                ]
            );
        }
    }

    public function views($status_links)
    {
        global $totals, $status;

        $current_class_string = ' class="current" aria-current="page"';

        if ($this->_chk_status()) {
            $status = self::UNRECOMMEND_STATUS;
            foreach ($status_links as $type => $text) {
                $status_links[$type] = str_replace($current_class_string, '', $text); 
            }
        }

        $type = self::UNRECOMMEND_STATUS;
        $count = count($this->unrecommended());
        $text = _n('Unrecommended <span class="count">(%s)</span>', 'Unrecommended <span class="count">(%s)</span>', $count);
        $status_link = sprintf(
            "<a href='%s'%s>%s</a>",
            add_query_arg('plugin_status', $type, 'plugins.php'),
            ( $type === $status ) ? $current_class_string : '',
            sprintf($text, number_format_i18n($count))
        );
        $status_links = array_merge([$type => $status_link], $status_links);

        return $status_links;
    }

    public function wp_redirect($location)
    {
        if ($this->_chk_status()) {
            if (strstr($location, 'plugins.php') && strstr($location, 'plugin_status=all')) {
                $location = str_replace('plugin_status=all', 'plugin_status='.self::UNRECOMMEND_STATUS, $location);
            }
        }
        return $location;
    }

    public function admin_init()
    {
        if (count($this->unrecommended()) > 0) {
            add_action('pre_current_active_plugins', [$this, 'pre_current_active_plugins']);
            add_filter('views_plugins', [$this, 'views']);
            add_filter('wp_redirect', [$this, 'wp_redirect']);
        }
    }
}