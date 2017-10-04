jQuery(document).ready(function($) {
	
	// Hide ALL forms on page load
	$('.dialog-form').hide();
	
	
	// When a "buy now" button is clicked
	$('.submit_buy_now').click(function(e) {
		
		// If form is hidden, display form: else form is shown, so hide form
		if($(this).parent().parent().next('div.dialog-form').css('display') == 'none') {
			$(this).parent().parent().next('div.dialog-form').show();
		} else {
			$(this).parent().parent().next('div.dialog-form').hide();
		}
	});
	
	// Variables used for BrainTree API
//	var braintree = Braintree.create(wp_braintree_scripts_front_js_vars.cse_key); 
//	braintree.onSubmitEncryptForm("braintree-payment-form"); 
	
	// This is used when the page is submitted; it displays the transaction result
	$( "#dialog-message-success" ).dialog({  // If payment was success; this is the message just before the redirect url
		modal: true,
		width: 600,
		height: 300,
		buttons: {
			Ok: function() {
				$( this ).dialog( "close" );
			}
		},
		close: function (event, ui) {
			$(this).remove();
			// Redirect after alert message
			window.location = wp_braintree_scripts_front_js_vars.success_url;
		}
	});
	$( "#dialog-message-error" ).dialog({  // If payment failed; alert message with only option to go back and fix form to resubmit
		modal: true,
		width: 600,
		height: 300,
		buttons: {
			Back: function() {
				history.back();
			}
		},
		close: function (event, ui) {
				history.back();
		}
	});
	
	// Before we actually submit any form for transaction... we want to check the input fields.
	// This will help since each time the form is submitted.. all fields are cleared.
	// We could get around this using sessions.

var tokenizeSuccess=false;

if (typeof braintree !='undefined') {

braintree.client.create({
  authorization: wp_braintree_scripts_front_js_vars_bt.client_token,
}, function (err, clientInstance) {
  if (err) {
    console.error(err);
    return;
  }
  braintree.hostedFields.create({
    client: clientInstance,
    styles: {
      'input': {
        'font-size': '14px',
        'font-family': 'helvetica, tahoma, calibri, sans-serif',
        'color': '#3a3a3a',
      },
      ':focus': {
        'color': 'black'
      }
    },
    fields: {
      number: {
        selector: '#card-number',
        placeholder: '4111 1111 1111 1111'
      },
      cvv: {
        selector: '#cvv',
        placeholder: '123'
      },
      expirationMonth: {
        selector: '#expiration-month',
        placeholder: 'MM'
      },
      expirationYear: {
        selector: '#expiration-year',
        placeholder: 'YY'
      },
    }
  }, function (err, hostedFieldsInstance) {
    if (err) {
      console.error(err);
      return;
    }
    jQuery('.braintree-payment-form').submit(function (event) {
       if (!tokenizeSuccess) event.preventDefault(); else {
            tokenizeSuccess=false;
        if (!confirm(wp_braintree_scripts_front_js_vars.confirm_trans)) 
            return false; else return true;
        }
      hostedFieldsInstance.tokenize(function (err, payload) {
        if (err) {
            wpbErrorMsg='';
          if (typeof err.details != "undefined") {
                var firstInvalidField=err.details.invalidFieldKeys[0];
                switch (firstInvalidField) {
                case "number":
                    wpbErrorMsg=wp_braintree_scripts_front_js_vars.cc_no_valid;
                    break;
                case "cvv":
                    wpbErrorMsg=wp_braintree_scripts_front_js_vars.cvv_number;
                    break;
                case "expirationMonth":
                    wpbErrorMsg=wp_braintree_scripts_front_js_vars.exp_month_number;
                    break;                
                case "expirationYear":
                    wpbErrorMsg=wp_braintree_scripts_front_js_vars.exp_year_number;
                    break;
                default:
                    wpbErrorMsg=wp_braintree_scripts_front_js_vars.check_fields;
                }

          } else {
                wpbErrorMsg=wp_braintree_scripts_front_js_vars.fill_fields;
          }
          if (wpbErrorMsg!='') {
            jQuery("<div>"+wpbErrorMsg+"</div>").dialog({
                title: wp_braintree_scripts_front_js_vars.val_errors,
                modal: true,
                width: 600,
                height: 300,
                buttons: {
                    Ok: function() {
                        jQuery( this ).dialog( "close" );
                    }
                }
            });
        }            
        } else {
         jQuery('#wp-braintree-nonce').val(payload.nonce);
         tokenizeSuccess=true;
         jQuery('.braintree-payment-form').submit();
        }
      });
    });
  });
});
}

});