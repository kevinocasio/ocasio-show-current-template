=== Ocasio Show Current Template ===
Contributors: ocas
Tags: current template, show template, template hierarchy, theme developer, admin bar template
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://kevinocasio.com/

Displays the active theme template filename and included template parts in the WordPress admin bar for administrators.

== Description ==

When editing or troubleshooting WordPress themes, guessing which PHP template file is rendering the current page wastes valuable time.

Ocasio Show Current Template tells you immediately. It captures the active template hierarchy file and displays its filename directly inside your top admin bar when logged in as an administrator.

Hovering over the menu item reveals the full relative file path and a complete list of all loaded template parts and partials.

It runs only for logged-in site administrators, leaving zero footprint for normal visitors and adding zero database clutter.

== Installation ==

1. Upload `ocasio-show-current-template.zip` through the WordPress admin screen via **Plugins -> Add New -> Upload Plugin**.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Open **Ocasio Plugins -> Dashboard** from the WordPress sidebar and ensure the **Active on Site** toggle is switched ON.

== Frequently Asked Questions ==

= Who can see the active template in the admin bar? =
Only logged-in administrators with the `manage_options` capability can see the template info. Regular visitors and non-admin users will never see it.

= Does this work with child themes? =
Yes. It automatically identifies whether a template file or template part is being loaded from your active child theme or parent theme.

= Does this slow down my website? =
No. It doesn't load external scripts, stylesheets, or database queries. It uses native WordPress template inspection hooks.

= Does it change anything in my database? =
No. It only reads template filenames dynamically in memory and doesn't write any content to your database.

== Changelog ==

= 1.0.0 =
* Initial public release.
* Added active template hierarchy capture via template_include hook.
* Added admin bar node for logged-in administrators.
* Added dropdown inspector for included template parts and relative file paths.
* Integrated into the unified Ocasio Plugins suite dashboard.
