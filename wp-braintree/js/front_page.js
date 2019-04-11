jQuery(document).ready(function ($) {

    function wp_braintree_create_instance(id) {
	if (typeof braintree != 'undefined') {

	    wp_braintree_buttons[id].handler = braintree.client.create({
		authorization: window['wp_braintree_buttons_data_' + id].client_token,
	    }, function (err, clientInstance) {
		if (err) {
		    console.error(err);
		    return;
		}

		braintree.threeDSecure.create({
		    client: clientInstance
		}, function (threeDSecureErr, threeDSecureInstance) {
		    if (threeDSecureErr) {
			console.error(err);
			return;
		    }

		    threeDSecure = threeDSecureInstance;
		});

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
			    selector: '#wp-braintree-card-number-' + id,
			    placeholder: '4111 1111 1111 1111'
			},
			cvv: {
			    selector: '#wp-braintree-cvv-' + id,
			    placeholder: '123'
			},
			expirationMonth: {
			    selector: '#wp-braintree-expiration-month-' + id,
			    placeholder: 'MM'
			},
			expirationYear: {
			    selector: '#wp-braintree-expiration-year-' + id,
			    placeholder: 'YY'
			},
		    }
		}, function (err, hostedFieldsInstance) {
		    if (err) {
			console.error(err);
			return;
		    }
		    jQuery('#braintree-payment-form-' + id).submit(function (event) {
			if (!wp_braintree_buttons[id].tokenizeSuccess)
			    event.preventDefault();
			else {
			    wp_braintree_buttons[id].tokenizeSuccess = false;
			    return true;
			}

			$('button[data-wp-braintree-button-id="' + id + '"]').hide();
			$('#wp-braintree-spinner-container').insertAfter('button[data-wp-braintree-button-id="' + id + '"]:not(.submit_buy_now)');
			$('#wp-braintree-spinner-container').show();

			hostedFieldsInstance.tokenize(function (err, payload) {
			    if (err) {
				var wpbErrorMsg = '';
				if (typeof err.details != "undefined") {
				    var firstInvalidField = err.details.invalidFieldKeys[0];
				    switch (firstInvalidField) {
					case "number":
					    wpbErrorMsg = wp_braintree_scripts_front_js_vars.cc_no_valid;
					    break;
					case "cvv":
					    wpbErrorMsg = wp_braintree_scripts_front_js_vars.cvv_number;
					    break;
					case "expirationMonth":
					    wpbErrorMsg = wp_braintree_scripts_front_js_vars.exp_month_number;
					    break;
					case "expirationYear":
					    wpbErrorMsg = wp_braintree_scripts_front_js_vars.exp_year_number;
					    break;
					default:
					    wpbErrorMsg = wp_braintree_scripts_front_js_vars.check_fields;
				    }

				} else {
				    wpbErrorMsg = wp_braintree_scripts_front_js_vars.fill_fields;
				}
				if (wpbErrorMsg !== '') {
				    jQuery("<div>" + wpbErrorMsg + "</div>").dialog({
					title: wp_braintree_scripts_front_js_vars.val_errors,
					width: 'auto',
					maxWidth: 600,
					height: 'auto',
					modal: true,
					fluid: true,
					buttons: {
					    Ok: function () {
						jQuery(this).dialog("close");
					    }
					}
				    });
				    $('#wp-braintree-spinner-container').hide();
				    $('button[data-wp-braintree-button-id="' + id + '"]:not(.submit_buy_now)').show();
				}
			    } else {
				jQuery('#wp-braintree-nonce-' + id).val(payload.nonce);
				if (typeof threeDSecure !== "undefined") {
				    console.log("Starting 3DS verify...");
				    var amount = $('form#braintree-payment-form-' + id).find('input[name="item_amount"]').val();
				    var bt3DSModalContent = document.getElementById('wp-braintree-3ds-modal-content');
				    threeDSecure.verifyCard({
					amount: amount,
					nonce: payload.nonce,
					addFrame: function (err, iframe) {
					    console.log('Adding 3DS frame');
					    bt3DSModalContent.appendChild(iframe);
					    $('div.wp-braintree-3ds-modal-container').fadeIn();
					},
					removeFrame: function () {
					    console.log('Removing 3DS frame');
					    $('div.wp-braintree-3ds-modal-container').fadeOut();
					}
				    }, function (err, response) {
					if (err) {
					    console.error(err);
					    $('#wp-braintree-spinner-container').hide();
					    $('button[data-wp-braintree-button-id="' + id + '"]:not(.submit_buy_now)').show();
					    alert(wp_braintree_scripts_front_js_vars.error_occurred + ' ' + err);
					    return;
					} else {
//					console.log(response);
					    jQuery('#wp-braintree-nonce-' + id).val(response.nonce);
					}
					console.log('3DS check done');
					wp_braintree_buttons[id].tokenizeSuccess = true;
					jQuery('button[data-wp-braintree-button-id="' + id + '"]').prop('disabled', true);
					jQuery('#braintree-payment-form-' + id).submit();
				    });
				} else {
				    console.log('3DS not available');
				    wp_braintree_buttons[id].tokenizeSuccess = true;
				    jQuery('button[data-wp-braintree-button-id="' + id + '"]').prop('disabled', true);
				    jQuery('#braintree-payment-form-' + id).submit();
				}
			    }
			});
		    });
		});
	    });
	}
    }

    $('.wp-braintree-3ds-modal-close-btn').click(function () {
	$('div.wp-braintree-3ds-modal-container').fadeOut();
	$('#wp-braintree-spinner-container').hide();
	$('button[data-wp-braintree-button-id]:not(.submit_buy_now)').show();
    });

    // When a "buy now" button is clicked
    $('button.submit_buy_now').click(function (e) {

	e.preventDefault();
	id = $(this).attr('data-wp-braintree-button-id');
	//wp_braintree_create_instance(id);
	$('.dialog-form[data-wp-braintree-button-id="' + id + '"]').slideDown();
	$(this).hide();
    });

    // Variables used for BrainTree API
//	var braintree = Braintree.create(wp_braintree_scripts_front_js_vars.cse_key); 
//	braintree.onSubmitEncryptForm("braintree-payment-form"); 

    // This is used when the page is submitted; it displays the transaction result
    if (typeof $("#dialog-message-success").dialog === 'function') {
	$("#dialog-message-success").dialog({// If payment was success; this is the message just before the redirect url
	    width: 'auto',
	    maxWidth: 600,
	    height: 'auto',
	    modal: true,
	    fluid: true,
	    buttons: {
		Ok: function () {
		    $(this).dialog("close");
		}
	    },
	    close: function (event, ui) {
		$(this).remove();
		// Redirect after alert message
		window.location = wp_braintree_scripts_front_js_vars.success_url;
	    }
	});
    }
    if (typeof $("#dialog-message-error").dialog === 'function') {
	$("#dialog-message-error").dialog({// If payment failed; alert message with only option to go back and fix form to resubmit
	    width: 'auto',
	    maxWidth: 600,
	    height: 'auto',
	    modal: true,
	    fluid: true,
	    buttons: {
		Back: function () {
		    history.back();
		}
	    },
	    close: function (event, ui) {
		history.back();
	    }
	});
    }
    // Before we actually submit any form for transaction... we want to check the input fields.
    // This will help since each time the form is submitted.. all fields are cleared.
    // We could get around this using sessions.

    var wp_braintree_buttons = [];

    $('form[data-wp-braintree-button-id]').each(function (id) {
	wp_braintree_buttons[id] = [];
	wp_braintree_buttons[id].tokenizeSuccess = false;
	wp_braintree_create_instance(id);
    });

});