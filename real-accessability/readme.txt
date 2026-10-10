=== Real Accessibility ===
Contributors: realmediail
Donate link: https://realmedia.co.il
Tags: accessibility, toolbar, font size, grayscale, text to speech
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.0
Stable tag: 1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An accessibility toolbar for your website - font resizer, color inverse, black & white view, link highlighting, readable font and a page reader.

== Description ==

Real Accessibility adds a very easy to use and intuitive accessibility toolbar to your website.

**Real Accessibility Features:**

* Increase font/text size
* Decrease font/text size
* Black & White view
* Invert website colors
* Highlight all links with underline
* Use a more readable font (Arial)
* Page reader - reads the page content out loud
* Remove target attribute from links
* Add role="link" to links
* Keyboard accessible toolbar
* RTL & LTR Support

**Page reader**

The page reader uses the speech engine that is built into the visitor's browser and operating system (the Web Speech API). The page content is not sent to any external service. The reader buttons appear only when the visitor's device has a voice for the language of the page.

**Privacy**

The plugin stores the visitor's toolbar settings in a cookie named `real-accessability` so they stay applied while browsing the site. No personal data is collected and nothing is sent to third parties.

**Available languages:**

* English
* Hebrew

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/real-accessability` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to your website, you will see the accessibility toolbar.

== Frequently Asked Questions ==

= The page reader buttons do not appear =

The page reader is shown only when the visitor's browser supports speech and a voice for the page language is installed on the device. A help page with instructions for adding a voice is linked from the toolbar.

== Changelog ==

= 1.1 =
* Security: the page reader no longer loads scripts from external servers (508fi.org and ResponsiveVoice). The 508fi.org domain is no longer operated by the original service - please update.
* Page reader rewritten to use the browser's built-in speech engine, with support for Hebrew and any other language that has a voice installed on the device.
* Screen reader support page updated, now in English and Hebrew, and excluded from search engines (noindex).
* Fixed PHP 8 warnings when reading the settings cookie.
* Settings cookie is now validated before use.
* Toolbar is keyboard accessible: real buttons for the page reader, ARIA states, Escape closes the toolbar, hidden items are not focusable while the toolbar is closed.
* role="link" is no longer forced on links that already have a role.
* Black & White and Inverse Colors no longer break the toolbar's fixed position.
* Tested with WordPress 7.1.

= 1.0 =
This is the first stable version of Real Accessability Plugin

== Upgrade Notice ==

= 1.1 =
Security update: removes scripts loaded from external servers that are no longer operated by the original service. Please update.
