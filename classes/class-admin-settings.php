<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class ChatbotSupportSettings {
	
	function __construct()
	{
		add_action('admin_menu', array(&$this, 'plugin_settings_page')); 
	}
	
	function plugin_settings_page()
	{
		add_options_page( 
			__( 'Chatbot Support AI Options', 'chatbot-support-ai' ),
			__( 'Chatbot Support AI', 'chatbot-support-ai' ),
			'manage_options',
			'chatbot-support-ai-settings',
			array(&$this, 'settings')
		);
	}

	function settings()
	{
		
		?>
<div id="chatbot-support-settings-wrap" class="wrap">
<h2>Chatbot Support AI - Settings</h2>
<h2 class="nav-tab-wrapper um-nav-tab-wrapper">
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=chatbot-support-ai-settings') ) ?>" class="nav-tab nav-tab-active">General</a>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=chatbot-support-ai-settings&tab=chatgpt-settings') ) ?>" class="nav-tab nav-tab-active">ChatGPT Settings</a>
</h2>
	
		<?php
		if(!isset($_GET['tab'])) $this->general_tab_settings();
		else
		{
			switch($_GET['tab'])
			{
				case 'chatgpt-settings':
					$this->chatgpt_tab_settings();
				break;			
			}
		}
	}
	
	function general_tab_settings()
	{
		$msg = '';
		$settings = get_option('chatbot_support_ai_general_settings');
		
		if(isset($_POST['save_general_settings']))
		{
			$settings = array(
					'openai_api_key' => isset($_POST['openai_api_key'])? $_POST['openai_api_key']:'',
					'chatbot_type' => $_POST['chatbot_type'],
					'custom_product_description' => $_POST['custom_product_description'],
					'whole_site_enabled' => isset($_POST['whole_site_enabled'])? $_POST['whole_site_enabled']:'',
					'logged_in_only' => isset($_POST['logged_in_only'])? $_POST['logged_in_only']:'',
					'enable_cookie' => isset($_POST['enable_cookie'])? $_POST['enable_cookie']:'',
					'cookie_lifespan' => isset($_POST['cookie_lifespan'])? $_POST['cookie_lifespan']:'1',
					'starting_message' => isset($_POST['starting_message'])? $_POST['starting_message']:'Hello! How can I help you?',
			);
			update_option('chatbot_support_ai_general_settings', $settings);
			$msg = ' &#10004; Saved Successfuly!';
		}
		?>
				<form method="post" action="" name="chatbot-support-settings-form" id="chatbot-support-settings-form">
					<input type="hidden" name="save_general_settings" />
			<table  class="form-table" >
			<tbody>

				<tr >
					<th><label >OpenAI Secret API Key :</label> <span class="dashicons dashicons-editor-help" title="This is required to use ChatGPT"></span></th>

					<td><input type="text" size="50" name="openai_api_key" id="openai_api_key" value="<?php echo esc_html($settings['openai_api_key'])?>" />  ::You can find your Secret API key in your OpenAI <a href="https://beta.openai.com/account/api-keys" target="_blank">User settings</a></td>
				</tr>
				<tr >
					<th><label >Chatbot Type : </label> <span class="dashicons dashicons-editor-help" title="Select which type of Chatbot you want to use."></span></th>

					<td> <fieldset>
	<input type="radio" name="chatbot_type" value="1" <?php  if($settings['chatbot_type'] == 1){?> checked<?php }?>>General Assistant<br><br>
	<?php if ( class_exists( 'WooCommerce' ) ) { ?> <input type="radio" name="chatbot_type" value="3" <?php  if($settings['chatbot_type'] == 3){?> checked<?php }?>>Woocommerce Products Assistant<br><br> <?php } ?>
	<input type="radio" name="chatbot_type" value="2" <?php  if($settings['chatbot_type'] == 2){?> checked<?php }?>>Custom Product Assistant<br><br>
				<label  for="custom_product_description" >Custom Product Description (Less than < 4000 words)</label><br>
	<textarea  id="custom_product_description" style="width:100%" name="custom_product_description"  rows="6" ><?php echo esc_textarea($settings['custom_product_description'])?></textarea>
</fieldset></td>
				</tr>
				<tr >
					<th><label>Enable Chatbot :</label> <span class="dashicons dashicons-editor-help" title="Enable the chatbot in whole site"></span></th>

					<td><input type="checkbox" name="whole_site_enabled" id="whole_site_enabled" value="yes" <?php  if($settings['whole_site_enabled']){?> checked<?php }?> /></td>
				</tr>
				<tr >
					<th><label>For Logged in User Only :</label> <span class="dashicons dashicons-editor-help" title="Enable the chatbot for logged in users only"></span></th>

					<td><input type="checkbox" name="logged_in_only" id="logged_in_only" value="yes" <?php  if($settings['logged_in_only']){?> checked<?php }?>/></td>
				</tr>
				
				<tr >
					<th><label>Enable Cookie :</label> <span class="dashicons dashicons-editor-help" title="Enable this to carry the chat throughout the site."></span></th>

					<td><input type="checkbox" name="enable_cookie" id="enable_cookie" value="yes" <?php  if($settings['enable_cookie']){?> checked<?php }?>/>  :: Enable a cookie policy and GDPR compliance for this</td>
				</tr>
				<tr >
					<th><label >Cookie Lifespan (n hour) :</label> <span class="dashicons dashicons-editor-help" title="How long cookie should last"></span></th>

					<td><input type="text" size="50" name="cookie_lifespan" id="cookie_lifespan" value="<?php echo esc_html($settings['cookie_lifespan'])?>" /></td>
				</tr>
				<tr >
					<th><label >Starting Message :</label> <span class="dashicons dashicons-editor-help" title="How the bot starts the conversation"></span></th>

					<td><input type="text" size="50" name="starting_message" id="starting_message" value="<?php echo esc_html($settings['starting_message'])?>" /></td>
				</tr>
			</tbody>
			</table>

				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes" /> <?php echo esc_html($msg);?>

				</p>
			</form>
	<?php
		
	}
	
	function chatgpt_tab_settings()
	{
		$msg = '';
		$settings = get_option('chatbot_support_ai_chatgpt_settings');
		
		if(isset($_POST['save_chatgpt_settings']))
		{
			$settings = array(
					'openai_model_name' => isset($_POST['openai_model_name'])? $_POST['openai_model_name']:'gpt-3.5-turbo',
					'max_tokens' => isset($_POST['max_tokens'])? $_POST['max_tokens']:'2000',
					'temperature' => isset($_POST['temperature'])? $_POST['temperature']:'0.7',
					'top_p' => isset($_POST['top_p'])? $_POST['top_p']:'1',
					'frequency_penalty' => isset($_POST['frequency_penalty'])? $_POST['frequency_penalty']:'0',
					'presence_penalty' => isset($_POST['presence_penalty'])? $_POST['presence_penalty']:'0',
			);
			update_option('chatbot_support_ai_chatgpt_settings', $settings);
			$msg = ' &#10004; Saved Successfuly!';
		}
		?>
				<form method="post" action="" name="chatbot-support-settings-form" id="chatbot-support-settings-form">
		<input type="hidden" name="save_chatgpt_settings" />
			<table  class="form-table" >
			<tbody>

				<tr >
					<th><label>OpenAI Model :</label> <span class="dashicons dashicons-editor-help" title="OpenAI model name to use as chatbot"></span></th>

					<td><input type="text" size="50" name="openai_model_name" id="openai_model_name" value="<?php echo esc_html($settings['openai_model_name'])?>" /></td>
				</tr>
				
				<tr >
					<th><label>Max Tokens :</label> <span class="dashicons dashicons-editor-help" title="max_tokens"></span></th>

					<td><input type="text" name="max_tokens" id="max_tokens" value="<?php echo esc_html($settings['max_tokens'])?>"/></td>
				</tr>
				<tr >
					<th><label>Temperature :</label> <span class="dashicons dashicons-editor-help" title="temperature"></span></th>

					<td><input type="text" name="temperature" id="temperature" value="<?php echo esc_html($settings['temperature'])?>"/></td>
				</tr>
				<tr >
					<th><label>Top_p :</label> <span class="dashicons dashicons-editor-help" title="top_p"></span></th>

					<td><input type="text" name="top_p" id="top_p" value="<?php echo esc_html($settings['top_p'])?>"/></td>
				</tr>
				<tr >
					<th><label>Frequency_penalty :</label> <span class="dashicons dashicons-editor-help" title="frequency_penalty"></span></th>

					<td><input type="text" name="frequency_penalty" id="frequency_penalty" value="<?php echo esc_html($settings['frequency_penalty'])?>"/></td>
				</tr>
				<tr >
					<th><label>Presence_penalty :</label> <span class="dashicons dashicons-editor-help" title="presence_penalty"></span></th>

					<td><input type="text" name="presence_penalty" id="presence_penalty" value="<?php echo esc_html($settings['presence_penalty'])?>"/></td>
				</tr>
			</tbody>
			</table>




				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes" />
											<input type="hidden" name="__umnonce" value="2cd7b50e0d" /> <?php echo esc_html($msg);?>
				</p>
			</form>
	<?php
		
	}
		
	
}

