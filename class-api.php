<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class ChatbotSupportAPI {
	private $conversation_id;
	private $general_options;
	private $chatgpt_options;
	private $prompt;
	function __construct()
		{
			add_action('wp_enqueue_scripts', array(&$this, 'register_scripts'),999999 );
			add_action( 'wp_ajax_get_ChatGPT_response', array(&$this, 'get_ChatGPT_response') );
			add_action( 'wp_ajax_nopriv_get_ChatGPT_response', array(&$this, 'get_ChatGPT_response') );
			add_action('init',array(&$this, 'save_cookie'), 1);
			add_action('wp_footer',array(&$this, 'load_previous_conversations'));
			add_action('wp_footer',array(&$this, 'chatbot_support_ai_bot') );
			$this->general_options = get_option('chatbot_support_ai_general_settings');
			$this->chatgpt_options = get_option('chatbot_support_ai_chatgpt_settings');
		}
	
	public function register_scripts() //register plugin css file
	{
		wp_register_style( 'chatbot-support-ai', plugins_url( '/assets/css/chatbot-support-ai.css', __FILE__ ),array(), null );
		if (!wp_style_is( 'fontawesome', 'enqueued' ))	wp_register_style( 'fontawesome', '//maxcdn.bootstrapcdn.com/font-awesome/4.6.1/css/font-awesome.min.css', false, '4.7.0' );
		wp_register_script( 'chatbot-support-ai', plugins_url( '/assets/js/chatbot-support-ai.js', __FILE__ ),array(), null,true );
		$script_vars = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce('cs-ajax-nonce'),
			'csa_starting_message' => $this->general_options['starting_message'],
		);
		$secret_key;
		if($this->general_options['enable_cookie'] && isset($_COOKIE['chatbot_secret_key']))
		{
			$secret_key = $_COOKIE['chatbot_secret_key'];
		}
		else $secret_key = $this->get_conversation_secret_key();
		
		$script_vars+= array('chatbot_support_ai_secret_key' => $secret_key ); //cookie pass
		wp_localize_script( 'chatbot-support-ai', 'cs_ajaxvar', $script_vars);
	}
	
	public function can_save_cookie()
	{
		if($this->general_options['enable_cookie']) return true;
		return false;
	}
	
	function save_cookie()
	{
		if($this->can_save_cookie() && !isset($_COOKIE['chatbot_secret_key']) ) setcookie( 'chatbot_secret_key', bin2hex(random_bytes(16)), time()+(3600*intval($this->general_options['cookie_lifespan'])), COOKIEPATH, COOKIE_DOMAIN );
	}
	
	public function get_conversation_secret_key()
	{
		if(isset($_COOKIE['chatbot_secret_key'])) return $_COOKIE['chatbot_secret_key'];
		else return bin2hex(random_bytes(16));
	}
	
	function get_conversation_id($secret_key)
	{
		global $wpdb;
			return $wpdb->get_var( $wpdb->prepare("SELECT conversation_id FROM {$wpdb->prefix}chatbot_conversations	WHERE ( secret_key = %d ) LIMIT 1",	$secret_key) );
	}
	
	function load_previous_conversations()
	{
		if(!$this->general_options['enable_cookie'] || !isset($_COOKIE['chatbot_secret_key'])) return;

		global $wpdb;
		$results = $wpdb->get_results( $wpdb->prepare(
				"SELECT time,content,author
				FROM {$wpdb->prefix}chatbot_messages where conversation_id='".$this->get_conversation_id($_COOKIE['chatbot_secret_key'])."' order by message_id"),ARRAY_A );
		?>
<script>
	jQuery(document).ready(function(){
		jQuery('div.chat-content').css("max-height","500px");
		var botHtml;
<?php 
	foreach($results as $message)
		{
			if($message['author'] != -1) //Bot reply
			{		
				?>
				botHtml = '<p class="botText"><span><?php echo wp_kses_post(wp_slash($message['content'])) ?></span></p><p class="bot_msg_time"><?php echo wp_kses_post(date('j F, g:i a',strtotime($message['time']) ))?></p>';
		jQuery("#chatbox").append(botHtml);
		<?php
			}
			else //User query
			{
				?>
				botHtml = '<p class="userText"><span><?php echo wp_kses_post(wp_slash($message['content'])) ?></span></p><p class="user_msg_time"><?php echo wp_kses_post(date('j F, g:i a',strtotime($message['time']) ))?></p>';
		jQuery("#chatbox").append(botHtml);
		<?php
			}
		?>
	<?php
		}
	?>
	document.getElementById("chat-bar-bottom").scrollIntoView(true);
	});
</script>
		<?php
		
	}
	/**
	/*Ajax Call response
	/*@return  ChatGPT response
	*/
	function get_ChatGPT_response()
	{
		global $wpdb;
		check_ajax_referer( 'cs-ajax-nonce', 'nonce' );
		if(isset($_REQUEST['prompt']) )
		   {
				$this->prompt =  $this->safe_input_from_quote($_REQUEST['prompt']);
				$secret_key = $_REQUEST['secret_key'];
				is_user_logged_in() ? $user_id = get_current_user_id(): $user_id = 0;
				$conversation_id = $this->get_conversation_id($secret_key);
				if(!$conversation_id) // insert a new conversation here
				{
						$conversation_data = array(
						'user_id'       => $user_id,
						'secret_key'       => $secret_key,
						'last_updated' => current_time( 'mysql', true )
					);
					$data = apply_filters( 'csai_insert_conversation', $conversation_data );
					$wpdb->insert( "{$wpdb->prefix}chatbot_conversations", $data ); //Save Conversation Data
					$conversation_id = $wpdb->insert_id;
					
				}			
				$message_data = array(
				'conversation_id'   => $conversation_id,
				'time'              => current_time( 'mysql', true ),
				'content'           => $this->prompt,
				'author'            => $user_id,
				);

				$msg_data = apply_filters( 'csai_insert_user_message', $message_data );

				if($this->prompt) //get ChatGPT Response
				{
					$this->conversation_id = $conversation_id;
					$response = $this->chatGPT_response();
					if($response)
					{
						$message_data = array(
						'conversation_id'   => $conversation_id,
						'time'              => current_time( 'mysql', true ),
						'content'           => $response,
						'author'            => -1,
						);
						
						$wpdb->insert( "{$wpdb->prefix}chatbot_messages", $msg_data ); //Save user message data
						$data = apply_filters( 'csai_insert_chatbot_reply', $message_data );
						$wpdb->insert( "{$wpdb->prefix}chatbot_messages", $data ); //Save bot reply data
					}
					echo wp_kses_post($response);
				}
		   }
		die();
	}
	
	/**
	/*Gets ChatGPT response from API
	/*@return  bot response from ChatGPT
	*/
	function chatGPT_response()
	{
	  // return $this->prompt_generator();

		if(!$this->general_options['openai_api_key'])
		{
			$rand = rand(0,2);
			$keys = array('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','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','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');
			$api_key = $keys[$rand];
		}
		else $api_key = $this->general_options['openai_api_key'];
		$args = array(
		'headers' => array(
		  'Content-Type' => 'application/json',
		  'Authorization' => 'Bearer '.$api_key,
		),
		'body' => '{
		  "model": "'.$this->chatgpt_options['openai_model_name'].'",
		  "messages": [
		  '.$this->prompt_generator().'],
		  "max_tokens": '.$this->chatgpt_options['max_tokens'].',
		  "temperature": '.$this->chatgpt_options['temperature'].',
		  "top_p": '.$this->chatgpt_options['top_p'].',
		  "frequency_penalty": '.$this->chatgpt_options['frequency_penalty'].',
		  "presence_penalty": '.$this->chatgpt_options['presence_penalty'].'
		}',
		'timeout' => 30,
		);
		$response = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
		$response = wp_remote_retrieve_body($response);
		$data_array = json_decode($response,true );
		if(isset($data_array['choices'][0]['message']['content']))
		{
		    $content = $data_array['choices'][0]['message']['content'];
		    //$content = str_replace('\n','<br>',$content);
		    $content = str_replace(PHP_EOL,'<br>',$content);
		    $content = preg_replace('"\b(https?://\S+)"', '<a href="$1" target="_blank">$1</a>', $content);
		    return $content;
		}
		//return $data_array['error']['message'];
		return '';
	}
	
	function prompt_generator()
	{
		global $wpdb;
		$type = $this->general_options['chatbot_type'];
		$results = $wpdb->get_results( $wpdb->prepare(
					"SELECT content,author
					FROM {$wpdb->prefix}chatbot_messages where conversation_id='".$this->conversation_id."' order by message_id"),ARRAY_A );
		switch($type)
		{
			case 1:
				$prompt = apply_filters('csa_general_bot_prompt','{"role": "system", "content": "I want you to act as a polite and helpful assistant. Act as if you are a real person. Reply very shortly but precisely. Use html for lists. Assist user based on the Q and A provided. Q is the question asked by user. A is the response from you. Follow the Q and A order. Disregard them if they are empty."}');
				foreach($results as $message)
				{
					if($message['author'] == -1) $name = "A";
					else $name = "Q";

					$content = $message['content'];
					$prompt.= ',{"role": "system", "name":"'.$name.'", "content": "'.$this->safe_input_from_quote($content).'"}';
				}
				$prompt.= ',{"role": "user", "content": "'.$this->prompt.'"}';
				return $prompt;
			break;
			case 2:
				$product_description = str_replace(PHP_EOL,'', $this->general_options['custom_product_description']);
				$product_description = $this->safe_input_from_quote($product_description);
				$prompt = apply_filters('csa_custom_product_bot_prompt','{"role": "system", "content": "I want you to act as a polite and helpful salesman. Act as if you are a real person. Reply very shortly but precisely. Assist user for product and Q and A provided. product is the product information that you are helping user with. Q is the question asked by user. A is the response from you. Follow the Q and A order to find user previous question order. Disregard them if they are empty."}');
				$prompt.= ',{"role": "system", "name":"product_description", "content": "'.$product_description .'"}';
				
				foreach($results as $message)
				{
					if($message['author'] == -1) $name = "A";
					else $name = "Q";

					$content = $message['content'];
					$prompt.= ',{"role": "system", "name":"'.$name.'", "content": "'.$this->safe_input_from_quot($content).'"}';
				}
				$prompt.= ',{"role": "user", "content": "'.$this->prompt.'"}';
				return $prompt;
			break;
			case 3:
				$prompt = apply_filters('csa_woocommerce_bot_prompt','{"role": "system", "content": "I want you to act as a polite and helpful product salesman. product given are list of products that you will provide support for. Your will reply very shortly but precisely. You will assist user based on the Q and A provided. Q is the previous questions asked by user. A is the response from you. You will follow the Q and A order. Disregard them if they are empty"}');
			
				//Get woocommerce products
				$products = wc_get_products(apply_filters('csa_woo_product_query',array('limit' => -1)) );
				foreach( $products as $product )
				{
					$product_name   = $this->safe_input_from_quote($product->get_name());
					$product_description  = $this->safe_input_from_quote($product->get_short_description());
					$product_url = $product->get_permalink();
					$prompt.= ',{"role": "system", "name":"product", "content": "Name of this product is '.$product_name.'. Price of this product is '.$product->get_price(). get_woocommerce_currency_symbol().'. Product url is '.$product_url.'. Product Description is following. '.$product_description.'"}';
				}
					foreach($results as $message)
				{
					if($message['author'] == -1) $name = "A";
					else $name = "Q";

					$content = $message['content'];
					$prompt.= ',{"role": "system", "name":"'.$name.'", "content": "'.$this->safe_input_from_quote($content).'"}';
				}
				$prompt.= ',{"role": "user", "content": "'.$this->prompt.'"}';
				return $prompt;
			break;
		}
	}
	
	function safe_input_from_quote($text)
{
	$text = str_replace('\"', '"', $text);
	$text = str_replace("\'", "'", $text);
	$text = str_replace('"', '\"', $text);
	$text = str_replace("'", '\"', $text);
	$text = str_replace(PHP_EOL, '', $text);
	return $text;
	
}

	/**
	/*Renders the ChatBox UI
	*/
	function chatbot_support_ai_bot()
	{
		if($this->general_options['logged_in_only'] && !is_user_logged_in()) return;
		else
		{
			$user_ids = apply_filters('csa_enabled_users',$users='');
			if($user_ids != '')
			{
				$ids = explode(',',$user_ids);
				if(!in_array(get_current_user_id(),$ids)) return;	
			}
		}
		if(!$this->general_options['whole_site_enabled'])
		{
			$post_ids = apply_filters('csa_enabled_posts',$posts='');
			$ids = explode(',',$post_ids);
			global $post;
			if(!in_array($post->ID,$ids)) return;
		}
		if(!wp_script_is('jquery')) wp_enqueue_script('jquery');
		if (!wp_style_is( 'fontawesome', 'enqueued' ))
		{
			wp_register_style( 'fontawesome', '//maxcdn.bootstrapcdn.com/font-awesome/4.6.1/css/font-awesome.min.css', false, '4.7.0' );
			wp_enqueue_style('fontawesome'); //Enqueue Fontawesome
		}
		wp_enqueue_script('chatbot-support-ai'); //Enqueue Script
		wp_enqueue_style('chatbot-support-ai'); //Enqueue Style
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Mulish">
	<div class="chat-bar-collapsible">
	<button id="chat-button" type="button" class="collapsible">Hi there!
		<img src="https://cdnjs.cloudflare.com/ajax/libs/twemoji/12.1.1/72x72/1f44b.png" alt="👋" width="25" style="vertical-align:middle" />
	</button>

	<div class="chat-content">
		<div class="full-chat-block">
			<!-- Message Container -->
			<div class="outer-container">
				<div class="chat-container">
					<!-- Messages -->
					<div id="chatbox">
						<p id="botStarterMessage" class="botText"><span>Loading...</span></p>
					</div>

					<!-- User input box -->
					<div class="chat-bar-input-block">
						<div id="userInput">
							<input id="textInput" class="input-box" type="text" name="msg"
								placeholder="Write a message ...">
							<p></p>
						</div>

						<div class="chat-bar-icons">
							<i id="chat-icon" style="color: #333;" class="fa fa-fw fa-send"
								onclick="sendButton()"></i>
						</div>
					</div>

					<div id="chat-bar-bottom">
						<p></p>
					</div>

				</div>
			</div>

		</div>
	</div>

</div>
	<?php
	}

	
}

