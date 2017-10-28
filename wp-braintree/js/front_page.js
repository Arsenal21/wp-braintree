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
                        hostedFieldsInstance.tokenize(function (err, payload) {
                            if (err) {
                                wpbErrorMsg = '';
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
                                if (wpbErrorMsg != '') {
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
                                }
                            } else {
                                jQuery('#wp-braintree-nonce-' + id).val(payload.nonce);
                                wp_braintree_buttons[id].tokenizeSuccess = true;
                                jQuery('button[data-wp-braintree-button-id="' + id + '"]').prop('disabled', true);
                                jQuery('#braintree-payment-form-' + id).submit();
                            }
                        });
                    });
                });
            });
        }
    }

    // Hide ALL forms on page load
    $('.dialog-form').hide();


    // When a "buy now" button is clicked
    $('.submit_buy_now').click(function (e) {

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