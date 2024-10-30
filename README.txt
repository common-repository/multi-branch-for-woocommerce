=== Multi Branch for WooCommerce ===
Contributors: condless
Tags: branches, vendors, locations, stores
Requires at least: 5.2
Tested up to: 6.5
Requires PHP: 7.0
Stable tag: 1.0.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WooCommerce plugin for configuring store with multiple branches.

== Description ==

WooCommerce plugin for configuring store with multiple branches.

[Documentation](https://en.condless.com/multi-branch-for-woocommerce/) | [Contact](https://en.condless.com/contact/)

= How To Use =
1. Plugin settings: Choose the Shipping Methods Users and the Branches Shipping Methods
1. Product edit screen (Multi Branch tab): Insert the Shipping Methods instance id's which the product can be provided by
1. Branches shortcode: Embed the [mbw_branches] shortcode in your header to allow the customer to select his branch

= How It Works =
1. The customer select his branch before shopping
1. Only the products which can be provided by the shipping methods of the selected branch are displayed
1. On order creation the new order notification will be sent to the relevant Shipping Methods Users and only them will be able the see it on the orders screen

= Features =
* **Conditional Content**: Display content per branch using the mbw-restrict-branch-{branch_id} class
* **Add to cart Notice**: Notice will appear if the customer add to cart product which can not be provided by the selected branch
* **Checkout Validation**: Notice will appear if the cart contains items which can not be provided by the selected shipping method
* **Orders manager Role**: Assign the orders manager role to users which should be able only to edit orders in the store

== Installation ==

= Minimum Requirements =
WordPress 5.2 or greater
PHP 7.0 or greater
WooCommerce 3.4 or greater

= Automatic installation =
1. Go to your Dashboard => Plugins => Add new
1. In the search form write: Condless
1. When the search return the result, click on the Install Now button

= Manual Installation =
1. Download the plugin from this page clicking on the Download button
1. Go to your Dashboard => Plugins => Add new
1. Now select Upload Plugin button
1. Click on Select file button and select the file you just download
1. Click on Install Now button and the Activate Plugin

== Screenshots ==
1. Branches Shipping Methods settings
1. Branches Shortcode
1. Notice when customer tries to place order with product which can not be provided by the selected shipping method

== Frequently Asked Questions ==

= How to identify the instance id of a shipping method? =

On WooCommerce Shipping Zone edit screen, Hover on its edit link and check the URL.

= Why the Shipping Methods User I configured can see all the orders? =

The first Shipping Methods User is considered the default and is able to see all the orders which are not assigned to no other Shipping Methods User.

= How to change the assigned shipping method instance id of an order? =

In the custom fields meta box of the order edit screen, when creating order from admin it should be added manually.

= How to set different product's stock/prices of for each branch? =

Create seperate product for each branch, and assign the relevant shipping methods to each product.

= What if multiple branches offer the same shipping method? =

Create seperate shipping method for each branch and use Conditional Shipping plugin to hide them on checkout based on the branches routing logic.

= How to set different payment method per branch? =

Use Conditional Payment plugin to display the proper payment method based on the branches shipping methods.

= Why I can't edit the per product options? =

The plugin is not compatible with the new product editor.

== Changelog ==

= 1.0.6 - May 22, 2024 =
* Enhancement - WooCommerce version compatibility

= 1.0.5 - March 1, 2024 =
* Enhancement - WordPress version compatibility

= 1.0.4 - October 12, 2023 =
* Enhancement - WooCommerce version compatibility

= 1.0.3 - June 30, 2023 =
* Enhancement - WooCommerce version compatibility

= 1.0.2 - March 18, 2023 =
* Enhancement - WooCommerce version compatibility

= 1.0.1 - November 29, 2022 =
* Enhancement – Filters for default customer branch

= 1.0 - November 1, 2022 =
* Initial release
