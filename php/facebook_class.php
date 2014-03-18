<?php

if (!class_exists("FBI_Facebook_News")) {

    class FBI_Facebook_News {

        private $config;
        public $wpdb;

        public function __construct() {
            global $wpdb;
            $this->wpdb = $wpdb;
            $this->config = array(
                'fb_app_id' => get_option('fbi_app_id'),
                'fb_app_secret' => get_option('fbi_app_secret'),
                'fb_group_id' => get_option('fbi_group_id'),
                'fbi_id_user' => get_option('fbi_id_user'),
                'fbi_update_interval' => get_option('fbi_update_interval'),
                'fbi_category_id' => get_option('fbi_category_id'),
                'fbi_last_update' => get_option('fbi_last_update'),
            );
            if ((empty($this->config['fb_app_id']) || empty($this->config['fb_app_secret']) || empty($this->config['fb_group_id'])) && is_admin()) {
                wp_die('Please fill in FB App ID, FB App Secret and Group ID');
            }
        }

        public function import_news() {
            if (!empty($this->config)) {
                $this->_retrieve_fb_news();
            }
        }

        private function _retrieve_fb_news() {
            $lastModified = $this->config['fbi_last_update'];
            $forced = (isset($_GET['force_import']) && $_GET['force_import']) ? 1 : 0;
            if ($lastModified < date('Y-m-d H:i:s', strtotime('now -' . $this->config['fbi_update_interval'])) || $forced) {
                // Request to facebook to obtain an access token.
                $access_url = 'https://graph.facebook.com/oauth/access_token?client_id=' . $this->config['fb_app_id'] . '&client_secret=' . $this->config['fb_app_secret'] . '&grant_type=client_credentials&redirect_url=' . site_url();
                $access_str = $this->_fetch_contents($access_url);
                parse_str($access_str);
                // Request the public posts.
                $json_url = 'https://graph.facebook.com/' . $this->config['fb_group_id'] . '/feed?access_token=' . $access_token;
                $json_str = $this->_fetch_contents($json_url);
                // Set news data
                $raw_data = json_decode($json_str);
                $this->_set_posts_data($raw_data);
                // Update last import date
                update_option('fbi_last_update', date('Y-m-d H:i:s'));
            }
        }

        private function _set_posts_data($raw_data) {
            foreach ($raw_data->data AS $v) {
                if (!empty($v->message) && $v->from->id == $this->config['fb_group_id']) {
                    $this->_set_one_news($v);
                }
            }
        }

        private function _set_one_news($news) {
            $message = $this->_set_news_contents($news->message);
            $news->title = $message['title'];
            $news->content = $message['content'];
            $news->id = str_replace($this->config['fb_group_id'] . '_', '', $news->id);
            if (!empty($news->source)) {
                $news->message .= '<br /><br /><a href="' . $news->link . '" target="_blank" class="videoFB"><img src="' . $news->picture . '" alt="" /><i></i></a>';
            }

            $post = array(
                'post_author' => $this->config['fbi_id_user'],
                'post_content' => $news->message,
                'post_category' => array($this->config['fbi_category_id']),
                'post_date' => date('Y-m-d H:i:s', strtotime($news->created_time)),
                'post_status' => 'publish',
                'post_title' => $message['title'],
                'post_type' => 'post',
            );
            $sql = "SELECT id_wp FROM " . $this->wpdb->prefix . "fb_to_wp WHERE id_fb = %s";
            $wp_id = $this->wpdb->get_row($this->wpdb->prepare($sql, $news->id));
            if (!empty($wp_id) && !empty($wp_id->id_wp)) {
                $post['ID'] = $wp_id->id_wp;
            }

            $id_wp = wp_insert_post($post);
            if (!empty($id_wp)) {
                // Featured thumbnail
                if (!empty($news->picture)) {
                    $news->picture = preg_replace('/(_s)(\.[a-z]{3,4})$/i', '_n$2', $news->picture);
                    if (!get_the_post_thumbnail($id_wp)) {
                        $this->_fetch_picture($news->picture, $id_wp);
                    }
                    $this->_update_post_meta($id_wp, 'featured-image', $news->picture);
                    $this->_update_post_meta($id_wp, 'fb_status_type', $news->status_type);
                    $this->_update_post_meta($id_wp, 'fb_type', $news->type);
                    $this->_update_post_meta($id_wp, 'fb_link', $news->link);
                    $this->_update_post_meta($id_wp, 'fb_source', $news->source);
                    $this->_update_post_meta($id_wp, 'fb_name', $news->name);
                    $this->_update_post_meta($id_wp, 'fb_caption', $news->caption);
                    $this->_update_post_meta($id_wp, 'fb_description', $news->description);
                }
                // relations
                $sql = "INSERT IGNORE " . $this->wpdb->prefix . "fb_to_wp SET id_fb = %s, id_wp = %d";
                $this->wpdb->query($this->wpdb->prepare($sql, $news->id, $id_wp));
            }
        }

        private function _fetch_picture($url, $post_id) {
            if (!function_exists('media_handle_upload')) {
                require_once(ABSPATH . "wp-admin" . '/includes/image.php');
                require_once(ABSPATH . "wp-admin" . '/includes/file.php');
                require_once(ABSPATH . "wp-admin" . '/includes/media.php');
            }
            set_time_limit(300);
            // Download file to temp location
            $tmp = download_url($url);
            // Set variables for storage
            // fix file filename for query strings
            preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $url, $matches);
            $file_array['name'] = basename($matches[0]);
            $file_array['tmp_name'] = $tmp;
            // If error storing temporarily, unlink
            if (is_wp_error($tmp)) {
                @unlink($file_array['tmp_name']);
                $file_array['tmp_name'] = '';
            }
            // do the validation and storage stuff
            $thumbid = media_handle_sideload($file_array, $post_id);
            // If error storing permanently, unlink
            if (is_wp_error($thumbid)) {
                @unlink($file_array['tmp_name']);
                return $thumbid;
            }
            set_post_thumbnail($post_id, $thumbid);
        }

        /**
         * Updates post meta for a post. It also automatically deletes or adds the value to field_name if specified
         *
         * @access     protected
         * @param      integer     The post ID for the post we're updating
         * @param      string      The field we're updating/adding/deleting
         * @param      string      [Optional] The value to update/add for field_name. If left blank, data will be deleted.
         * @return     void
         */
        private function _update_post_meta($post_id, $field_name, $value = '') {
            if (empty($value) OR !$value) {
                delete_post_meta($post_id, $field_name);
            } elseif (!get_post_meta($post_id, $field_name)) {
                add_post_meta($post_id, $field_name, $value);
            } else {
                update_post_meta($post_id, $field_name, $value);
            }
        }

        private function _fetch_contents($url) {
            $response = wp_remote_get($url);
            if (is_wp_error($response) || 200 != wp_remote_retrieve_response_code($response)) {
                return false;
            }
            try {
                // get the response body
                $content = wp_remote_retrieve_body($response);
            } catch (Exception $ex) {
                $content = null;
            }
            return $content;
        }

        private function _set_news_contents($content) {
            $ret = array('title' => '', 'content' => '');
            $ret['title'] = $this->_set_news_title($content);
            $ret['content'] = $this->_set_news_content($content);
            return $ret;
        }

        private function _set_news_title($content) {
            preg_match('/^(.+)/i', $content, $matches);
            if ($matches[1] == $content) {
                preg_match('/^([^\.]+)/i', $content, $matches);
            }
            return $matches[1];
        }

        private function _set_news_content($content) {
            $titre = $this->_set_news_title($content);
            return trim(str_replace($titre, '', $content));
        }

    }

}