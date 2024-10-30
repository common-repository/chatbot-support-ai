// Collapsible
var loader = '<span class="loader"><span class="loader__dot"></span><span class="loader__dot"></span><span class="loader__dot"></span></span>';
var secret_key;
if(typeof cs_ajaxvar.chatbot_support_ai_secret_key == 'undefined') // Cookies are disabled
{
	secret_key = Math.random().toString(16).slice(2);
}
else
{
	secret_key = cs_ajaxvar.chatbot_support_ai_secret_key; //We will remember this chat by this
}

var coll = document.getElementsByClassName("collapsible");

for (let i = 0; i < coll.length; i++) {
    coll[i].addEventListener("click", function () {
        this.classList.toggle("active");

        var content = this.nextElementSibling;

        if (content.style.maxHeight) {
            content.style.maxHeight = null;
        } else {
            content.style.maxHeight = content.scrollHeight + "px";
        }

    });
}

function getTime() {
	var date = new Date();
	var month = date.toLocaleString("default", { month: "short" });
	var day = date.toLocaleString("default", { day: "2-digit" });
	var time =  date.toLocaleString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true });
	return day+" "+month+", "+time;
}

// Gets the first message
function firstBotMessage() {
    var firstMessage = cs_ajaxvar.csa_starting_message;
    document.getElementById("botStarterMessage").innerHTML = '<p class="botText"><span>' + firstMessage + '</span></p><p class="bot_msg_time">' + getTime() + '</p>';
    document.getElementById("userInput").scrollIntoView(false);
}

firstBotMessage();


//Gets the text text from the input box and processes it
function getResponse() {
    let userText = jQuery("#textInput").val();

    if (userText == "") {
        userText = "I love Code Palace!";
    }

    let userHtml = '<p class="userText"><span>' + userText + '</span></p><p class="user_msg_time">' + getTime() + '</p>';

    jQuery("#textInput").val("");
    jQuery("#chatbox").append(userHtml);
    document.getElementById("chat-bar-bottom").scrollIntoView(true);

    setTimeout(() => {
        getBotResponse(userText);
    }, 1000)

}

//Gets Response from Chat GPT
function getBotResponse(input) {
	var botHtml;
	botHtml = loader;
	jQuery("#chatbox").append(botHtml);
	document.getElementById("chat-bar-bottom").scrollIntoView(true);
	var result = '';
	  jQuery.ajax({
         type : "post",
         url : cs_ajaxvar.ajaxurl,
         data : {
			 	action: "get_ChatGPT_response",
			 	nonce: cs_ajaxvar.nonce,   // pass the nonce here
				prompt: input,
			 	secret_key: secret_key
		 		},
         success: function(response) {
			 jQuery("span.loader").hide();
			 if(response == '') botHtml = '<p class="botText"><span>Sorry! Could you please provide bit more information?</span></p><p class="bot_msg_time">' + getTime() + '</p>';
			 else{
				 botHtml = '<p class="botText"><span>' + response + '</span></p><p class="bot_msg_time">' + getTime() + '</p>';
			}
				 jQuery("#chatbox").append(botHtml);
				 document.getElementById("chat-bar-bottom").scrollIntoView(true);
         },
		 error: function(response) {
               console.log('chatbot-support-ajax-error');
         }
      });  

}

// Handles sending text via button clicks
function buttonSendText(sampleText) {
    let userHtml = '<p class="userText"><span>' + sampleText + '</span></p>';

    jQuery("#textInput").val("");
    jQuery("#chatbox").append(userHtml);
    document.getElementById("chat-bar-bottom").scrollIntoView(true);

}

function sendButton() {
    getResponse();
}


// Press enter to send a message
jQuery("#textInput").keypress(function (e) {
    if (e.which == 13) {
        getResponse();
    }
});