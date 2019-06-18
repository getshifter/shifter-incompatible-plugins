<?php
/*
Plugin Name: Shifter - Unrecommended Plugins
Plugin URI: https://github.com/getshifter/shifter-unrecommended-plugins
Description: Shifter unrecommended plugins
Version: 0.0.1
Author: Shifter Team
Author URI: https://getshifter.io
License: GPLv2 or later
*/

if (!defined('ABSPATH')) {
    exit; // don't access directly
};

if (is_admin()) {
    $unrecommended = ShifterUnrecommendedPlugins::get_instance();
    add_action('admin_init', array($unrecommended, 'admin_init'));
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

    private function getUnrecommendedList()
    {
        $url = apply_filters('shifter_unrecommend_plugins_list_url', self::UNRECOMMEND_PLUGIN_LIST_URL);
        $transientKey = 'ShifterUnrecommendedPluginsList-'.$url;
        if (!($unrecommendedPlugins = get_transient($transientKey))) {
            $unrecommendedPlugins = [];
            $response = wp_remote_get($url, array('timeout' => 30));
            if (!is_wp_error($response) && $response["response"]["code"] === 200) {
                $unrecommendedPlugins = json_decode($response["body"]);
            }
            set_transient($transientKey, $unrecommendedPlugins, 24 * HOUR_IN_SECONDS);
        }
        return $unrecommendedPlugins;
    }

    private function getAllPlugins()
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH.'/wp-admin/includes/plugin.php';
        }
        return get_plugins();
    }

    public function unrecommended()
    {
        $transientKey = 'ShifterUnrecommendedPlugins';
        if (!($unrecommended = get_transient($transientKey))) {
            $unrecommended = [];
            foreach ($this->getAllPlugins() as $plugin_name => $plugin_detail) {
                foreach ($this->getUnrecommendedList() as $unrecommended_plugin) {
                    if (preg_match('#^'.$unrecommended_plugin.'/#', $plugin_name)) {
                        $unrecommended[$plugin_name] = $plugin_detail;
                        break;
                    }
                }
            }
            set_transient($transientKey, $unrecommended, 1 * MINUTE_IN_SECONDS);
        }
        return $unrecommended;
    }

    private function chkStatus()
    {
        return self::UNRECOMMEND_STATUS === $_REQUEST['plugin_status'];
    }

    public function views($status_links)
    {
        global $totals, $status, $wp_list_table;

        $current_class_string = ' class="current" aria-current="page"';
        if ($this->chkStatus()) {
            $status = self::UNRECOMMEND_STATUS;
            foreach ($status_links as $type => $text) {
                $status_links[$type] = str_replace($current_class_string, '', $text); 
            }
        }
        $type = self::UNRECOMMEND_STATUS;
        $unrecommended = $this->unrecommended();
        $count = count($unrecommended);
        $text = _n('Unrecommended <span class="count">(%s)</span>', 'Unrecommended <span class="count">(%s)</span>', $count);
        $status_links[$type] = sprintf(
            "<a href='%s'%s>%s</a>",
            add_query_arg('plugin_status', $type, 'plugins.php'),
            ( $type === $status ) ? $current_class_string : '',
            sprintf($text, number_format_i18n($count))
        );
        if ($this->chkStatus()) {
            $wp_list_table->items = $unrecommended;
        }
        return $status_links;
    }

    public function all_plugins($plugins)
    {
        global $status;
        if ($this->chkStatus()) {
            $status = self::UNRECOMMEND_STATUS;
        }
        return $plugins;
    }

    public function wp_redirect($location)
    {
        if ($this->chkStatus()) {
            if (strstr($location, 'plugins.php') && strstr($location, 'plugin_status=all')) {
                $location = str_replace('plugin_status=all', 'plugin_status='.self::UNRECOMMEND_STATUS, $location);
            }
        }
        return $location;
    }

    public function admin_init()
    {
        $unrecommended = $this->unrecommended();
        if (count($unrecommended) > 0) {
            add_filter('all_plugins', array($this,'all_plugins'));
            add_filter('views_plugins', array($this, 'views'));
            add_filter('wp_redirect', array($this, 'wp_redirect'));
        }
    }
}