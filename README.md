# Capstan

WP-CLI command package for PressGang WordPress themes.

## What is Capstan

Capstan is a WP-CLI extension that scaffolds, configures, and manages PressGang-based WordPress themes. It provides a repeatable, inspectable workflow for theme development, invoked directly through WP-CLI.

## Requirements

- PHP 8.2+
- [WP-CLI](https://wp-cli.org/) installed and available
- [Composer](https://getcomposer.org/) installed and available

## Installation

```bash
composer require --dev pressgang-wp/capstan
```

## Available Commands

| Command                 | Description                                                             |
|-------------------------|-------------------------------------------------------------------------|
| `wp capstan about`      | Display Capstan version, PHP version, and WordPress root detection      |
| `wp capstan new`        | Scaffold a full PressGang WordPress project (core, parent, child theme) |
| `wp capstan make child` | Scaffold a PressGang child theme from the built-in template             |

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

### Environment info

```bash
wp capstan about
```

## Philosophy

- Dry-run by default — always preview before writing
- Explicit over implicit — no hidden global state
- Minimal abstractions, maximum inspectability
- Composer-native distribution via WP-CLI

## Roadmap

Planned commands:

- `make block` — scaffold a custom block
- `make cpt` — scaffold a custom post type with config registration
- `doctor` — diagnose common theme configuration issues
- `config dump` — display resolved PressGang configuration
