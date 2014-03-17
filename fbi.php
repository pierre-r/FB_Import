<?php

/*
  Plugin Name: FBI : FaceBook Import
  Description: Import your Facebook status into WP Posts
  Author: DarkStar
  Author URI: http://pierre-roels.com
  Version: 0.1
 */


// Activation
register_activation_hook(__FILE__, 'fbi_install');

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
    ini_set('display_errors', 1);
    require_once(dirname(__FILE__) . '/php/facebook_class.php');
    $fbi = new FBI_Facebook_News();
    $fbi->import_news();
}