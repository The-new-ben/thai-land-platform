=== Thailand Platform ===
Contributors: thailand-co-il
Tags: thailand, platform
Requires at least: 6.9
Tested up to: 7.0.3
Requires PHP: 7.4
Stable tag: 0.2.6
License: Proprietary

Geography, search, and homepage runtime for thai-land.co.il.

== Description ==

Version 0.2.6 adds the canonical country, seven-region, and 77-province data
spine. The compiled runtime resolves reviewed Hebrew, English, and Thai names,
codes, slugs, and aliases without fuzzy guessing. A public read-only geography
endpoint returns the bounded client payload with cache headers and ETag
revalidation. Authoring sources, internal indexes, and source notes are not
packaged as public data. Upgrade and activation do not change content, URLs,
redirects, taxonomies, schema, or database structure. The existing RTL
homepage and compact chat behavior remain unchanged.

== Changelog ==

= 0.2.6 =
* Add the canonical Thailand country, seven-region, and 77-province registry.
* Resolve reviewed Hebrew, English, and Thai identities without fuzzy guesses.
* Add the cacheable public geography endpoint with ETag revalidation.
* Add SEO intent ownership and geography source-lineage release evidence.
* Add concrete Chabad and kosher-service details for Bangkok, Phuket, Samui,
  Koh Phangan, and Chiang Mai.
* Replace unsupported search labels and enforce a 50-phrase public language
  boundary.

= 0.2.5 =
* Replace presentation-style homepage language with direct destination,
  property, business, living, and reader language.
* Keep unsolicited chat greetings compact without closing a visitor-initiated
  chat.

= 0.2.4 =
* Resume chat settling when a visitor returns through browser history.

= 0.2.3 =
* Wait for the chat widget to confirm its compact state after a late load.

= 0.2.2 =
* Reserve the correct side of the phone action bar for the existing chat
  launcher.

= 0.2.1 =
* Keep the existing chat compact on larger screens and clear of homepage
  controls on phones.
* Add release checks for responsive chat behavior.

= 0.2.0 =
* Add the reversible homepage template, administrator canary, scoped assets,
  responsive artwork, SEO coexistence controls, and rollback switch.

= 0.1.0 =
* Add the plugin bootstrap, compatibility gate, and minimal healthcheck.
