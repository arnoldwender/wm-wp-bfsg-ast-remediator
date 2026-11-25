# Changelog — WM BFSG AST Remediator

All notable changes to **WM BFSG AST Remediator** are documented in this file in adherence to [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Fixed
- Relaxed GenerateBlocks 2.x `generateblocks_dynamic_tag_replacement` filter parameter to `mixed` to handle `null` replacements cleanly.
- Renamed `RestController::NAMESPACE` to `REST_NAMESPACE` for PHP 8.5/8.6 compatibility.
- Added `Tested up to: 7.1` to the main plugin header and synchronized `bom.json`.

## [1.2.2] - 2026-11-21

### Fixed

- Screenshot 1 showed the WM Suite menu from before WM Suite Hub 1.2.3, with entries the hub no longer has («Marketplace (Lösungspakete)», «Merkle-Audit-Protokoll», «CRA- & SBOM-Zentrale»). It is retaken with the current menu; the plugin's code is unchanged.

## [1.2.1] - 2026-11-21

### Changed

- The bundled family stylesheet is identical to WM Suite Hub 1.2.5 again: it starts the admin header below WordPress's help toggle instead of shortening it. This plugin's own screens register no help tab, so they change only where another plugin adds one.
- The translation catalogs are regenerated from the code: the `.pot` header names the current version, and strings the code no longer uses are gone.

## [1.2.0] - 2026-11-20

### Changed
- The WM Suite Hub contract now lives in the `ArnoldWender\SuiteHub\Contracts` namespace. The spoke adapter registers only with WM Suite Hub 1.2.0 or later, so update the hub together with this plugin; used on its own, the plugin works as before.
- The `.wordpress-org/` banners show the current version.

### Removed
- The archived pre-2026-11-19 marketing visuals in `versionen/`: they claimed features and conformity the plugin does not have. They were never part of the installable package.

## [1.1.3] - 2026-11-19

- Removed the agency name, links to unrelated projects and the personal mailbox from code, catalogs and documentation; the admin family badge and the hub menu read "WM Suite". The READMEs say the plugin was built with an agentic workflow (AI coding agents) and should be reviewed like any third-party code.

- Added root `SUPPORT.md`, `.github/ISSUE_TEMPLATE/{config.yml,bug_report.yml}`, and explicit zero-support / "AS IS" liability notices across `README.md`, `README.de.md`, `README.es.md`, `readme.txt`, `SECURITY.md`, and `llms.txt`.
- Migrated plugin author/catalog/repository metadata to personal open-source distribution (`Arnold Wender`, `https://arnoldwender.com/wordpress-plugins/wm-bfsg-ast-remediator/`, `https://github.com/arnoldwender/wm-wp-bfsg-ast-remediator`), normalized FSF GPL-2.0 `LICENSE` metadata where applicable, and updated `.wordpress-org/` banner branding to `ARNOLD WENDER · OPEN SOURCE`.

## [Unreleased]

## [1.1.2] - 2026-11-19

### Changed
- Archive superseded legacy banner/screenshot files under `versionen/visuals-pre-2026-11-19/` and embed the live `.wordpress-org/` visual kit (`banner-1544x500.png`, `banner-772x250.png`, `icon.svg`, `icon-128x128.png`, `icon-256x256.png`, `screenshot-1..2.png`).
- Add `== Screenshots ==` captions in `readme.txt`, live screenshot tables and official plugin page links in `README.md`, `README.de.md`, `README.es.md`, and `llms.txt`, and align release metadata at `v1.1.2`.

## [1.1.1] - 2026-11-19

### Changed
- Point `Plugin URI` to `https://arnoldwender.com/wordpress-plugins/wm-bfsg-ast-remediator/` and align release metadata at `v1.1.1`.

## [1.1.0] - 2026-11-16

### Added
- **Opt-in uninstall data removal (`D-UNINSTALL`):** `uninstall.php` now preserves all saved options by default and deletes plugin options only when `wm_bfsg_remediator_delete_data_on_uninstall` (default `'0'`, registered via `register_setting` and exposed on the *Regeln* tab) is explicitly enabled.
- **Translations & text domain loading (`D-LOCALES`):** Added `load_plugin_textdomain( 'wm-bfsg-ast-remediator', ... )` on `init` for WordPress 6.4–6.7 compatibility alongside WordPress 6.8+ automatic loading, regenerated `languages/wm-bfsg-ast-remediator.pot`, and translated and compiled all 28 `.po`/`.mo` catalogs.
- **Developer extensibility & ecosystem bridges:** Added filters `wm_bfsg_remediator_rules_config` and `wm_bfsg_remediator_html_output`, action `wm_bfsg_remediator_ready`, WordPress 6.5+ Block Bindings source `wm-bfsg-remediator/status`, and GenerateBlocks 2.x dynamic tag `{{wm_bfsg_remediator_status}}`.
- **Packaging, SBOM & Playground:** Added CycloneDX 1.6 `bom.json` (`GPL-2.0-or-later`), `.wordpress-org/blueprints/blueprint.json`, `.license-allowlist.yml`, symlinked `AGENTS.md -> CLAUDE.md`, and updated `.distignore`.
- **Documentation & markup hygiene:** Synchronised `README.md`, `README.de.md`, `README.es.md`, `DEVELOPERS.md`, `readme.txt`, `SECURITY.md`, `docs/ARCHITECTURE.md` and `docs/translations/README.*.md` with the shipped `1.1.0` codebase.

### Translation catalog (2026-09-20)

- **The catalog and the code shared not one entry.** All 60 translatable strings were missing from the `.pot`, so no translator could see any of them in any language, and the 117 translated entries across the catalogs belonged to the Spanish admin screen removed on 2026-09-14 — `CUMPLIMIENTO BFSG 2025 ACTIVO`, `Elementos Reparados`, `Motor de Reparación Automática`. The catalogs were carrying retracted compliance claims into 24 languages.
- Regenerated from the code with `wp i18n make-pot`; every `.po` merged with `msgmerge --no-fuzzy-matching`, every `.mo` rebuilt. The dead entries are kept as obsolete `#~` entries rather than deleted. Verified dead **before** the merge, against the strings the code extracts rather than against the merge result.
- `tests/test-pot-freshness.php` and a CI step keep the catalog matching the code.
- The Known issues note about `load_plugin_textdomain()` was corrected: since WordPress 6.8 it is not needed for any plugin that declares `Text Domain` and `Domain Path` (this one declares both) and calling it is discouraged. It still matters on the 6.4–6.7 range the plugin supports, which is a compatibility decision rather than a defect.

### Plugin Check errors in the distribution view (2026-09-17)
- **`wp bfsg remediate --write` asks `wp_is_writable()` instead of PHP's `is_writable()`** (Plugin Check, `file_system_operations_is_writable`, 1 error → 0). On POSIX the two answer the same; on Windows `wp_is_writable()` is the one that is right. The existing mutation that reports success for an unwritable file still turns the suite red with the new call.

### The version this file opens on matches the version the plugin ships (D15, 2026-09-17)

- **The plugin is 1.0.0, and the changelog now opens on 1.0.0 too.** This file used to open on `[1.1.0]`, a release that `git tag` (0) and `gh release list` (0) both deny and that no other file in the repo agreed with. The `1.1.0` entry moved **verbatim and complete** to an appendix at the foot of this file: nothing was deleted, because a changelog is a record and the record is that those entries were written. Moved by the changelog-unrelease script, which refuses to write if a single non-blank line would be lost.

### Family sheets (2026-09-16)
- Re-synced from the hub: the badge variants carry the state tokens on body surfaces, where the command-banner inks measured 1.49:1 to 2.21:1 against the composed background (hub PR #19), and the canonical sheet dropped five classes that belonged to a single plugin (hub PR #21). `tests/test-ui-family.php` re-pins both md5s.

### Fixed (2026-09-15)
- **A malformed image URL ended the page in a fatal error.** `wp_parse_url()` returns `false` for a `src` such as `http:///x.jpg`; `false ?? ''` stays `false`, and `pathinfo( false )` under `strict_types` threw a TypeError inside the output buffer, the REST simulator and WP-CLI. The path is read as a string or empty. Test group 17.
- **"icon" anywhere in the `src` hid content images.** The decorative guess searched the whole `src`, so every image on a host or with a query containing "icon" got `alt=""`; uppercase "Icon" or "Decor" in the path was missed. It reads the lower-cased path now.
- **A flushed page was transformed in parts.** The buffer callback ignored the output phase: after a mid-page `ob_flush()` the first part was serialised as a complete document and the rest appended after `</html>`. `WM_BFSG_DOM_Transformer::buffer_handler()` collects the chunks and transforms the page once; `ob_clean()` discards what was collected. Group 18.
- **The skip link could point at another element.** A `<main>` without id got `main-content` even when another element already carried that id, and the link led there. It takes `main-content-2` and so on. Group 19.
- **`wp bfsg remediate --write` reported "Datei aktualisiert" for a file it could not write.** It ends in an error. Group 20.
- **Rule codes said "REPAIRED".** The simulator and WP-CLI showed `WCAG_1.1.1_IMAGE_ALT_REPAIRED_N` for a file name used as `alt`. Codes end in `ADDED`. Group 21.
- `tests/run-mutations.sh` with 11 mutations (the four fixes above, the codes, and the earlier XSS, kill-switch and skip-link target fixes), all red; CI runs it on PHP 8.3.

### Documented (2026-09-15)
- README (EN/DE/ES), DEVELOPERS.md and readme.txt describe the buffer handler, the id suffix, the path-only icon check, the write error and the codes. The README no longer shows the banner (it reads "BFSG 2025 COMPLIANT" and "WCAG 2.2 AAA" and shows invented request counts); `assets/images/` is left out of the package (`.distignore`), the file stays pending a decision. The screenshots are marked as the pre-2026-09-14 screen, the "24 EU Languages" badge is gone, and the sentence about the translated READMEs says what the sync script generates and what it leaves from the template.
- CONTRIBUTING, CLAUDE.md/AGENTS.md, llms.txt and `public/humans.txt` no longer say PSR-12 (the code is indented with tabs, WordPress style; there is no phpcs configuration); `composer.json` no longer describes "BFSG remediation"; the engine docblock no longer speaks of "BFSG / WCAG 2.2 AA rules". Emoji removed from the headings below.

### Fixed (2026-09-14, honest docs and runtime pass)
- **One engine.** The frontend buffer ran `WM_BFSG_Rules_Engine`; the admin simulator (`wm-bfsg/v1/remediate-html`), the hub self-test and an unregistered CLI command ran `Core\Plugin` with four `Remediators\*` classes, which renumbered headings (`h4` → `h2`) and labelled controls differently. Measured on a local WordPress install on one document: the simulator reported 7 changes including a renumbered heading, the frontend engine 3 rule hits and the heading unchanged. REST, self-test and CLI now call `WM_BFSG_DOM_Transformer::transform()` with the saved switches; `Core\Plugin`, the four remediators, `Engine\AstParser`, `CLI\BfsgRemediatorCommand`, the never-loaded `class-bfsg-spoke-adapter.php`, their PHPUnit test and `phpunit.xml` are removed. The XXE assertions moved to `tests/test-suite.php` (group 15).
- **Skip link pointed at nothing.** The injected link always used `#main-content`, so on a theme with `<main id="primary">` it led nowhere; it was injected on pages without a main landmark; an inline `top:-999px` kept it off screen even when focused. It now targets the landmark's own id, is only injected when a landmark exists, and a `<style>` in `<head>` shows it on focus (system colours, no hex). English theme skip links ("Skip to content", class `skip`) are recognised.
- **Visible labels replaced.** Controls inside a `<label>` or with a `title` got an `aria-label` from `name`, which replaces their accessible name: `<label>Name <input name="n"></label>` was announced as "N". Such controls, and image buttons, are left alone.
- **Admin screen.** It was Spanish and showed "CUMPLIMIENTO BFSG 2025 ACTIVO", "WCAG 2.2 AA GARANTIZADO", "100% Accesible", "compatible con JAWS, NVDA y VoiceOver", a widget with fixed "< 2ms" and "0 CLS" and "x / 6" rules (counting a contrast switch and a skip-link switch the form never offered), counters no current code writes, and a heading switch no frontend rule reads. It is German, reads the saved state, offers exactly the engine switch and the four rule switches (skip link now included), explains each rule and what the plugin does not do, and has no emoji.
- **WP-CLI.** `wp bfsg status` printed a fixed "AKTIV & BETRIEBSBEREIT" and "BFSG 2025 / BITV 2.0 / WCAG 2.2 AA"; it prints the saved switches. `wp bfsg remediate` answered an unchanged document with "Keine BFSG-Verstöße gefunden. Dokument ist barrierefrei."; it now says that no rule changed anything and that this is no statement about accessibility, and it refuses fragments without `<html>` instead of reporting them clean.
- **REST.** `/remediate-html` validates input (400 empty, 413 above 500 KB, 422 without `<html>`, 503 without the PHP extensions); `/telemetry`, which returned counters nothing writes, is removed.
- **Hub adapter.** A switched-off engine answered `WARNING`, not a hub status (now `NEEDS_CONFIG`); telemetry read counter keys nothing ever wrote (now the switches); `self_test` runs the real engine on a fixed document and checks four outcomes; SBOM properties name features instead of compliance labels; registration on `wm_register_suite_module`.
- **Missing PHP extensions.** Without `ext-dom` or `ext-mbstring` the buffer ended every frontend request in a fatal error; the buffer, REST and CLI check first.
- **Uninstall** also deletes `wm_bfsg_fix_skip_links`.
- `composer.json`: PHP floor 8.1 like the header, test script runs the standalone suites.
- Tests: 83 in `tests/test-suite.php` (16 groups), each fix mutation-checked against `main`. — *the mutation checks were not recorded; `tests/run-mutations.sh` exists since 2026-09-15*

### Documentation (2026-09-14)
- README (EN/DE/ES), DEVELOPERS, readme.txt, CLAUDE.md/AGENTS.md, llms.txt, CONTRIBUTING, SECURITY and the 24 translated READMEs rewritten from the code. They described `[wm_bfsg_badge]`, `wp bfsg-ast status|verify|export`, REST `/status` `/submit` `/export`, the table `wp_wm_bfsg_remediations_log`, two hooks, contrast repair, GoBD reports, an HMAC chain and guaranteed BFSG / WCAG 2.2 AAA compliance; none of those exist in the code, they were never shipped.
- **[1.1.0]** below never shipped: the plugin header, `readme.txt` and the adapter say 1.0.0. Which number the next release carries is Arnold's decision (D15).

### Security
- **Site-wide XSS closed (CRIT, fleet audit 2026-09-13 §6.2 #1):** `WM_BFSG_DOM_Transformer` ran `html_entity_decode()` over the whole page after remediation, turning every `&lt;script&gt;` the theme had escaped back into live markup on any page where a rule fired. Only named entities that `saveHTML()` produces for non-ASCII text (`&szlig;`, `&euro;`) are decoded now; `&lt; &gt; &amp; &quot; &apos;` and every numeric entity stay as served. Regression test: group 8 of `tests/test-suite.php`.
- **XPath injection through element ids:** the label lookup interpolated the page's `id` attribute into an XPath expression; a quote in the id broke the query silently and every labelled control received an invented `aria-label`. Labels are indexed once and compared in PHP. Test: group 10.

### Fixed
- **Kill-switch and rule toggles were inert:** the live output buffer is hooked from the main file, outside `Core\Plugin::boot()`, so `wm_bfsg_remediator_enabled` and the four rule options saved by the panel changed nothing. The buffer now reads the kill-switch at `template_redirect` and passes the toggles into `WM_BFSG_Rules_Engine::apply_rules()` (`skip_link`, `images_alt`, `form_labels`, `nav_landmarks`; document language always runs). Test: group 9.
- **Settings screen fatal:** `WM_BFSG_REMEDIATOR_URL` was used by the admin stylesheet enqueue and never defined. Defined next to the other constants.
- **Live simulator answered 404:** `wm-bfsg/v1/remediate-html` was only registered from `Core\Plugin::boot()`, which nothing calls. The REST controller is now registered from the main file (routes require `manage_options`).
- **Invented telemetry on a fresh install:** the dashboard defaulted to 1 420 pages and 963 fixes that never happened. Defaults are zero. The counters are still not fed by the live engine; wiring them is tracked in the fleet hackaton plan. — *superseded 2026-09-14: the counters are no longer shown*

### Changed
- **Plugin Check on the dist view (2026-09-14):** the image-alt rule and the two remediator classes read the URL path through `wp_parse_url()` and the generated field id seeds from `wp_rand()` (the remediator classes were removed later the same day). `readme.txt` says `Requires PHP: 8.1` like the plugin header, the READMEs and the developer manual (commit 1232fa3 lowered the floor on purpose; the readme kept the old 8.2). The readme licence line still differs from the header and waits for Arnold.

### Design family (hackaton phase 3, 2026-09-14)
- The admin screen renders from the two WM Suite family sheets (`assets/css/wm-admin-tokens.css`, `assets/css/wm-admin-ui.css`, byte-identical copies of the hub's canonical files, pinned by `tests/test-ui-family.php`) instead of a private copy of `admin-obsidian.css`; same handles as every WM plugin, versioned by file time. Native `.wrap`, family badge with the hub/standalone state, gettext menu titles, family menu icon, visible focus rings on every control.
- **The repository never contained `assets/`:** the workspace's global ignore rule for `/Assets/` matched this plugin's root `assets/` on a case-insensitive filesystem. `.gitignore` now negates the rule (as the hub does) and the stylesheets and banner are committed.

---

## [1.0.0] — 2026-08-25

### Initial Enterprise Release
- Core architectural implementation for Accessibility & BFSG. — *never shipped as described: the plugin makes no BFSG assessment*
- WP-CLI management commands under `wp bfsg-ast`. — *never shipped: the command is `wp bfsg`*
- Revisionssicheres GoBD cryptographic ledger and telemetry hooks. — *never shipped*
- Strict compliance with Barrierefreiheitsstärkungsgesetz (BFSG 2025), BITV 2.0, WCAG 2.2 AAA & EN 301 549. — *never shipped: the plugin assesses no compliance*

---

## Appendix — the entry written for 1.1.0 on 2026-08-29, never released

> **Not a released version — the plugin does not carry this number.** Measured 2026-09-17: `git tag` → 0, `gh release list` → 0, and `wm-bfsg-ast-remediator.php`, `readme.txt`, `SECURITY.md` carry **1.0.0**, which is the version this plugin ships as. The entry is kept verbatim as the record of what was written on 2026-08-29; only its heading changed, because Keep a Changelog reserves `## [x.y.z]` for releases and this was never one. Raising the plugin to 1.1.0 means raising `wm-bfsg-ast-remediator.php`, `readme.txt`, `SECURITY.md` in the same commit — the number lives in more than one file.

### Added
- **24 Official European Union Languages:** Full binary `.mo` and source `.po` translation packages in `languages/` (`bg_BG`, `cs_CZ`, `da_DK`, `de_DE`, `el_GR`, `en_GB`, `es_ES`, `et_EE`, `fi_FI`, `fr_FR`, `ga_IE`, `hr_HR`, `hu_HU`, `it_IT`, `lt_LT`, `lv_LV`, `mt_MT`, `nl_NL`, `pl_PL`, `pt_PT`, `ro_RO`, `sk_SK`, `sl_SI`, `sv_SE`). — *never shipped as described: the files are named `el`, `et`, `ga`, `lv` (plus `ar`, `tr_TR`, `en_US`, `fi`), none is loaded, and 117 of 2 646 entries are translated (measured 2026-09-15)*
- **Developer Documentation Suite:** Created `DEVELOPERS.md` with complete architecture diagrams, hook references, database schema, and REST API guides. — *never shipped: the hooks, schema and routes it described did not exist*
- **Decoupled REST API Controller:** Registered namespace `/wp-json/wm-bfsg/v1` with strict nonce and permission validation.
- **Zero-CDN Frontend Shortcode:** Implemented `[wm_bfsg_badge]` with inline SVG icons and CSS design tokens. — *never shipped*
- **CycloneDX v1.6 SBOM Engine:** Dynamic SHA-256 entry file integrity hashing and spoke adapter integration for **WM Suite Hub**. — *the adapter returns one component with a hash; no CycloneDX document or version*
- **Enterprise Showcase Assets:** High-fidelity 16:9 marketing banner and cockpit showcase mockups conditioned on real screenshot topology. — *the banner claims "BFSG 2025 COMPLIANT" and "WCAG 2.2 AAA" with invented figures; not shipped since 2026-09-15*
- **Clean Uninstallation Lifecycle:** Comprehensive `uninstall.php` handler for clean database and transient cache purging. — *it deletes seven options; the plugin has no transients*

### Security
- **CWE-1236 Mitigation:** Active sanitization against formula injection attacks on all CSV exports and dynamic text inputs. — *never shipped: there is no export*
- **Constant-Time Cryptography:** Enforced `hash_equals()` verification on all HMAC-SHA256 signature chains. — *never shipped: there is no chain*
- **SQL Parameterization:** Refactored all queries to use strict `$wpdb->prepare()` with parameterized placeholders. — *never shipped: the plugin runs no SQL*

### Quality & Tests
- Expanded standalone deterministic test suite (`tests/test-suite.php`) with 100% green pass rate and zero external dependencies. — *no run was recorded*
