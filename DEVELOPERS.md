# WM BFSG AST Remediator — Developer Manual

> Everything below is measured against the code in this repository (`v1.2.2`).

---

## 1. Layout

| Path | Role |
| --- | --- |
| `wm-bfsg-ast-remediator.php` | Header, constants (`WM_BFSG_REMEDIATOR_VERSION` / `_FILE` / `_PATH` / `_URL`), PSR-4 autoloader for `WMCyber\BFSG\Remediator\` → `includes/`, requires the engine, boots the admin, loads WP-CLI, loads translations on `init` (1), boots Block Bindings / GenerateBlocks dynamic tags + fires `wm_bfsg_remediator_ready` on `init` (20), registers the REST controller on `rest_api_init`, the hub adapter on `plugins_loaded` (20) and the output buffer on `template_redirect` (1). |
| `includes/class-bfsg-rules-engine.php` | `WM_BFSG_Rules_Engine::RULES` and `apply_rules( DOMDocument, array $enabled )`: the five rules. |
| `includes/class-bfsg-dom-transformer.php` | `WM_BFSG_DOM_Transformer::engine_available()`, `rules_from_options()`, `buffer_handler( string $chunk, int $phase )` (the `ob_start` callback), `transform( string $html, ?array $enabled_rules )`: parse, `wm_bfsg_remediator_rules_config` filter, rules, serialise, entity handling, `wm_bfsg_remediator_html_output` filter. |
| `includes/class-bfsg-cli.php` | `WM_BFSG_CLI`, command `wp bfsg` with `status` and `remediate`. |
| `includes/API/RestController.php` | `POST /wm-bfsg/v1/remediate-html`. |
| `includes/Admin/AdminDashboard.php` | Menu, family sheet enqueue, `register_settings()`, four tabs, settings save, dashboard widget, `rule_descriptions()`. |
| `includes/Adapters/Bfsg_Remediator_Spoke_Adapter.php` | WM Suite Hub contract; returns early when the hub's interface does not exist. |
| `assets/css/` | `wm-admin-tokens.css`, `wm-admin-ui.css` (copies of the hub's canonical sheets). |
| `languages/` | `.pot` template and 28 compiled `.po`/`.mo` catalogs loaded via `load_plugin_textdomain()`. |
| `bom.json` | CycloneDX 1.6 Software Bill of Materials (`GPL-2.0-or-later`). |
| `.wordpress-org/blueprints/blueprint.json` | WordPress Playground preview blueprint. |
| `uninstall.php` | Preserves options by default; deletes all plugin options only when `wm_bfsg_remediator_delete_data_on_uninstall` is `'1'`. |

---

## 2. Request flow

`wm_bfsg_start_buffer()` returns early for `is_admin()`, AJAX, cron and `REST_REQUEST`, when `WM_BFSG_DOM_Transformer::engine_available()` is false (no `DOMDocument` or no `mb_encode_numericentity`), and when `wm_bfsg_remediator_enabled` is not `'1'`. Otherwise it starts `ob_start( [ 'WM_BFSG_DOM_Transformer', 'buffer_handler' ] )`. The handler collects chunks and returns nothing until the final call (`PHP_OUTPUT_HANDLER_FINAL`), then passes the whole page to `transform()` when it contains `<html`; `PHP_OUTPUT_HANDLER_CLEAN` discards what was collected.

`transform()` encodes non-ASCII as numeric entities, loads with `LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET` and libxml errors suppressed, passes the active rule map through `wm_bfsg_remediator_rules_config`, runs `apply_rules()`, and returns the input unchanged when no rule fired. After a change it serialises with `saveHTML()`, decodes the numeric entities and then only named entities that do not decode to `< > & " '`, and passes the output HTML through `wm_bfsg_remediator_html_output`. Return shape: `html`, `fixes` (rule codes), `fixed_count`, `modified`.

---

## 3. Rules

| Key | Option | Code(s) | Behaviour |
| --- | --- | --- | --- |
| `lang` | none | `WCAG_3.1.1_HTML_LANG_ADDED` | `lang` from `get_locale()` with `_` → `-` when missing or empty. |
| `skip_link` | `wm_bfsg_fix_skip_links` | `WCAG_2.4.1_SKIP_LINK_ADDED`, `WCAG_2.4.1_MAIN_LANDMARK_ID_ADDED` | Needs `//main` or `[@role="main"]`; sets `id="main-content"` if the landmark has none (`main-content-2`, `-3` … when that id is taken); skips injection when a `#`-link points at the target or has `skip` in its class or "inhalt" / "skip to" in text or title; otherwise inserts `a.wm-bfsg-skip-link` first in `<body>` and a `<style>` in `<head>` (translateY off screen, visible on `:focus`, system colours). |
| `images_alt` | `wm_bfsg_fix_images` | `WCAG_1.1.1_IMAGE_ALT_ADDED_N` | `//img[not(@alt)]`: `alt=""` if the lower-cased URL path contains `icon` or `decor` or the file name is empty, else the file name with `_ - +` as spaces, `ucfirst`. |
| `form_labels` | `wm_bfsg_fix_labels` | `WCAG_4.1.2_FORM_ARIA_LABEL_ADDED_N` | Controls without `aria-label`, `aria-labelledby`, non-empty `title` or an ancestor `label` (inputs also not hidden, submit, button, reset, image); ids referenced by `label[for]` are indexed in PHP, not interpolated into XPath; `aria-label` = placeholder, else `ucfirst(name)`, else "Eingabefeld". |
| `nav_landmarks` | `wm_bfsg_fix_aria` | `WCAG_1.3.1_NAV_ARIA_LABEL_ADDED_N` | Only with two or more unnamed `<nav>`: class main / primary / header → "Hauptnavigation", footer → "Fußzeilennavigation", mobile → "Mobilmenü", else "Navigation N". |

The WCAG numbers in the codes say which criterion a rule relates to; they are not conformance results.

---

## 4. Hooks, Block Bindings & GenerateBlocks

### Filters & Actions

| Hook | Type | Signature | Purpose |
| --- | --- | --- | --- |
| `wm_bfsg_remediator_rules_config` | Filter | `( array $rules_config, string $html ): array` | Override which rules run on a given HTML document (for example per template or URL). |
| `wm_bfsg_remediator_html_output` | Filter | `( string $decoded_html, array $fixed_rules, string $original_html ): string` | Inspect or post-process the transformed HTML string when at least one rule modified the document. |
| `wm_bfsg_remediator_ready` | Action | `( string $version ): void` | Fired on `init` (priority 20) once Block Bindings and GenerateBlocks dynamic tags are registered. |

### Block Bindings & GenerateBlocks 2.x

- **WordPress 6.5+ Block Bindings:** source `wm-bfsg-remediator/status` accepts `args.key` (`summary` default, `enabled`, `active_rules_count`, `total_rules_count`) and returns factual runtime state without compliance claims.
- **GenerateBlocks 2.x Dynamic Tag:** `{{wm_bfsg_remediator_status}}` (registered via `GenerateBlocks_Register_Dynamic_Tag` and `generateblocks_dynamic_tag_replacement` filter; optional `field` parameter).

---

## 5. REST

| Method | Route | Permission | Response |
| --- | --- | --- | --- |
| `POST` | `/wp-json/wm-bfsg/v1/remediate-html` | `manage_options` | Argument `raw_html` (string, required). 200 with `data.html`, `data.fixes`, `data.fixed_count`, `data.modified`, `data.rules`; 400 empty; 413 above `RestController::MAX_HTML_BYTES` (500 000); 422 without `<html>`; 503 without the PHP extensions. |

---

## 6. WP-CLI

| Command | Effect |
| --- | --- |
| `wp bfsg status` | Version, engine switch, PHP extensions, each rule switch. |
| `wp bfsg remediate <file> [--write]` | Runs `transform()` with the saved switches; warns and stops for a file without `<html>`; lists the rule codes; with `--write` saves the result to the same path and ends in an error when the file is not writable or the write fails. |

---

## 7. Options and uninstall

| Option | Default | Written by | Read by |
| --- | --- | --- | --- |
| `wm_bfsg_remediator_enabled` | `'1'` | settings form | buffer, admin, CLI, adapter, status helper |
| `wm_bfsg_fix_skip_links`, `wm_bfsg_fix_images`, `wm_bfsg_fix_labels`, `wm_bfsg_fix_aria` | `'1'` | settings form | `rules_from_options()` |
| `wm_bfsg_remediator_delete_data_on_uninstall` | `'0'` | settings form | admin, `uninstall.php` |
| `wm_bfsg_fix_headings`, `wm_bfsg_telemetry` | — | legacy versions | removed on opt-in uninstall |

`uninstall.php` preserves all options by default and deletes the eight keys above (site and network) only when `wm_bfsg_remediator_delete_data_on_uninstall` is `'1'`.

---

## 8. Admin

`add_menu_page` with `manage_options`, slug `wm-bfsg-remediator`, position 81; sheets enqueued only on `toplevel_page_wm-bfsg-remediator`. Tabs `overview`, `simulator`, `rules`, `limits` (unknown values fall back to `overview`). The simulator script writes server text with `textContent`. The form is verified with `wm_bfsg_save_nonce` / `wm_bfsg_save_action` and `manage_options`, saves the engine switch, the four rule switches and the uninstall opt-in toggle, and redirects back to the rules tab.

---

## 9. WM Suite Hub spoke

| Method | Behaviour |
| --- | --- |
| `get_health_status()` | `ERROR` without ext-dom or ext-mbstring, `NEEDS_CONFIG` with the engine switched off, `HEALTHY` otherwise. |
| `get_telemetry_metrics()` | `engine_enabled`, `engine_available`, `rules` (the five keys with their switches). |
| `execute_action()` | `self_test` runs all rules on `SELF_TEST_DOCUMENT` and checks the skip link to `#primary`, an `alt`, a labelled control left alone and an `aria-label` on the unnamed one; other actions fail. |
| `get_sbom_data()` | One component with SHA-256 of the main file, SPDX license `GPL-2.0-or-later`, feature properties. |
| `get_dependencies()` | Empty. |

---

## 10. Tests

```bash
php tests/test-suite.php
php tests/test-pot-freshness.php
bash tests/run-mutations.sh
```

The standalone suite stubs the WordPress functions it needs, loads the real hub interface when `../wm-suite-hub` is on disk (and checks parity), and covers each rule, escaping, toggles, REST equivalence with the transformer, WP-CLI output, the rendered admin tabs and widget, the settings save, the adapter, external entities, opt-in vs default uninstall behaviour, developer hooks, Block Bindings and GenerateBlocks dynamic tags, malformed image URLs, flushed and cleaned output buffers, the skip-link id, `--write` on a read-only file and the rule codes.

---

## 11. Security disclosure

See [SECURITY.md](SECURITY.md).
