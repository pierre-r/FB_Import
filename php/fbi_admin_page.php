<?php
// create custom plugin settings menu
add_action('admin_menu', 'fbi_main_menu');

function fbi_main_menu() {
    add_options_page('Facebook Import Options', 'Facebook Import', 'manage_options', 'fbi-admin.php', 'fbi_plugin_page');
}

function register_mysettings() {
    //register our settings
    register_setting('fb-import-settings', 'fbi_app_id');
    register_setting('fb-import-settings', 'fbi_app_secret');
    register_setting('fb-import-settings', 'fbi_group_id');
    register_setting('fb-import-settings', 'fbi_update_interval');
    register_setting('fb-import-settings', 'fbi_id_user');
    register_setting('fb-import-settings', 'fbi_category_id');
    register_setting('fb-import-settings', 'fbi_last_update');
}

function fbi_plugin_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Sorry, you cannot access this page.');
    }
    if ($_POST['action'] == 'fbi-update-settings') {
        update_option('fbi_app_id', trim($_POST['fbi_app_id']));
        update_option('fbi_app_secret', trim($_POST['fbi_app_secret']));
        update_option('fbi_group_id', trim($_POST['fbi_group_id']));
        update_option('fbi_update_interval', trim($_POST['fbi_update_interval']));
        update_option('fbi_id_user', trim($_POST['fbi_id_user']));
        update_option('fbi_category_id', trim($_POST['fbi_category_id']));
        update_option('fbi_last_update', trim($_POST['fbi_last_update']));
    }
    ?>
    <div class="wrap">
        <?php screen_icon(); ?>
        <h2>Settings</h2>			
        <form method="post" action="options-general.php?page=<?php echo $_GET['page']; ?>">
            <?php settings_fields('fb-import-settings'); ?>
            <?php //do_settings('fb-import-settings'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">App ID</th>
                    <td><input type="text" name="fbi_app_id" value="<?php echo get_option('fbi_app_id'); ?>" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">App Secret</th>
                    <td><input type="text" name="fbi_app_secret" value="<?php echo get_option('fbi_app_secret'); ?>" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Group ID</th>
                    <td><input type="text" name="fbi_group_id" value="<?php echo get_option('fbi_group_id'); ?>" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">ID Wordpress User</th>
                    <td><input type="text" name="fbi_id_user" value="<?php echo get_option('fbi_id_user'); ?>" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">ID Wordpress Category</th>
                    <td><input type="text" name="fbi_category_id" value="<?php echo get_option('fbi_category_id'); ?>" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Update interval<br /><small>ie: "6 days", "2 hours", etc<br />(without quotes)</small></th>
                    <td><input type="text" name="fbi_update_interval" value="<?php echo get_option('fbi_update_interval'); ?>" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">
                        Last update
                        <br /><small>reset this field if you want to force update</small>
                    </th>
                    <td><input type="text" name="fbi_last_update" value="<?php echo get_option('fbi_last_update'); ?>" /></td>
                </tr>
            </table>
            <input type="hidden" name="action" value="fbi-update-settings" />
            <?php submit_button(); ?>

        </form>
    </div>
    <?php
}
?>