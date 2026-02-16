# Capstan Agent Guide

## What Capstan Is

Capstan is the WP-CLI tooling layer for the PressGang ecosystem. It scaffolds, validates, inspects, and packages PressGang-based WordPress projects.

All commands run as:

    wp capstan

Capstan borrows the ergonomics of Artisan, but remains strictly WordPress-native. It understands PressGang conventions and generates correct, wired-up code from those conventions.

Capstan is not:

- A standalone CLI (it was; it is now a WP-CLI extension)
- A runtime dependency (it is require-dev only)
- A build tool or asset compiler
- A replacement for WP-CLI core commands

---

## Ecosystem Context

Capstan sits alongside these packages — all under `pressgang-wp/`:

| Package         | Role                                                                                             |
|-----------------|--------------------------------------------------------------------------------------------------|
| pressgang       | Parent theme framework. Config-driven registration, Timber 2 rendering, controllers as view models. |
| quartermaster   | Fluent WP_Query args builder. Standalone, no PressGang coupling.                                 |
| capstan         | WP-CLI scaffolding, inspection, and theme management commands.                                   |

Capstan must understand PressGang's architecture to generate correct scaffolding.

### Key PressGang Conventions Capstan Must Respect

- **Config-driven registration** — `config/*.php` files return arrays; filenames map to `Configuration\*` classes by StudlyCase convention (e.g. `custom-post-types.php` → `CustomPostTypes`)
- **PSR-4 autoloading** — child themes use `src/` with their own namespace
- **Timber-first rendering** — controllers prepare context; Twig templates render it
- **Child theme structure** — `config/`, `src/`, `views/`, `style.css`, `functions.php`, `composer.json`
- **Parent/child separation** — parent provides framework; child provides site-specific config, controllers, templates, branding

---

## Command Categories

Commands should fall into one of the following categories:

### Scaffolding
- `wp capstan make child`
- `wp capstan make block`
- `wp capstan make cpt`

### Inspection
- `wp capstan about`
- `wp capstan doctor`
- `wp capstan config dump`

### Packaging
- `wp capstan theme package`
- `wp capstan theme screenshot`

New commands must fit one of these categories.

---

## Design Rules

### WP-CLI Only

All commands run under WP-CLI.

The bootstrap file (`capstan.php`) must guard with:

    defined('WP_CLI') && WP_CLI

It must do nothing outside that context.

### No Side Effects on Load

`capstan.php` registers commands and nothing more.

No filesystem writes.
No shell commands.
No runtime mutations.

### WordPress-Native Assumption

Capstan assumes it runs inside a WordPress project unless explicitly told otherwise via `--path`.

Capstan does not attempt to bootstrap WordPress itself.

### Explicit Command Registration

Each command is registered with a full:

    WP_CLI::add_command()

call in `capstan.php`.

No magic subcommand discovery.
No method-name inference.

### Fail with Actionable Messages

When a command cannot proceed:

- Use `WP_CLI::error()` for fatal failures.
- Tell the user exactly what to do (e.g., "Pass --path if not inside a WP project.").

### Idempotency

Commands must be idempotent where possible.

Running the same command twice must not corrupt state.

Overwrites require `--force`.

### Dry-Run by Default

Scaffolding and destructive operations should:

- Show an execution plan first.
- Require `--force` to overwrite.

### No Implicit Global State

Commands must not rely on:

- Global variables
- Mutable singletons
- Hidden static state

All context must be resolved explicitly via `ContextResolver`.

### Support Layer Is Framework-Agnostic

Classes under `src/Support/`:

- Must not depend on WP-CLI.
- Must not depend on WordPress runtime.
- Must be plain PHP.
- Must be testable without WordPress bootstrapped.

### Separation of Concerns

Command classes should:

- Validate input
- Resolve context
- Call Support layer methods
- Handle WP_CLI output

Command classes must not:

- Perform filesystem operations directly
- Perform token replacement directly
- Execute shell commands directly
- Contain business logic

All logic belongs in `src/Support/`.

---

## Architecture

```
capstan.php                    # WP-CLI bootstrap — guards and registers commands

src/
  CapstanCommand.php           # VERSION constant, namespace anchor

  Commands/
    AboutCommand.php           # wp capstan about
    …

  Support/
    Context.php                # Immutable value object (wpRoot, themesDir, currentDir)
    ContextResolver.php        # Detects WP root by walking upward
    Filesystem.php             # symfony/filesystem wrapper
    Shell.php                  # symfony/process wrapper
    Tokens.php                 # String derivation (slug → name, slug → namespace)

templates/
  child/
    grunt/
      manifest.json
      theme/                   # Template files with {{token}} placeholders

tests/
  ApplicationTest.php          # Unit tests for support classes
```

---

## Command Registration

Commands are registered in `capstan.php`:

```php
WP_CLI::add_command('capstan about', \PressGang\Capstan\Commands\AboutCommand::class);
```

Each command class implements:

```php
__invoke(array $args, array $assoc_args): void
```

Use WP-CLI docblock format for:

- `## OPTIONS`
- `## EXAMPLES`
- `@when before_wp_load`

WP-CLI parses these for `--help`.

---

## ContextResolver

`ContextResolver::resolve($cwd)` returns a `Context` value object.

Detection walks upward from `$cwd` looking for:

- `wp-config.php`
- or `wp-content/`

It determines:

- WordPress root
- Themes directory (`<root>/wp-content/themes`)

If detection fails, return a `Context` with null values and let the command decide how to proceed.

---

## Template Presets

Each preset lives under:

    templates/<type>/<preset>/

Required structure:

    manifest.json
    theme/

### manifest.json Contract

Must define:

- `text_extensions` — array
- `rename_map` — object\<string, string\>
- `defaults` — object\<string, string\>

Example:

```json
{
  "text_extensions": ["php", "css", "json", "twig", "md"],
  "rename_map": {},
  "defaults": {}
}
```

### Token Rules

- Format: `{{token_name}}`
- snake_case only
- Tokens are replaced before `rename_map` is applied
- Binary files must never be processed for token replacement

---

## PressGang Child Theme Anatomy

When Capstan scaffolds a child theme, it should produce:

```
my-theme/
  style.css
  functions.php
  composer.json
  config/
  src/
  views/
```

### Key Conventions

- `THEMENAME` constant = slug (text domain)
- `THEMENAMESPACE` constant = PascalCase namespace
- `functions.php` loads `vendor/autoload.php`
- `composer.json`:
  - `"type": "wordpress-theme"`
  - Requires `pressgang-wp/pressgang`
  - Defines PSR-4 namespace

---

## Testing

Tests run via PHPUnit without WordPress or WP-CLI bootstrapped.

Support classes are tested directly.

Command classes are thin wrappers and are not unit-tested.

All business logic must reside in Support classes.

Run:

    vendor/bin/phpunit

---

## Versioning

Capstan follows SemVer.

- Minor versions may add new commands.
- Patch versions must not change existing command behavior.
- Breaking command signature changes require a major version.

CLI stability matters.

---

## Non-Goals

Capstan will not:

- Replace WP-CLI core commands
- Manage plugin installation
- Compile assets
- Introduce a runtime container or service layer
- Become a general-purpose task runner

---

## Roadmap Principles

New commands must:

- Solve a recurring problem across PressGang projects
- Encode a PressGang convention
- Be deterministic and inspectable
- Avoid introducing new global assumptions
- Preserve idempotency

---

## Where to Look

- `capstan.php` — command registration
- `src/Commands/*` — command implementations
- `src/Support/*` — framework-agnostic utilities
- `templates/` — preset templates for scaffolding
- `composer.json` — dependencies and autoloading
