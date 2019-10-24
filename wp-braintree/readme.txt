=== WP Braintree ===
Contributors: Tips and Tricks HQ, alexanderfoxc, wptipsntricks
Donate link: https://www.tipsandtricks-hq.com/development-center
Tags: braintree, payment gateway, cart, checkout, e-commerce, store, sales, sell, accept payment, payment, card payment, braintree payments
Requires at least: 3.0
Tested up to: 5.3
Stable tag: 2.0.2
License: GPLv2 or later

Easily accept payments via Braintree payment gateway. Quick on-site checkout functionality.

== Description ==

This plugin allows you to accept payments using Braintree payment gateway on your WordPress site.

Users can easily pay with credit cards for your products or services using one-click "Buy Now" button.

You can accept credit card payment for products, services or digital downloads using this plugin.

The orders are saved in the database so it can be viewed from the admin dashboard.

This plugin also supports 3D Secure Payment option. So if you enable 3D Secure Payment in your Braintree account, this plugin will work with that option.

https://www.youtube.com/watch?v=kfzz8U8azbM

= Settings Configuration =

Once you have installed the plugin you need to provide your Braintree merchant details in the settings menu (Settings -> WP Braintree).

* Merchant ID
* Public Key
* Private Key

Now create a new post/page and insert Braintree shortcode for your product. For example:

`[wp_braintree_button item_name="Test Product" item_amount="5.00"]`

Use the following shortcode to sell a digital item/product using Braintree:

`[wp_braintree_button item_name="Test Product" item_amount="5.00" url="example.com/downloads/myproduct.zip"]`

The plugin will let the customer download the digital item after a successful payment.

You can customize the buy now button text using the "button_text" parameter in the shortcode. For example:

`[wp_braintree_button item_name="Test Product" item_amount="5.00" button_text="Buy This Item"]`

For screenshots, detailed documentation, support and updates, please visit: [WordPress Braintree plugin](https://www.tipsandtricks-hq.com/wordpress-braintree-plugin) page

== Usage ==

You need to embed the appropriate shortcode on a post/page to create Braintree Buy Now button.

Instructions for using the shortcodes are available at the following URL:
[Accept Braintree Payments Usage Instruction](https://www.tipsandtricks-hq.com/wordpress-braintree-plugin)

== Installation ==

Upload the plugin to the plugins directory via WordPress Plugin Uploader (Plugins -> Add New -> Upload -> Choose File -> Install Now) and Activate it.

== Frequently Asked Questions ==

= Can this plugin be used to create Buy Now button for Braintree payment gateway? =
Yes

= Can I accept Braintree payments using this plugin? =
Yes

= Can I process credit card payments on my site using this plugin? =
Yes

== Screenshots ==

None

== Upgrade Notice ==

None

== Changelog ==

= 2.0.2 =
* Updated Braintree PHP SDK to prevent deprecation notice when using PHP 7+.

= 2.0.1 =
* Fixed inability to proceed with payment when 3D Secure is not enabled in Braintree account.

= 2.0 =
* Added 3D Secure support.
* Credit Card payment forms are no longer unrolled for a second on page load.
* Added spinner after button click to indicate that payment is being processed.

= 1.9 =
* Customer info (Name and Email) is now saved in Braintree account.
* Special characters in item name should no longer result in "Item name could not be found" error (thanks to eaglesparis for reporting).

= 1.8 =
* Made sure the button and form are properly displayed if called using do_shortcode() (thanks to monolith920 for reporting).
* Removed confirmation popup when user clicks "Buy now" button after filling in CC details.
* Added all available parameters to shortcode inserter.
* Added "show_form" shortcode parameter to display payment form without the need to click a button first (thanks to ranakamransl for reporting).
* Added support for multiple buttons on a single page (thanks to benjino for reporting).

= 1.7 =
* Updated the settings menu slug to make it unique.

= 1.6 =
* The payments are now saved in the database so the admin can view it from the admin dashboard.
* The customer's name and email address is now collected in the checkbout form. This info is saved after the transaction and can be viewed from the admin dashboard.
* Fixed an error that was being shown in the thank you page message.

= 1.5 =
* Added a new action hook (wp_braintree_payment_completed) so custom script can be run after a transaction.

= 1.4 =
* Upgraded the Braintree library to the latest version.
* Everything should keep working the way it used to, however you should delete any cache (if you are using caching) to make sure all the new changes are loaded.

= 1.3 =
* Added better price validation and checking in the plugin.

= 1.2 =
* Added a new parameter in the shortcode so the Buy Now button text can be customized.

= 1.1 =
* Added a new feature to accommodate the selling of a digital item via this plugin. You can specify the URL of a digital item in the shortcode using the "url" parameter.

= 1.0 =
* First commit to the wordpress repository