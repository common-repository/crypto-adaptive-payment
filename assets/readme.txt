=== Crypto adaptive payment ===
Contributors: joyxpertsden
Donate link: https://www.3d-prototype.co.uk/donate/
Requires at least: 3.9
Tested up to: 4.7
Stable tag: 4.3
Requires PHP: 5.2.4

This plugin takes payment, in the form of Crypto Currency, and splits it into multiple wallet ID's by 

creating individual shares in https://coinsplit.io.

This plugin currently only works with Bitcoin (BTC).


== Description ==

This plugin is an adaptive payment gateway, that allows payment to be split to multiple receiving 

parties, exclusively for Crypto Currencies, and works as such.

Before you start, please ensure the following plugins are properly configured on your website. 

1. GoUrl Bitcoin Payment Gateway & Paid Downloads & Membership (https://en-

gb.wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/)

2. Dokan Multivendor Plugin (https://wedevs.com/dokan/)

3. WooCommerce (https://woocommerce.com/)



Please note - The previous 3 plugins abide by their own Terms and Conditions, which are subject to 

change at their respective owners or admins will. Crypto Adaptive Payments take no responsibility for 

any issues, including (but not limited to) loss of income, due to any issues with the aforementioned 

plugins. Please contact their respective owners/admin directly to resolve any issues. 


Crypto Adaptive Payments will take payment, in the form of Bitcoin Crypto Currency, and split it 

between two or more receivers such as owner of a marketplace website and a vendor of a product 

being sold on the marketplace website.

In this scenario, the owner will receive a predetermined percentage share of the Crypto Currency, say 

x%, and the vendor will receive a different predetermined share of the Crypto Currency, say y%, 

where y% is 100% subtracted by x% (thus, the remainder of the share).

Payments will be distributed via the Coinsplit platform (https://coinsplit.io) via the gourl.io plugin.

The payment splitting will be automatic and receivers of the split payment can withdraw their funds 

once their personal Coinsplit account reaches its minimum payout threshold. The individual funds can 

be withdrawn to any external BTC wallet ID from coinsplit.

Before the plugin is installed, you need to create an account with Coinsplit (https://coinsplit.io). Once 

this has been done, look for the 'My Scheme' section and follow the steps to make your own 

scheme, in which you are able to add your own username and minimum payment threshold. Once 

this is done, you can retrieve your own scheme ID within your own specific URL. For example, within 

https://coinsplit.io/scheme/1111, your scheme ID will be 1111. Take note of this scheme ID and keep 

it safe. 

The next step is to go the 'My Profile' section and generate your Authorization Key. Again, take note 

of this.

Third, you will need your Bitcoin (BTC) Wallet ID. For this, you will need to go to the My Scheme 

section of your Coinsplit dashboard and click on your 'Scheme Name', then search for your Bitcoin 

Wallet ID. An example of this is '3EhT1sKLUobywQFv2jzXkXXVGm2MRAjnd5'. All the payouts will 

come to this ID and then split from this ID. And that this ID will be the main address you will use for 

the GoURL platform.

The scheme ID, Authorization Key and BTC Wallet ID will be needed for future steps.

You previously integrated 4 plugins into your WordPress website, two of which are required in the 

next two steps.

You now have to create a GoUrl Account (http://gourl.io) which may be a subsequent step of 

integrating the plugin into your website. After this is done, in the backend of your dashboard, insert 

the BTC wallet ID that was created from the Coinsplit platform in the previous steps. A detailed guide 

can be found in the GoURL section of your WordPress backend dashboard.

You earlier obtained a Scheme ID and Authorization Key from Coinsplit. You will now need to add this 

to your WordPress dashboard. In your dashboard, look for a section on the left called 'BTC-settings' 

There, you will have a field in which to insert your previously obtained Scheme ID and Authorisation 

Key.You just need to mention the admin percentage and the remaining percentage will be 

automatically calculated by the system and forward it to the seller.


How Crypto Adaptive Payments works:

1. Install the 3 aforementioned plugins.
2. Completed the above steps, including creating an account with Coinsplit which generates an 

Authorisation Key, Scheme ID and Bitcoin Wallet ID
3. Create a BTC account on GoURL.io. This may be a subsequent step from installing the plugin 

itself.
4. Follow the instructions in the word file called Crypto Adaptive Payments located in this folder.
5. The Vendor then inputs their Bitcoin Wallet ID in the Vendor Dashboard. Now you have everything 

ready to start.
5. The plugin will accept Crypto Currency Payment, currently only Bitcoin (BTC). The system will 

automatically create shares using the Coinsplit API.
6. Now, whenever a customer makes a purchase, the Crypto Currency will get deposited into the 

Coinsplit account, which would split the share in the predetermined percentages, and deposit it into 

the relevant Bitcoin Wallets.
7. There is also an option to have the Crypto Currency deposited into one account so the plugin can 

be used as a normal payment gateway.


== Installation ==

How to install the plugin:

1. Copy and paste the plugin files into the '/wp-content/plugins/plugin-name' directory. An alternative 

to this instillation through the WordPress Plugins screen.
2. Activate the plugin through the Plugins menu in your WordPress Backend Dashboard.
3. Enter the Crypto Adaptive Payment plugin menu in the WordPress dashboard and into Configure 

the Coinsplit Account.
4. Follow the detailed document called 'Crypto Adaptive Payment Documentation' located within the 

Crypto Adaptive Payment folder you downloaded.


== Frequently Asked Questions ==

1. How does the plugin transfer and share the funds?

Ans - The Coinsplit platform is used to transfer the money and create shares after the GoURL 

platform sends the money to the Coinsplit Platform.

2. Does Crypto Adaptive Payment have any WordPress Version Preference?

Ans - It has been tested with the latest version of WordPress and the two previous versions.

3. What is the difference between the normal and adaptive payments?

Ans - Normal payment means the administrator of the website gets the full payment, Adaptive 

payment means payment will be split between the admin and the seller of the product. The admin 

can always decide the  Percentage from the back end.

== Screenshots ==

1. plugin/assets/screenshot1.png
2. plugin/assets/screenshot2.png

== Changelog ==

= 1.0 =
* A change since the previous version.

== Upgrade Notice ==

= 1.0 =
in The latest version uses many of the hooks of the latest wp versions..

= 0.5 =
This version fixes a security related bug.  Upgrade immediately.




