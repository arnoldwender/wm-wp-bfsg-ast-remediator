# Contributing to WM BFSG AST Remediator

Thank you for your interest in contributing to **WM BFSG AST Remediator**.

---

## Zero Support & Issue Scope

**Zero technical, configuration, or private support is provided.** See [SUPPORT.md](SUPPORT.md):

- Report reproducible code defects **exclusively** via public [GitHub Issues](https://github.com/arnoldwender/wm-wp-bfsg-ast-remediator/issues).
- **Do not** contact the author privately via email, `arnoldwender.com`, or direct messages — private inquiries are never answered.
- Issues and pull requests carry no response or maintenance SLA, and the software is provided strictly **"AS IS"** without warranty or liability.

---

## Core Development Standards

1. **PHP 8.1+ strict typing:** every PHP file of the plugin starts with `declare(strict_types=1);` and a direct-access guard (the standalone tests in `tests/` have no guard; they are not in the package).
2. **Self-hosted assets:** no CDNs, Google Fonts or external telemetry loaded by the plugin.
3. **One engine:** frontend, simulator, WP-CLI and hub self-test call `WM_BFSG_DOM_Transformer::transform()`; no parallel implementation.
4. **Safe serialisation:** never decode entities that turn escaped text into markup; parse with `LIBXML_NONET`; keep page values out of XPath expressions.
5. **Do no harm to names:** a rule never replaces an existing accessible name and never adds a link without a target.
6. **No compliance claims** in the admin screen, CLI output, telemetry or docs.

---

## Development Workflow

```bash
# 1. Clone the repository
git clone https://github.com/arnoldwender/wm-wp-bfsg-ast-remediator.git
cd wm-bfsg-ast-remediator

# 2. Run the standalone suites
php tests/test-suite.php
```

---

## Commit Conventions

- Format: `[Feature] ...`, `[Fix] ...`, `[Security] ...`, `[Docs] ...`.
- A pull request that changes a rule adds a document to `tests/test-suite.php` that shows the change and one that shows what the rule must leave alone, and updates README.md, DEVELOPERS.md and CHANGELOG.md.

For support and liability rules, read [SUPPORT.md](SUPPORT.md).
