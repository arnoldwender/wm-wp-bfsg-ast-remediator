# Architecture — WM BFSG AST Remediator
`wm-bfsg-ast-remediator` · version `1.2.0` from the plugin header · WordPress 6.4+ · PHP 8.1+ · text domain `wm-bfsg-ast-remediator`

Every figure below comes from the code, not from any document — the inventory at the bottom is regenerated automatically from the repository tree.

---

## How a page is rewritten

```
template_redirect          the plugin opens an output buffer over the whole frontend page
                             └─ kill-switch off?  buffer is returned untouched
                             └─ WM_BFSG_DOM_Transformer::transform()
                                  parse with LIBXML_NONET
                                  apply up to five rules, each behind its own switch:
                                    1  lang attribute
                                    2  skip link            (only with a real target)
                                    3  image alt
                                    4  aria-label on unnamed controls
                                    5  navigation names
                                  serialise
                             └─ page goes out

REST simulator (manage_options)   the SAME transform(), so the preview is what visitors get
wp bfsg status | remediate        the SAME transform()
hub self-test                     the SAME transform()
```

The engine is two global classes — `WM_BFSG_Rules_Engine` and `WM_BFSG_DOM_Transformer` — while
`includes/API`, `includes/Admin` and `includes/Adapters` are namespaced. That split is historical,
not a layering rule.

**It audits nothing.** Applying a rule does not assess conformity, and "no rule changed anything"
is not "this page is accessible".

---

## Wiring — what to touch for a given change

| Change | Files, in order | Trap |
|---|---|---|
| Add a rule | `WM_BFSG_Rules_Engine` (the rule) → a switch option → read that switch in the engine → `uninstall.php` → assertion + mutation | groups 14 and 16 fail if a switch the form saves is not read, or an option is missing from `uninstall.php` |
| Change the simulator | nothing — it calls `transform()` | **never add a second implementation.** Group 12 pins that the simulator shows what visitors get; a preview that diverges from the output is worse than no preview |
| Touch serialisation | the tail of `transform()` | see fragile spot 1 |

---

## Fragile spots

1. **Never decode markup back into the page after serialisation.** Only named entities that do **not** decode to `< > & " '` may be decoded. A general `html_entity_decode()` here is what caused the site-wide XSS of 2026-09-13; group 8 is its regression test.
2. **One engine, four callers.** Frontend buffer, REST simulator, WP-CLI and hub self-test all enter through `WM_BFSG_DOM_Transformer::transform()`.
3. **Never overwrite an existing accessible name**, and never inject a skip link without a real target (groups 2 and 11). A wrong name is worse than a missing one: it is announced with confidence.
4. **Parser flags:** `LIBXML_NONET`, never `LIBXML_NOENT` or `LIBXML_DTDLOAD` (XXE). Page-controlled values never reach an XPath expression.
5. **The plugin rewrites every frontend page through an output buffer.** Anything slow or fatal in `transform()` is slow or fatal for the whole site — that is why the kill-switch exists and why it is checked before the parse.
6. **Opt-in uninstall.** `uninstall.php` preserves all saved options by default unless `wm_bfsg_remediator_delete_data_on_uninstall` is explicitly set to `'1'`.

---

<!-- wm-measured-inventory -->

## Measured inventory

Regenerated from the code, never hand-edited. Everything above this line is written by hand and stays.

```bash
python3 the fleet's architecture inventory tool wm-bfsg-ast-remediator --append-to docs/ARCHITECTURE.md
```

## Inventory by layer

**8 PHP files, 1487 lines** (excluding `tests/`, `vendor/`, `node_modules/`, `languages/`).

| Layer | Files | Lines |
|---|---:|---:|
| `(raíz)` | 2 | 266 |
| `includes` | 3 | 572 |
| `includes/API` | 1 | 77 |
| `includes/Adapters` | 1 | 154 |
| `includes/Admin` | 1 | 418 |

---

## Classes

| Class | File |
|---|---|
| `AdminDashboard` | [`includes/Admin/AdminDashboard.php`](../includes/Admin/AdminDashboard.php) |
| `Bfsg_Remediator_Spoke_Adapter` | [`includes/Adapters/Bfsg_Remediator_Spoke_Adapter.php`](../includes/Adapters/Bfsg_Remediator_Spoke_Adapter.php) |
| `RestController` | [`includes/API/RestController.php`](../includes/API/RestController.php) |
| `WM_BFSG_CLI` | [`includes/class-bfsg-cli.php`](../includes/class-bfsg-cli.php) |
| `WM_BFSG_DOM_Transformer` | [`includes/class-bfsg-dom-transformer.php`](../includes/class-bfsg-dom-transformer.php) |
| `WM_BFSG_Rules_Engine` | [`includes/class-bfsg-rules-engine.php`](../includes/class-bfsg-rules-engine.php) |

---

## Entry points

**REST routes:** `/remediate-html`

**WP-CLI:** `bfsg`

---

## Persistence — who writes each key and who reads it

| Key | Written by | Read by |
|---|---|---|
| `wm_bfsg_remediator_delete_data_on_uninstall` | `includes/Admin/AdminDashboard.php` | `includes/Admin/AdminDashboard.php`, `uninstall.php` |
| `wm_bfsg_remediator_enabled` | `includes/Admin/AdminDashboard.php` | `includes/Adapters/Bfsg_Remediator_Spoke_Adapter.php`, `includes/Admin/AdminDashboard.php`, `includes/class-bfsg-cli.php`, `wm-bfsg-ast-remediator.php` |

Every key has exactly one writing layer and at least one reader.


---

## Gates

- `tests/mutations.php`
- `tests/run-mutations.sh`
- `tests/test-pot-freshness.php`
- `tests/test-suite.php`
- `tests/test-ui-family.php`
- `.github/workflows/ci.yml`

What CI actually runs is in the workflow files above — a test file that no workflow invokes is not a gate.

---

## Drift detected against the documentation

Nothing measurable: versions agree and every class named in the documents exists.

