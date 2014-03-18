<?php

/*
  Plugin Name: FBI : FaceBook Import
  Description: Automaticaly import your Facebook statuses into Wordpress
  Author: DarkStar
  Author URI: http://pierre-roels.com
  Version: 1.0
 */

// Activation
// Workaround to work on my localhost where plugin folder is symlink. Default: __FILE__
$plugin_file = WP_PLUGIN_DIR . '/' . basename(dirname(__FILE__)). '/' . basename(__FILE__);
register_activation_hook($plugin_file, 'fbi_install');

function fbi_install() {
    global $wpdb;
    $sql = "CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . "fb_to_wp` (
            `id_fb` bigint(20) NOT NULL,
            `id_wp` int(11) NOT NULL,
            PRIMARY KEY (`id_fb`)
          );";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Admin page
require_once(dirname(__FILE__) . '/php/fbi_admin_page.php');


// Public page
add_action('wp', 'fbi_get_facebook_news');

function fbi_get_facebook_news() {
    require_once(dirname(__FILE__) . '/php/facebook_class.php');
    $fbi = new FBI_Facebook_News();
    $fbi->import_news();
}
