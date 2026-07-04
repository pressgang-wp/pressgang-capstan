# Capstan

WP-CLI command package for PressGang WordPress themes.

## What is Capstan

Capstan is a WP-CLI extension that scaffolds, configures, and manages PressGang-based WordPress themes. It provides a repeatable, inspectable workflow for theme development, invoked directly through WP-CLI.

## Requirements

- PHP 8.3+
- [WP-CLI](https://wp-cli.org/) installed and available
- [Composer](https://getcomposer.org/) installed and available

## Installation

Install as a global WP-CLI package:

```bash
wp package install https://github.com/pressgang-wp/pressgang-capstan.git
```

WP-CLI's shorthand package index (`wp package install pressgang-wp/capstan`) is deprecated and may not resolve this package. Use the Git URL command above.

Composer install into the WP-CLI packages directory is also supported:

```bash
mkdir -p ~/.wp-cli/packages && cd ~/.wp-cli/packages && composer require pressgang-wp/capstan:dev-main
```

This makes all `wp capstan` commands available system-wide. Global installation is the recommended approach because scaffolding commands like `wp capstan new` and `wp capstan make child` run before a project or theme exists — there is no project `composer.json` to require Capstan into yet.

### For Capstan development

If you are contributing to Capstan itself, clone the repo and install dependencies locally:

```bash
git clone git@github.com:pressgang-wp/pressgang-capstan.git
cd pressgang-capstan
composer install
```

## Available Commands

| Command                    | Description                                                             |
|----------------------------|-------------------------------------------------------------------------|
| `wp capstan about`         | Display Capstan version, PHP version, and WordPress root detection      |
| `wp capstan new`           | Scaffold a full PressGang WordPress project (core, parent, child theme) |
| `wp capstan make child`    | Scaffold a PressGang child theme from the built-in template             |
| `wp capstan theme package` | Create a WordPress-uploadable ZIP from a theme directory                |

## Usage

### New project

Create a complete PressGang WordPress project in one command — downloads WordPress core, installs the PressGang parent theme via Composer, scaffolds a child theme, and optionally configures ACF Pro as an MU-plugin.

```bash
# Preview the execution plan (dry-run)
wp capstan new my-theme --dbuser=root

# Execute it
wp capstan new my-theme --dbuser=root --force

# With ACF Pro as an MU-plugin
wp capstan new my-theme --dbuser=root --acf --force

# Full customisation
wp capstan new my-theme \
  --dbname=mytheme --dbuser=wp --dbpass=secret --dbhost=localhost \
  --url=http://mytheme.local --title="My Theme" \
  --admin-user=admin --admin-email=dev@example.com \
  --namespace=MyTheme --acf --force
```

The command runs dry by default — review the plan, then re-run with `--force` to execute.

When `--acf` is used, the root `composer.json` and an MU-plugin loader are written but ACF Pro itself is not downloaded (it requires licence authentication). The summary output includes the steps to complete the install.

### Child theme

Scaffold a PressGang child theme into an existing WordPress installation.

```bash
# Preview what would be generated
wp capstan make child my-theme

# Generate the theme
wp capstan make child my-theme --force

# Custom name and namespace
wp capstan make child my-theme --name="My Theme" --namespace=MyTheme --force

# Explicit target path
wp capstan make child my-theme --path=/srv/www/wp-content/themes --force
```

### Theme packaging

Package a theme directory into a zip ready for "Appearance > Themes > Upload Theme" in WordPress admin. Build artifacts (`.git/`, `vendor/`, `node_modules/`, editor dirs, `.env`, dev config files) are automatically excluded.

```bash
# Preview what would be packaged (from inside a theme directory)
wp capstan theme package

# Create the zip
wp capstan theme package --force

# Package a specific directory
wp capstan theme package /path/to/my-theme --force

# Custom output path
wp capstan theme package --output=release/my-theme.zip --force
```

The zip is created alongside the theme directory by default (e.g. `themes/my-theme.zip`).

### Environment info

```bash
wp capstan about
```

## Philosophy

- Dry-run by default — always preview before writing
- Explicit over implicit — no hidden global state
- Minimal abstractions, maximum inspectability
- Global WP-CLI package — available before any project exists

## Roadmap

This roadmap is the single source of truth for planned Capstan commands
(other ecosystem docs link here rather than restating it).

Scaffolding:

- `make block` — scaffold a custom block
- `make cpt` — scaffold a custom post type with config registration

Introspection — designed for humans and AI agents alike; once shipped,
[Bosun](https://github.com/pressgang-wp/pressgang-bosun) fragments will
teach agents these recipes:

- `resolve <url>` — show the template candidates and resolved controller for a URL
- `config dump` — display the resolved PressGang configuration
- `snippets` — list registered snippets and their constructor args
- `context <Controller>` — show a controller's context keys and getters
- `doctor` — diagnose common theme configuration issues

Theme utilities:

- `theme screenshot` — generate a theme screenshot
