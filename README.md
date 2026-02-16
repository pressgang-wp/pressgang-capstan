# Capstan

WP-CLI command package for PressGang WordPress themes.

## What is Capstan

Capstan is a WP-CLI extension that scaffolds, configures, and manages PressGang-based WordPress themes. It provides a repeatable, inspectable workflow for theme development, invoked directly through WP-CLI.

## Requirements

- PHP 8.2+
- [WP-CLI](https://wp-cli.org/) installed and available
- Run from within a WordPress project (or pass `--path` where required)

## Installation

```bash
composer require --dev pressgang-wp/capstan
```

## Usage

```bash
# Show environment info
wp capstan about
```

## Available Commands

| Command | Description |
|---|---|
| `wp capstan about` | Display Capstan version, PHP version, and WordPress root detection |

## Philosophy

- Explicit over implicit — no hidden global state
- Minimal abstractions, maximum inspectability
- Composer-native distribution via WP-CLI

## Roadmap

Planned commands:

- `make child` — full template scaffolding with token replacement
- `make component` — generate Twig/PHP component pairs
- `lint` — validate theme structure against PressGang conventions
- `doctor` — diagnose common theme configuration issues
- `config dump` — display resolved PressGang configuration
