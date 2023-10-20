=== Cardlink Payment Gateway ===
Contributors: cardlink
Tags: payments, payment-gateway
Requires at least: 5.8.3
Tested up to: 5.8.3
Stable tag: 5.8.3
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Cardlink Payment Gateway allows you to accept payment through various schemes such as Visa, Mastercard, Maestro, American Express, Diners, Discover cards on your website.

== Description ==

Cardlink Payment Gateway allows you to accept payment through various schemes such as Visa, Mastercard, Maestro, American Express, Diners, Discover cards on your website, with or without variable installments.
This plugin aims to offer new payment solutions to Cardlink merchants through the use of CMS plugin for their website creation and provide the possibility to add extra features without having web development knowledge. 
Merchants with e-shops (redirect cases only) will be able to integrate the Cardlink Payment Gateway to their checkout page using the CSS layout that they want. Also they could choose between redirect or iframe option for the payment enviroment. Once the payment is made, the customer returns to the online store and the order is updated.
Once you have completed the requested tests and any changes to your website, you can activate your account and start accepting payments. 

== Features ==

1.	A dropdown option for instance between Worldline, Nexi και Cardlink.
2.	Option to enable test environment. All transactions will be re-directed to the endpoint that represents the production environment by default. The endpoint will be different depending on which acquirer has been chosen from instance dropdown option.
3.	Ability to define the maximum number of installments regardless the total order amount and ability to define the ranges of the total order amounts and the maximum installment  (up to 10 conditions) for every range.
4.	Option for pre-authorization or sale transactions.
5.	Option for a user tokenization service. The card token will be stored at the merchant’s e-shop database and will be used by customers to auto-complete future payments. 
6.	Redirection option: user should have a check box to enable pop up with i-frame without redirection.
7.	A text field for providing the absolute or relative (to Cardlink Payment Gateway location on server) url of custom CSS stylesheet, to change css styles in payment page.
8.	Translation ready for Greek & English languages.

== Installation ==

If you have a copy of the plugin as a zip file, you can manually upload it and install it through the Plugins admin screen.
1. Navigate to Plugins > Add New.
2. Click the Upload Plugin button at the top of the screen.
3. Select the zip file from your local filesystem.
4. Click the Install Now button.
5. When the installation is complete, you’ll see “Plugin installed successfully.” Click the Activate Plugin button.

In rare cases, you may need to install a plugin by manually transferring the files onto the server. This is recommended only when absolutely necessary, for example when your server is not configured to allow automatic installations.
This procedure requires you to be familiar with the process of transferring files using an SFTP client. It is recommended for advanced users and developers.
Here are the detailed instructions to manually install a WordPress plugin by transferring the files onto the webserver. 

== Screenshots ==

1. The Cardlink Payment Gateway settings screen used to configure the main Cardlink gateway.
2. This is the front-end of Cardlink Payment Gateway plugin located in checkout page.

== Changelog ==

= 1.0.7 =
* Add IRIS payment method.
= 1.0.6 =
* Support Alpha bonus transactions.
= 1.0.5 =
* Bugfix: Fix compatibility issues with Payment Plugins for Stripe WooCommerce and Payment Plugins for PayPal WooCommerce plugins
= 1.0.4 =
* Bugfix: Check if WooCommerce is installed and activated before initialize plugin functionality
= 1.0.3 =
* Styling updates on iframe popup window
= 1.0.2 =
* Bugfix: Installments total number
= 1.0.1 =
* Compatibility updates
= 1.0.0 =
* Initial release
