=== Thailand Platform ===
Contributors: thailand-co-il
Tags: thailand, platform
Requires at least: 6.9
Tested up to: 7.0.3
Requires PHP: 7.4
Stable tag: 0.2.0
License: Proprietary

The modular platform foundation for thai-land.co.il.

== Description ==

Version 0.2.0 adds the premium RTL homepage presentation behind an
administrator-controlled, fail-closed feature flag. Upgrade and activation do
not change the configured front page, content, URLs, redirects, taxonomies,
schema, or database structure. The default mode is Off; Canary is private and
noindex; Live changes only the presentation of the existing canonical homepage.
Mode transitions, deactivation, and uninstall purge the installed page cache.
The public healthcheck remains available and the vendored update UI fallback
remains disabled.

== Changelog ==

= 0.1.0 =
* Add the plugin bootstrap, compatibility gate, and minimal healthcheck.

= 0.2.0 =
* Add the reversible homepage template, administrator canary, scoped assets,
  responsive artwork, SEO coexistence controls, and rollback switch.
