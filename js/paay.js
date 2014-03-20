// PAAY APP
(function(){
    var api = {
        paay_handler_action: "/?page=paay_handler",

        callbacks:{},
        send: function(url,callback) {
            var request_time = new Date();
            var request_name = request_time.getTime();

            url += '&cb_name='+request_name;

            this.callbacks[request_name] = callback;

            var jsonp_script = document.createElement('script');
            jsonp_script.setAttribute('type','text/javascript');
            jsonp_script.setAttribute('src',url);
            jsonp_script.setAttribute('id','request_'+request_name);

            document.getElementsByTagName('head')[0].appendChild(jsonp_script);
        },
        handle: function(name, json_response) {
            var head_tag = document.getElementsByTagName('head')[0];
            var request_script = document.getElementById('request_'+name);

            // remove script tag associated with this request
            head_tag.removeChild(request_script);

            if (typeof this.callbacks[name] == 'function') {
                this.callbacks[name](json_response);
            }
        }

    };

    var gui = {
        init: function() {
            this.handle_polling_reply_timeout = false;
            this.plug = document.getElementById('paay_box');
            this.overlay = document.getElementById('paay_overlay');;

            if (this.plug) {
                this.paay_button = document.getElementById('paay_button');
                this.phone_number = document.getElementById('paay_phone');

                this.progress_bar = document.getElementById('paay_progress');
                this.progress_text = document.getElementById('paay_processing_status');
                this.status_text = document.getElementById('paay_status_text');
                this.overlay_close_button = document.getElementById('paay_overlay_close_button');
                this.overlay_cancel_button = document.getElementById('paay_cancel_button');
                this.overlay_resend_button = document.getElementById('paay_resend_button');
                this.overlay_help_button = document.getElementById('paay_help_button');
            } else {
                return false;
            }
            return true;
        },
        overlay_sending: function() {
            this.progress_bar.style.width = "15%";
            this.progress_text.innerHTML = 'Sending confirmation.';
            this.status_text.innerHTML = 'We are now sending you your confirmation request.';
        },
        overlay_waiting: function() {
            this.progress_bar.style.width = "50%";
            this.progress_text.innerHTML = 'Awaiting approval.';
            this.status_text.innerHTML = 'Please check your phone now to approve this payment.';
        },
        overlay_approved: function() {
            this.progress_bar.style.width = "100%";
            this.progress_text.innerHTML = 'Transaction approved.';
            this.status_text.innerHTML = 'Approved!<br>Thanks for using Paay.';
        },
        overlay_denied: function() {
            this.overlay.style.display="none";
        }
    };

    var events = {
        init: function() {
            // hook events to gui elems
            gui.paay_button.addEventListener('click',events.handle_paay_button_click);
            gui.overlay_close_button.addEventListener('click',events.handle_overlay_close_button_click);
            gui.overlay_cancel_button.addEventListener('click',events.handle_overlay_close_button_click);
            gui.phone_number.addEventListener('keydown',events.handle_phone_number_keydown);
        },
        handle_phone_number_keydown: function(evnt) {
            if (evnt.keyCode == 13) {
                events.handle_paay_button_click();
                evnt.preventDefault();
                return false;
            }
        },
        handle_paay_button_click: function(evnt) {
            var nums_regex = /[0-9]/gi;
            var number = gui.phone_number.value;
            var numbers = number.match(nums_regex);
            var phone_number = (numbers === null) ? false : numbers.join('');

            if (phone_number === false || phone_number.length != 10) {
                alert('Please ensure your phone number is typed correctly:\n + no leading 1\n + US numbers only');
            } else {
                gui.overlay.style.display = 'block';
                var api_url = api.paay_handler_action+'&cancel=true&';
                api_url += 'telephone='+phone_number;

                gui.overlay_sending();
                api.send(api_url,events.handle_payment_reply);
            }

            return false;
        },
        handle_overlay_close_button_click: function(evnt) {
            gui.overlay.style.display = 'none';
            window.clearTimeout(gui.handle_polling_reply_timeout);
            return false;
        },
        handle_payment_reply: function(json_data) {
            if( json_data.response.message == "Success")
            {
                console.log(json_data);
                var api_url = api.paay_handler_action+'&order_id='+json_data.response.order_id;

                gui.overlay_waiting();
                api.send(api_url,events.handle_polling_reply);
            }
            else
            {
                alert("Server error: "+ json_data.response.data);
                gui.overlay_denied();
            }
        },
        handle_polling_reply: function(json_data) {
            if( json_data.response.data.Transaction!= undefined )
            {
                switch(json_data.response.data.Transaction.state) {
                    case 'pending':
                        // check again soon
                        var api_url = api.paay_handler_action+'?order_id='+json_data.response.order_id;

                        gui.handle_polling_reply_timeout = window.setTimeout(function(){
                            api.send(api_url,events.handle_polling_reply);
                        },3000);
                        break;

                    case 'user_declined':
                        alert("User declined");
                        gui.overlay_denied();
                        break;

                    case 'approved':
                        gui.overlay_approved();
                        gui.handle_polling_reply_timeout = window.setTimeout(function(){
                            console.log("Redirecting to " + json_data.response.data.Transaction.return_url);
                            top.location.href = json_data.response.data.Transaction.return_url;
                        },3000);
                        break;
                }
            }
            else
            {
                alert("Server error: "+ json_data.response.data);
                gui.overlay_denied();
            }
        }
    };

    var public = {
        init: function() {
            // global init
            if (gui.init()) {
                events.init();
            }
        },
        gui: gui,
        api: api,
        events: events,
        handle_callback: function(name,json_response) {
            api.handle(name,json_response);
        }
    };

    // global namespace
    paay_app = public;

    // init on pageload
    window.addEventListener('load', function(evnt){
        paay_app.init();
    });
})();
