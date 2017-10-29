//(function() {
jQuery(document).ready(function ($) {


    tinymce.create('tinymce.plugins.wp_braintree', {
        init: function (ed, url) {
            ed.addButton('wp_braintree', {
                title: ed.getLang('wp_braintree.title', 'Insert Braintree Button shortcode'),
                image: url + '/images/wp_braintree.png',
                onclick: function () {

                    var width = $(window).width(), H = $(window).height(), W = (720 < width) ? 720 : width;
                    W = W - 80;
                    H = H - 84;
                    tb_show(ed.getLang('wp_braintree.shortcode', 'Braintree Button Shortcode Inserter'), '#TB_inline?width=' + W + '&height=' + H + '&inlineId=wp_braintree_shortcode_form');
                }
            });
        },
        createControl: function (n, cm) {
            return null;
        },
        getInfo: function () {
            return {
                longname: ed.getLang('wp_braintree.title'),
                author: 'Josh Lobe',
                authorurl: 'http://www.example.com',
                infourl: 'http://example.com',
                version: "1.0"
            };
        }
    });
    tinymce.PluginManager.add('wp_braintree', tinymce.plugins.wp_braintree);

    // Setup form to be displayed when button is clicked
    var form = jQuery('<div id="wp_braintree_shortcode_form">\
    <style>\
    table#wp_braintree-table sup {color:red;}\
    </style>\
        <table id="wp_braintree-table" class="form-table">\
		<tr>\
			<th><label for="wp_braintree-item_name">Item Name <sup>*</sup></label></th>\
			<td><input type="text" id="wp_braintree-item_name" name="item_name" value="" /><br />\
			<small>Specify the Item Name.</small></td>\
		</tr>\
		<tr>\
			<th><label for="wp_braintree-item_amount">Item Amount <sup>*</sup></label></th>\
			<td><input type="text" name="item_amount" id="wp_braintree-item_amount" value="" /><br />\
			<small>Specify the Item Price.</small>\
		</tr>\
		<tr>\
			<th><label for="wp_braintree-button_text">Button Text</label></th>\
			<td><input type="text" name="button_text" id="wp_braintree-button_text" value="" /><br />\
			<small>Optional. Leave it blank to use default "Buy Now" text.</small>\
		</tr>\
		<tr>\
			<th><label for="wp_braintree-url">Product URL</label></th>\
			<td><input style="width:100%;" type="text" name="url" id="wp_braintree-url" value="" /><br />\
			<small>Optional. Leave it blank if you are not selling digital product.</small>\
		</tr>\
              	<tr>\
			<th><label for="wp_braintree-url">Show Form At Once</label></th>\
			<td><input type="checkbox" name="show_form" id="wp_braintree-show_form" value="1" /><br />\
			<small>If checked, payment form will be displayed at once, without the need to click the buy button first.</small>\
		</tr>\
        </table>\
	<p class="submit">\
		<input type="button" id="wp_braintree_shortcode_submit" class="button-primary" value="Insert Button Shortcode" name="submit" />\
	</p>\
	</div>');

    var table = form.find('table');
    form.appendTo('body').hide();

    // handles the click event of the submit button
    form.find('#wp_braintree_shortcode_submit').click(function () {

        wp_bt_name = table.find('#wp_braintree-item_name').val();
        wp_bt_amount = table.find('#wp_braintree-item_amount').val();
        if (!wp_bt_name || !wp_bt_amount) {
            alert('Please fill in required fields marked with *');
            return;
        }


        // defines the options and their default values
        var options = {
            'item_name': '',
            'item_amount': '',
            'button_text': '',
            'url': '',
            'show_form': '',
        };
        var shortcode = '[wp_braintree_button';

        for (var index in options) {
            var value = table.find('#wp_braintree-' + index).val();

            // attaches the attribute to the shortcode only if it's different from the default value
            if (value !== options[index])
                if (index === 'show_form' && !table.find('#wp_braintree-show_form').prop('checked')) {

                } else {
                    shortcode += ' ' + index + '="' + value + '"';
                }
        }

        shortcode += ']';

        // inserts the shortcode into the active editor
        tinyMCE.activeEditor.execCommand('mceInsertContent', 0, shortcode);

        // Clear input fields
        table.find('input#wp_braintree-item_name').val('');
        table.find('#wp_braintree-item_amount').val('');
        table.find('#wp_braintree-button_text').val('');
        table.find('#wp_braintree-url').val('');
        table.find('#wp_braintree-show_form').prop('checked', false);
        // closes Thickbox
        tb_remove();
    });
});
//})();