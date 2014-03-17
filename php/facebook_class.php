<?php

if (!class_exists("FBI_Facebook_News")) {

    class FBI_Facebook_News {

        private $config;

        public function __construct() {
            //settings_fields('fb-import-settings');
            $this->config = array();
            $this->config = array(
                'fb_app_id' => get_option('fbi_app_id'),
                'fb_app_secret' => get_option('fbi_app_secret'),
                'fb_group_id' => get_option('fbi_group_id'),
                'fbi_id_user' => get_option('fbi_id_user'),
                'fbi_update_interval' => get_option('fbi_update_interval'),
                'fbi_category_id' => get_option('fbi_category_id'),
                'fbi_last_update' => get_option('fbi_last_update'),
            );
            if (empty($this->config['fb_app_id']) ||
                    empty($this->config['fb_app_secret']) ||
                    empty($this->config['fb_group_id'])) {
                if (is_admin()) {
                    wp_die('Please fill in FB App ID, FB App Secret and Group ID');
                }
            }
        }

        public function import_news() {
            if (!empty($this->config)) {
                $this->_retrieve_fb_news();
            }
        }

        public function get_news() {
            $this->_retrieve_fb_news();
            return json_decode(file_get_contents($this->config['news_file']));
        }

        private function _retrieve_fb_news() {
            global $wpdb;
            $lastModified = $this->config['fbi_last_update'];
            $now = date('Y-m-d H:i:s');
            $forced = (isset($_GET['force_import']) && $_GET['force_import']) ? 1 : 0;
            if ($lastModified < date('Y-m-d H:i:s', strtotime('now -' . $this->config['fbi_update_interval'])) || $forced) {
                // Request to facebook to obtain an access token.
                $access_url = 'https://graph.facebook.com/oauth/access_token?client_id=' . $this->config['fb_app_id'] . '&client_secret=' . $this->config['fb_app_secret'] . '&grant_type=client_credentials&redirect_url=' . site_url();
                $access_str = $this->_get_contents($access_url);
                parse_str($access_str);
                // Request the public posts.
                $json_url = 'https://graph.facebook.com/' . $this->config['fb_group_id'] . '/feed?access_token=' . $access_token;
                $json_str = $this->_get_contents($json_url);

                // Get only news
                $raw_data = json_decode($json_str);

                foreach ($raw_data->data AS $v) {
                    if (!empty($v->message) && $v->from->id == $this->config['fb_group_id']) {
                        $message = $this->_getNewsContents($v->message);
                        $v->title = $message['title'];
                        $v->content = $message['content'];
                        $v->id = str_replace($this->config['fb_group_id'] . '_', '', $v->id);
                        if (!empty($v->source)) {
                            $v->message .= '<br /><br /><a href="' . $v->link . '" target="_blank" class="videoFB"><img src="' . $v->picture . '" alt="" /><i></i></a>';
                        }

                        $post = array(
                            'post_author' => $this->config['fbi_id_user'],
                            'post_content' => $v->message,
                            'post_category' => array($this->config['fbi_category_id']),
                            'post_date' => date('Y-m-d H:i:s', strtotime($v->created_time)),
                            'post_status' => 'publish',
                            'post_title' => $message['title'],
                            'post_type' => 'post',
                        );
                        $sql = "SELECT id_wp FROM " . $wpdb->prefix . "fb_to_wp WHERE id_fb = %d";
                        $wp_id = $wpdb->get_row($wpdb->prepare($sql, $v->id));
                        if (!empty($wp_id) && !empty($wp_id->id_wp)) {
                            $post['ID'] = $wp_id->id_wp;
                        }

                        $id_wp = wp_insert_post($post);
                        if (!empty($id_wp)) {
                            // Featured thumbnail
                            if (!empty($v->picture)) {
                                $v->picture = preg_replace('/(_s)(\.[a-z]{3,4})$/i', '_n$2', $v->picture);
                                $this->__update_post_meta($id_wp, 'featured-image', $v->picture);
                                $this->__update_post_meta($id_wp, 'fb_status_type', $v->status_type);
                                $this->__update_post_meta($id_wp, 'fb_type', $v->type);
                                $this->__update_post_meta($id_wp, 'fb_link', $v->link);
                                $this->__update_post_meta($id_wp, 'fb_source', $v->source);
                                $this->__update_post_meta($id_wp, 'fb_name', $v->name);
                                $this->__update_post_meta($id_wp, 'fb_caption', $v->caption);
                                $this->__update_post_meta($id_wp, 'fb_description', $v->description);
                            }
                            // relations
                            $sql = "INSERT IGNORE " . $wpdb->prefix . "fb_to_wp SET id_fb = %d, id_wp = %d";
                            $wpdb->query($wpdb->prepare($sql, $v->id, $id_wp));
                        }
                    }
                }
                update_option('fbi_last_update', date('Y-m-d H:i:s'));
            }
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
        public function __update_post_meta($post_id, $field_name, $value = '') {
            if (empty($value) OR !$value) {
                delete_post_meta($post_id, $field_name);
            } elseif (!get_post_meta($post_id, $field_name)) {
                add_post_meta($post_id, $field_name, $value);
            } else {
                update_post_meta($post_id, $field_name, $value);
            }
        }

        private function _get_contents($url) {
            // create a new cURL resource
            $ch = curl_init();

            // set URL and other appropriate options
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);

            // grab URL and pass it to the browser
            $content = curl_exec($ch);

            // close cURL resource, and free up system resources
            curl_close($ch);
            return $content;
        }

        private function _getNewsContents($content) {
            $ret = array('title' => '', 'content' => '');
            $ret['title'] = $this->_get_news_title($content);
            $ret['content'] = $this->_get_news_content($content);
            return $ret;
        }

        private function _get_news_title($content) {
            preg_match('/^(.+)/i', $content, $matches);
            if ($matches[1] == $content) {
                preg_match('/^([^\.]+)/i', $content, $matches);
            }
            return $matches[1];
        }

        private function _get_news_content($content) {
            $titre = $this->_get_news_title($content);
            return trim(str_replace($titre, '', $content));
        }

    }

}