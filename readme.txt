=== Plugin Name ===
Contributors: phikai
Donate link: http://runkeeper.thinkonezero.com
Tags: runkeeper, widget,
Requires at least: 3.5
Tested up to: 4.7.0
Stable tag: 1.7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The RunKeeper + WordPress Activity Feed plugin automatically posts RunKeeper Activities straight to your blog.

== Description ==

The RunKeeper + WordPress Activity Feed plugin automatically posts RunKeeper Activities straight to your blog. All you need to do is activate the plugin and connect your account. After that, track your activities with RunKeeper and they'll show up on your WordPress site for everyone to see!

**NEW FEATURE**: Metric Unit Support

* Post Options and the Widget now support the use of metric units, just update your options!

**NEW FEATURE**: RunKeeper Records Widget

* Adds a new widget which allows to display your RunKeeper records in your blog sidebar.

Please help by reporting any bugs/feature request at the link below.

Bugs:

* Report at: [Github Issue Tracker](https://github.com/phikai/runkeeper-wordpress-activity-feed/issues)

Questions/Comments:

* http://www.kaiarmstrong.com/contact/
* runkeeper@thinkonezero.com

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload the entire `/runkeeper-wordpress-activity-feed/` folder to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Navigate to the RunKeeper Activity Feed Options under Settings and Authorize your RunKeeper Account
1. Input Author ID, Categories and Post Options and Save
1. That's it. Your Activities will automatically be published to your blog.

== Frequently Asked Questions ==

= I get this error: "Fatal error: Call to undefined function date_create_from_format()" =

This function was introduced in PHP 5.3, so you'll need to check with your hosting provider to make sure you're using at least PHP 5.3.

== Screenshots ==

1. Authorization Button in the WordPress Dashboard
2. http://runkeeper.thinkonezero.com used for RunKeeper Account Authentication
3. Profile Verification so we know an account was authorized
4. Plugin options for Posts

== Changelog ==

= 1.7.4 =
* Stable up to 4.7.0

= 1.7.3 =
* Stable up to 4.0.0

= 1.7.2 =
* Added classes for easy list styling

= 1.7.0 =
* Updated Admin Interface, Thanks to HughbertD

= 1.6.4 =
* BUG FIX: Fix in Widget Loop

= 1.6.3 =
* BUG FIX: HeartRate Data Should Work

= 1.6.2 =
* BUG FIX: Correct Notice in Widget

= 1.6.0 =
* NEW FEATURE: Average Pace Options for Posts

= 1.5.0 =
* NEW FEATURE: Metric Unit Support for Posts and Widget

= 1.4.0 =
* NEW FEATURE: Single Activity Import based on Activity ID

= 1.3.1 =
* BUG FIX: Update RunKeeper API Library with Records Method

= 1.3.0 =
* NEW FEATURE: RunKeeper Records Widget - List all your records for a specific activity on your sidebar.

= 1.2.8 =
* directly including libraries

= 1.2.4 =
* Initial Release

== Upgrade Notice ==

= 1.2.8 =
* Fixes fatal error on plugin activation
