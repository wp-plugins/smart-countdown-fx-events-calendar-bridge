=== Smart Countdown FX Events Calendar Bridge ===
Contributors: alex3493 
Tags: smart countdown fx, countdown, counter, count down, timer, event, widget, recurring, events calendar, modern tribe
Requires at least: 3.6
Tested up to: 4.2.2
Stable tag: 1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Smart Countdown FX Events Calendar Bridge adds Modern Tribe Events Calendar support to Smart Countdown FX.

== Description ==
Import upcoming events from [Events Calendar][3] to Smart Countdown FX. The counter will switch to the next event automatically after current event is over.

Smart Countdown FX Events Calendar Bridge **requires [Smart Countdown FX][2] version 0.9.5 or higher**, please do not forget to update before proceeding.

Up to two independent configurations are supported.

**Other features**

When configuring Smart Countdown FX or adding a shortcode to you post you can choose one of configurations defined in "Smart Countdown FX Events Calendar Bridge" settings.

Each configuration implements the following options:

* Filter events by category
* Show event date and / or venue
* Link countdown to event view

For samples and complete documentation [see this page][1]

 [1]: http://smartcalc.es/wp/index.php/2015/06/15/events-calendar-bridge/
 [2]: https://wordpress.org/plugins/smart-countdown-fx/
 [3]: https://theeventscalendar.com/product/wordpress-events-calendar/

== Installation ==
Extract the zip file and just drop the contents in the wp-content/plugins/ directory of your WordPress installation and then activate the Plugin from Plugins page. Open "Settings" and configure events import

== Frequently Asked Questions ==
= How does one use the shortcode, exactly? =
Actually there is a single shortcode - "import_config".

<http://smartcalc.es/wp/index.php/2015/06/15/events-calendar-bridge/> - complete list of attribute values for this shortcode has been provided to answer this exact question.

= I have installed the plugin, but the counter doesn't appear in available widgets list. =
Do not forget to install and activate the main plugin - Smart Countdown FX.

= I have configured the widget but it is not displayed. =
Please, check "Counter display mode" setting in the widget options. If "Auto - both countdown and countup" is not selected, the widget might have been automatically hidden because the event is still in the future or already in the past. If you are using "Smart Countdown FX Events Calendar Bridge" plugin check that "Import events from:" setting is correct. Then go to "Smart Countdown FX Events Calendar Bridge" settings and make sure that configurationselected in "Import events from:" is not set to "Disabled".
**Linking a widget to a disabled configuration will hide the counter becuse no events will be found**

= I have inserted the countdown in a post, but it is not displayed. What's wrong? =
Check the spelling of "fx_preset" attribute (if you included it in attributes list). Try the standard fx_preset="Sliding_text_fade.xml". Also check "mode" attribute. Set in to "auto". If you are using Events Calendar Bridge plugin check that import_config attribute is correct, e.g.: import_config="scd_tribe_events::1" to use the first pattern. Then go to "Smart Countdown FX Events Calendar Bridge" settings and make sure that Configuration 1 "Authorization" is not set to "Disabled".
**Linking a widget to a disabled configuration will hide the counter becuse no events will be found**

== Screenshots ==
1. Plugin settings sample

2. Time zone metabox in Event form

== Changelog ==

= 1.1 =

Added support for "countdown to event end" mode

= 1.0 =

First release