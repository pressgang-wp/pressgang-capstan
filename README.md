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

| Command                       | Description                                                             |
|-------------------------------|-------------------------------------------------------------------------|
| `wp capstan about`            | Display Capstan version, PHP version, and WordPress root detection      |
| `wp capstan new`              | Scaffold a full PressGang WordPress project (core, parent, child theme) |
| `wp capstan make child`       | Scaffold a PressGang child theme from the built-in template             |
| `wp capstan make cpt`         | Scaffold a custom post type entry in config/custom-post-types.php       |
| `wp capstan make block`       | Scaffold an ACF block: block.json, Twig template, config registration   |
| `wp capstan make controller`  | Scaffold a controller with a documented context_getters manifest        |
| `wp capstan make muster`      | Scaffold a development seed (`muster/SiteMuster.php`) from the theme's shape |
| `wp capstan resolve <url>`    | Show template hierarchy candidates and the resolved controller for a URL |
| `wp capstan context <Ctrl>`   | Show a controller's context manifest and getters; --add publishes keys  |
| `wp capstan config dump`      | Display the resolved PressGang configuration                            |
| `wp capstan snippets`         | List registered snippets, their resolved classes, and args              |
| `wp capstan matrix`           | Enumerate the theme's routes (post types, taxonomies, templates, menus)  |
| `wp capstan doctor`           | Run deterministic theme configuration health checks                     |
| `wp capstan theme package`    | Create a WordPress-uploadable ZIP from a theme directory                |

When [Muster](https://github.com/pressgang-wp/pressgang-muster) is installed in the theme, it also registers `wp capstan seed` (run the theme's `SiteMuster` by convention, with a production guard) and `wp capstan muster <class>` (the low-level runner). See the Muster docs for their flags.

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

### Development seeding

Scaffold a `SiteMuster` from the theme's own shape — its post types, taxonomies, page templates, menu locations, and ACF JSON — then seed with [Muster](https://github.com/pressgang-wp/pressgang-muster).

```bash
wp capstan make muster          # preview muster/SiteMuster.php
wp capstan make muster --force  # write it, and print the autoload-dev mapping to add

wp capstan seed                 # run it (requires pressgang-wp/muster in the theme)
```

Seeders live in a top-level `muster/` directory mapped under the theme's composer `autoload-dev` — they are development and test fixtures, not shipped code; `make muster` prints the exact mapping. See the Muster docs for the manifest, Recipes, and seeding flags.

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

Theme utilities:

- `theme screenshot` — generate a theme screenshot
