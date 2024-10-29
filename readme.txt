=== Plugin Name ===
Contributors: rushproject
Tags:  passbook, iphone
Requires at least: 3.0.1
Tested up to: 3.4.2
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Generate and download Apple Passbook member cards from the Pass Gate server using Rest API. The cards are built with info of currently logged in user.

== Description ==

The plugin provides integration with [Apple passbook server](http://www.passgate.com "Apple passbook server") hosted by Rush Project, Inc.

The *Apple Passbook Gateway* employs Rest API to generate and retrieve membership cards from the server. The cards are
built using information (email, name etc) of currently logged in user. The plugin also calculates email hash, which is
suitable for retrieving user photo from Gravatar site. The photo may be used for the pass thumbnail image.

The plugin requires account with the Pass Gate server - free trial is available for you to evaluate the technology.

== Installation ==

1. Install Apple Passbook Gateway either via the WordPress.org plugin directlry, or by uploading the files to your server.

2. Activate plugin.

3. Create pass template and generator on Pass Gate server. Use sample "wordpress" template instead of starting from
scratch with all new template. The "wordpress" template is identical to the one used in the demo.

4. Use plugin settings menu (Dashboard/Settings/Passbook Gateway) to supply Rest API connection info - the page provides
a link to setup free trial account with Pass Gate.

5. Place the [passbook-member-card] short code on appropriate page of your site. The short code will generate either
member card download link or, if visitor is not logged in, login form. You may provide link text using option parameter
"title":  [passbook-member-card title="Clip your card!"]

== Changelog ==

= 1.0 =
* First release
