# Capstan Agent Guide

## What Capstan Is

Capstan is the WP-CLI tooling layer for the PressGang ecosystem. It scaffolds, validates, inspects, and manages PressGang-based WordPress projects.

All commands run as:

    wp capstan

Capstan borrows the ergonomics of Artisan, but remains strictly WordPress-native. It understands PressGang conventions and generates correct, wired-up code from those conventions.

Capstan is not:

- A standalone CLI (it was; it is now a WP-CLI extension)
- A per-project Composer dependency (it is installed globally via `wp package install`)
- A build tool or asset compiler
- A replacement for WP-CLI core commands

### Distribution

Capstan is installed as a global WP-CLI package:

    wp package install pressgang-wp/capstan

Global installation is required because scaffolding commands (`wp capstan new`, `wp capstan make child`) run before any project or theme exists — there is no `composer.json` to require Capstan into yet. The `composer.json` declares `"type": "wp-cli-package"` and uses `files` autoload to register commands via `capstan.php`.

Other PressGang packages (e.g. `pressgang-wp/muster`) may register their own subcommands under the `capstan` namespace. WP-CLI merges subcommands from all installed packages, so `wp capstan muster` works without Capstan knowing about muster. Each package owns its own command registration.

---

## Ecosystem Context

Capstan sits alongside these packages — all under `pressgang-wp/`:

| Package         | Install method              | Role                                                                                              |
|-----------------|-----------------------------|---------------------------------------------------------------------------------------------------|
| pressgang       | `composer require` (theme)  | Parent theme framework. Config-driven registration, Timber 2 rendering, controllers as view models. |
| quartermaster   | `composer require` (theme)  | Fluent WP_Query args builder. Standalone, no PressGang coupling.                                  |
| capstan         | `wp package install` (global) | WP-CLI scaffolding, inspection, and theme management commands.                                  |
| muster          | `composer require-dev` (theme) | Data seeding orchestrator. Registers `wp capstan muster` via its own package.                  |

Capstan must understand PressGang's architecture to generate correct scaffolding.

### Key PressGang Conventions Capstan Must Respect

- **Config-driven registration** — `config/*.php` files return arrays; filenames map to `Configuration\*` classes by StudlyCase convention (e.g. `custom-post-types.php` → `CustomPostTypes`)
- **PSR-4 autoloading** — child themes use `src/` with their own namespace
- **Timber-first rendering** — controllers prepare context; Twig templates render it
- **Child theme structure** — `config/`, `src/`, `views/`, `style.css`, `functions.php`, `composer.json`
- **Parent/child separation** — parent provides framework; child provides site-specific config, controllers, templates, branding

---

## Canonical Usage Patterns

### New project (full stack)

```bash
# Dry-run — preview the plan
wp capstan new my-theme --dbuser=root

# Execute
wp capstan new my-theme --dbuser=root --force

# With ACF Pro as MU-plugin
wp capstan new my-theme --dbuser=root --acf --force

# Full customisation
wp capstan new my-theme \
  --dbname=mytheme --dbuser=wp --dbpass=secret \
  --url=http://mytheme.local --title="My Theme" \
  --admin-user=admin --admin-email=dev@example.com \
  --namespace=MyTheme --acf --force
```

`new` orchestrates: `wp core download` → `wp config create` → `wp db create` → `wp core install` → child theme scaffold → `composer install` (pulls PressGang parent) → optional ACF setup → `wp theme activate`.

### Child theme scaffolding

```bash
# Dry-run — preview what would be generated
wp capstan make child my-theme

# Generate the theme
wp capstan make child my-theme --force

# Custom name and namespace
wp capstan make child my-theme --name="My Theme" --namespace=MyTheme --force

# Explicit target path (outside a WordPress project)
wp capstan make child my-theme --path=/srv/www/wp-content/themes --force
```

The child theme's `composer.json` requires `pressgang-wp/pressgang` and uses `installer-paths` to put the parent theme at `../../themes/pressgang/`. Running `composer install` in the child theme directory wires everything up.

### Environment info

```bash
wp capstan about
```

---

## Command Categories

Commands should fall into one of the following categories:

### Project Setup
- `wp capstan new`

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

Capstan does not attempt to bootstrap WordPress itself. Scaffolding commands (`wp capstan new`, `wp capstan make child`) use `@when before_wp_load` because they run before WordPress exists. They rely on WP-CLI's global package loader, not on any project-level autoloading.

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
- Use `WP_CLI::warning()` for non-fatal problems that allow continuation (e.g., theme activation fails but the project is otherwise ready).

### Idempotency

Commands must be idempotent where possible.

Running the same command twice must not corrupt state.

Overwrites require `--force`.

### Dry-Run by Default

Scaffolding and destructive operations should:

- Show an execution plan first.
- Require `--force` to execute.
- Accept `--dry-run` in the docblock for discoverability (it is the default behaviour).

### No Implicit Global State

Commands must not rely on:

- Global variables
- Mutable singletons
- Hidden static state

All context must be resolved explicitly via `ContextResolver`.

### Typing Without strict_types

Do not use `declare(strict_types=1)`.

All code must still be fully typed:

- Type-hint every method parameter
- Type-hint every return type
- Type-hint class properties
- Use union types and nullables where appropriate (`?string`, `int|false`)
- Document complex types (`array<string, mixed>`, `list<string>`) with `@param`/`@return` docblocks

PHP's native type declarations enforce types at the function boundary; `strict_types` only changes whether scalar coercion is allowed. Explicit type-hints give you the safety without the brittleness.

### Docblocks

Every public and protected method must have a docblock with:

- A one-line summary of what it does
- `@param` for each parameter (include type and description)
- `@return` with type and description
- Additional context when behaviour is non-obvious

Private methods: a summary line is sufficient unless the logic warrants more.

Static factory methods (e.g. `AcfSetup::composerJson()`) should document what the output represents, not just the return type.

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
capstan.php                         # WP-CLI bootstrap — guards and registers commands

src/
  CapstanCommand.php                # VERSION constant, namespace anchor

  Commands/
    AboutCommand.php                # wp capstan about
    NewCommand.php                  # wp capstan new
    MakeCommand.php                 # wp capstan make (dispatcher)
    MakeChildCommand.php            # wp capstan make child

  Support/
    AcfSetup.php                    # ACF Pro composer.json + MU-plugin loader content
    Context.php                     # Immutable value object (wpRoot, themesDir, currentDir)
    ContextResolver.php             # Detects WP root by walking upward
    Filesystem.php                  # symfony/filesystem wrapper
    Shell.php                       # symfony/process wrapper (run + stream)
    Tokens.php                      # String derivation (slug → name, slug → namespace)

    Templates/
      Preset.php                    # Template preset metadata value object
      PresetLoader.php              # Loads manifest.json into Preset
      TemplateApplier.php           # Token replacement + file generation

templates/
  child/
    manifest.json                   # Preset config (text_extensions, rename_map, defaults)
    theme/                          # Template files with {{token}} placeholders

tests/
  ApplicationTest.php               # Version, tokens, context resolver tests
  TemplateTest.php                  # Preset loading, token replacement, plan generation
```

---

## Command Registration

Commands are registered in `capstan.php`:

```php
WP_CLI::add_command('capstan about', \PressGang\Capstan\Commands\AboutCommand::class);
WP_CLI::add_command('capstan new', \PressGang\Capstan\Commands\NewCommand::class);
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

## How to Add a New Command (Checklist)

1. **Decide the category** — Project Setup, Scaffolding, Inspection, or Packaging.
2. **Create the command class** in `src/Commands/`. One class per command, single `__invoke` method.
3. **Write the WP-CLI docblock** on the class — `## OPTIONS`, `## EXAMPLES`, `@when before_wp_load` if the command runs without WordPress loaded.
4. **Extract logic into Support** — the command validates input and calls Support classes. Business logic, content generation, and filesystem operations go in `src/Support/`.
5. **Register in `capstan.php`** — one explicit `WP_CLI::add_command()` call.
6. **Implement dry-run by default** — show a plan, require `--force` to execute. Include `--dry-run` in the docblock for discoverability.
7. **Error with actionable messages** — every `WP_CLI::error()` must tell the user what to do next.
8. **Add tests** — Support classes are tested directly via PHPUnit. Command classes are thin and not unit-tested.
9. **Update `AGENTS.md`** — add the command to the Architecture tree and Command Categories.
10. **Run the full suite**:

```bash
vendor/bin/phpunit
```

---

## How to Add a New Template Preset (Checklist)

1. **Create the preset directory** — `templates/<type>/` (e.g. `templates/block/`).
2. **Write `manifest.json`** with:
   - `text_extensions` — file extensions that receive token replacement
   - `rename_map` — source path → target path (may contain `{{tokens}}`)
   - `defaults` — default token values
3. **Create the `theme/` directory** with template files. Use `{{token_name}}` placeholders (snake_case only).
4. **Binary files** (images, fonts) go in the template directory but must not be listed in `text_extensions`.
5. **Load the preset** with `PresetLoader::resolveBuiltinPath($type)` and `PresetLoader::load($dir)`.
6. **Apply the preset** with `TemplateApplier::apply($tokens, $targetDir)`.
7. **Add tests** in `tests/TemplateTest.php` covering:
   - Preset loading and manifest validation
   - Plan generation (file list, rename mapping, tokenisation markers)
   - Token replacement in generated content

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

    templates/<type>/

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

## Testing & Debugging

Tests run via PHPUnit without WordPress or WP-CLI bootstrapped.

Support classes are tested directly. Command classes are thin wrappers and are not unit-tested. All business logic must reside in Support classes so it is testable.

Run:

    vendor/bin/phpunit

### What to test

- **Support classes** — inputs in, outputs out. No mocks needed for most classes.
- **Token derivation** — slug → name, slug → namespace edge cases.
- **Preset loading** — manifest parsing, missing/malformed manifest errors.
- **Template plans** — file listing, rename map application, tokenisation markers.
- **Template application** — token replacement in generated content.
- **New Support classes** — any class added to `src/Support/` must have corresponding tests.

### Debugging scaffolding

Every scaffolding command supports dry-run (the default). Use this to inspect the plan without writing files:

```bash
wp capstan make child my-theme
wp capstan new my-theme --dbuser=root
```

The plan output shows every file that would be created and whether it receives token replacement (`[T]` marker).

---

## Known Issues

### Credentials visible in process list

The `wp capstan new` command passes database credentials and admin passwords as shell arguments to WP-CLI subprocesses (e.g. `wp config create --dbpass=…`). These are momentarily visible in `ps` output. This is a WP-CLI limitation — WP-CLI core commands accept credentials the same way. Mitigation: use environment variables or `wp-cli.yml` for sensitive values in production-like environments.

### Process::fromShellCommandline usage

`Shell::stream()` and `Shell::run()` use `Process::fromShellCommandline()` which passes commands through the system shell. All user-supplied values are escaped with `escapeshellarg()` before interpolation, which prevents injection. The array-based `Process` constructor would provide defense-in-depth but requires restructuring how commands are built throughout the codebase. This is a future improvement opportunity.

### Sequential token replacement

`TemplateApplier` uses `str_replace()` with arrays, which processes replacements sequentially. If a token value itself contains a `{{placeholder}}` pattern matching another token name, it could be double-replaced. In practice this is safe because token values are derived from slugs (kebab-case) and namespaces (PascalCase), neither of which contain `{{…}}` patterns. The risk is theoretical but worth noting for future preset authors.

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
- `src/Support/Templates/*` — template preset loading and application
- `templates/` — preset templates for scaffolding
- `tests/` — PHPUnit tests for Support classes
- `composer.json` — dependencies and autoloading
