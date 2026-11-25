=== WM BFSG AST Remediator ===
Contributors: arnoldwender
Tags: accessibility, skip link, alt text, aria, output buffer
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Rewrites frontend HTML with five fixed rules: lang, skip link, image alt, form control aria-label, navigation names. No accessibility audit.

== Description ==

An output buffer on frontend requests parses each full HTML page with DOMDocument (no network access) and applies the switched-on rules before the page is sent:

* **lang:** fills in a missing lang attribute from the WordPress locale.
* **Skip link:** when the page has a main landmark and no skip link, inserts "Zum Inhalt springen" pointing at the landmark's id, visible on focus.
* **Image alt:** gives images without alt an empty alt ("icon" or "decor" in the URL path, or no file name) or their file name.
* **Form controls:** gives controls without any accessible name an aria-label from placeholder or name; controls inside a label or with a title are left alone.
* **Navigation names:** names several unnamed nav elements.

The admin screen shows the switches, a simulator that runs the same engine, an opt-in setting for deleting plugin options on uninstall (disabled by default so settings survive reinstallation), and a list of what the plugin does not do. WP-CLI: `wp bfsg status`, `wp bfsg remediate <file>`. Exposes filters `wm_bfsg_remediator_rules_config` and `wm_bfsg_remediator_html_output`, action `wm_bfsg_remediator_ready`, WordPress 6.5+ Block Bindings source `wm-bfsg-remediator/status`, GenerateBlocks 2.x dynamic tag `{{wm_bfsg_remediator_status}}`, and registers as a spoke of WM Suite Hub when active.

The plugin does not check pages for accessibility, fixes no contrast, headings, keyboard or focus issues, and does not make a site conformant with BFSG, BITV 2.0 or WCAG. A file name as alt and a name as aria-label are stand-ins; the real fix belongs in theme and content.

== Frequently Asked Questions ==

= Is technical or private support provided? =

No. This plugin comes with zero support (`0` support). Private inquiries sent via email, `arnoldwender.com`, or social/chat channels are not answered. Reproducible bugs belong solely on GitHub Issues (`https://github.com/arnoldwender/wm-wp-bfsg-ast-remediator/issues`) with no guarantee of reply or resolution. The software is provided **"AS IS"** at your sole risk with zero warranty or liability (see `SUPPORT.md` in the repository).

== Screenshots ==

1. DOMDocument server-side HTML buffer remediation rules for lang, skip links, form labels and nav landmarks
2. WordPress dashboard widget displaying active HTML AST remediation counters and opt-in status

== Changelog ==


= 1.2.2 =
* Fixed: Screenshot 1 showed the WM Suite menu from before WM Suite Hub 1.2.3, with entries the hub no longer has («Marketplace (Lösungspakete)», «Merkle-Audit-Protokoll», «CRA- & SBOM-Zentrale»). It is retaken with the current menu; the plugin's code is unchanged.

= 1.2.1 =
* The bundled family stylesheet is identical to WM Suite Hub 1.2.5 again: it starts the admin header below WordPress's help toggle instead of shortening it. This plugin's own screens register no help tab, so they change only where another plugin adds one.

= 1.2.0 =
* The WM Suite Hub contract now lives in the `ArnoldWender\SuiteHub\Contracts` namespace. The spoke adapter registers only with WM Suite Hub 1.2.0 or later, so update the hub together with this plugin; used on its own, the plugin works as before.
* The `.wordpress-org/` banners show the current version.
* Removed: The archived pre-2026-11-19 marketing visuals in `versionen/`: they claimed features and conformity the plugin does not have. They were never part of the installable package.

= 1.1.3 =
* Removed the agency name, links to unrelated projects and the personal mailbox from code, catalogs and documentation; the admin family badge and the hub menu read "WM Suite". The READMEs say the plugin was built with an agentic workflow (AI coding agents) and should be reviewed like any third-party code.
* Added root `SUPPORT.md`, GitHub Issue templates, explicit zero-support & "AS IS" liability notices in READMEs and `readme.txt`, and normalized canonical GPLv2+ `arnoldwender.com` / `arnoldwender/wm-wp-bfsg-ast-remediator` links.
* Updated `.wordpress-org/` visual banners to personal `ARNOLD WENDER · OPEN SOURCE` branding.

= 1.1.2 =
* Added live `.wordpress-org/` visual kit assets (banner, icons, and live WordPress screenshots) and updated documentation links for v1.1.2.

= 1.1.1 =
* Point Plugin URI to the official plugin page.

= 1.1.0 =
* Added opt-in uninstall setting `wm_bfsg_remediator_delete_data_on_uninstall` (off by default so options are preserved on uninstall unless explicitly enabled).
* Added `load_plugin_textdomain` on `init` and complete translations across 28 locales.
* Added developer hooks (`wm_bfsg_remediator_rules_config`, `wm_bfsg_remediator_html_output`, `wm_bfsg_remediator_ready`), WordPress 6.5+ Block Bindings source (`wm-bfsg-remediator/status`), and GenerateBlocks 2.x dynamic tag (`{{wm_bfsg_remediator_status}}`).
* Added CycloneDX 1.6 `bom.json` and WordPress Playground `blueprint.json`.
* Fixed: an image whose src does not parse as a URL no longer causes a TypeError.
* Fixed: "icon" in an image host or query no longer hides content images; the check reads the path case-insensitively.
* Fixed: a page flushed in parts is transformed once instead of each part as a document of its own.
* Fixed: the skip-link target id avoids ids another element already carries.
* Fixed: `wp bfsg remediate --write` uses `wp_is_writable()` and reports unwritable files as errors.
* Unified frontend buffer, REST simulator, WP-CLI and hub self-test on `WM_BFSG_DOM_Transformer::transform()`.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.2 =
Screenshot 1 retaken with the current WM Suite menu. No code, data or settings change.

= 1.2.1 =
Stylesheet update only (the family sheet of WM Suite Hub 1.2.5). No data or settings change.

= 1.2.0 =
Hub users: update WM Suite Hub to 1.2.0 together with this plugin. The hub contract moved to a new PHP namespace; next to an older hub the plugin keeps working, but it is not listed in the cockpit and its admin page has no menu entry until the hub is updated.
