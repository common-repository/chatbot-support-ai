<?php

if ( ! defined( 'ABSPATH' ) ) exit;


class ChatbotSupportDatabaseSetup{
	/**
	 * SQL DB setup
	 * @global \wpdb $wpdb
	 */
	public function sql_setup() {
		global $wpdb;

		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		/*
		 * Pay an attention if the user has active strict mode in the database
		 * the table cannot be created. The reason for this not supporting strict mode by WordPress by default.
		 * see ticket https://core.trac.wordpress.org/ticket/8857#comment:19
		 *
		 * For strict mode the datetime value can be in the range from 1000-01-01 00:00:00 to 9999-12-31 23:59:59
		 * ref.: https://www.mysqltutorial.org/mysql-datetime/
		 *
		 * We support wp.org logic because it's plugin for WordPress. So please temporarily disable strict-mode or create DB table
		 * manually via hosting CPanel
		 */
		$sql = "CREATE TABLE {$wpdb->prefix}chatbot_conversations (
conversation_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
user_id bigint(20) UNSIGNED DEFAULT 0 NOT NULL,
secret_key varchar(33) DEFAULT '' NOT NULL,
last_updated datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
PRIMARY KEY  (conversation_id),
KEY user_id (user_id)
) $charset_collate\n;
CREATE TABLE {$wpdb->prefix}chatbot_messages (
message_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
conversation_id bigint(20) UNSIGNED DEFAULT 0 NOT NULL,
time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
content longtext DEFAULT '' NOT NULL,
author bigint(20) SIGNED DEFAULT -1 NOT NULL,
PRIMARY KEY  (message_id),
KEY conversation_id (conversation_id),
KEY author (author)
) $charset_collate\n;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
	
	/**
	 *
	 */
	public function set_default_settings() {
		$settings = array(
					'openai_api_key' => '',
					'chatbot_type' => 1,
					'custom_product_description' => '',
					'whole_site_enabled' => 'yes',
					'logged_in_only' => '',
					'enable_cookie' => '',
					'cookie_lifespan' => '1',
					'starting_message' => 'Hello! How can I help you?',
			);
		update_option('chatbot_support_ai_general_settings', $settings);
		$settings = array(
					'openai_model_name' => 'gpt-3.5-turbo',
					'max_tokens' => '500',
					'temperature' => '0.7',
					'top_p' => '1',
					'frequency_penalty' => '0',
					'presence_penalty' => '0',
			);
		update_option('chatbot_support_ai_chatgpt_settings', $settings);
	}
	
	/**
	 *
	 */
	public function run_setup() {
		$this->single_site_activation();
		if ( is_multisite() ) {
			if ( is_plugin_active_for_network( chatbot_support_ai_plugin ) ) {
				update_network_option( get_current_network_id(), 'chatbot_support_maybe_network_wide_activation', 1 );
			}
		}
	}


	/**
	 * Maybe need multisite activation process
	 *
	 */
	function maybe_network_activation() {
		$maybe_activation = get_network_option( get_current_network_id(), 'chatbot_support_maybe_network_wide_activation' );

		if ( $maybe_activation ) {

			delete_network_option( get_current_network_id(), 'chatbot_support_maybe_network_wide_activation' );

			if ( is_plugin_active_for_network( chatbot_support_ai_plugin ) ) {
				// get all blogs
				$blogs = get_sites();
				if ( ! empty( $blogs ) ) {
					foreach( $blogs as $blog ) {
						switch_to_blog( $blog->blog_id );
						//make activation script for each sites blog
						$this->single_site_activation();
						restore_current_blog();
					}
				}
			}
		}
	}


	/**
	 * Single site plugin activation handler
	 */
	function single_site_activation() {
		$this->sql_setup();
		$this->set_default_settings();
	}
	
}

