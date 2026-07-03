<?php

namespace PressGang\Capstan\Commands;

use PressGang\Capstan\Support\AcfSetup;
use PressGang\Capstan\Support\Filesystem;
use PressGang\Capstan\Support\Shell;
use PressGang\Capstan\Support\Templates\PresetLoader;
use PressGang\Capstan\Support\Templates\TemplateApplier;
use PressGang\Capstan\Support\Tokens;

/**
 * Scaffold a new PressGang WordPress project.
 *
 * Downloads WordPress core, scaffolds a child theme (which installs the
 * PressGang parent theme via Composer), and optionally configures ACF Pro
 * as a Composer-managed MU-plugin.
 *
 * ## OPTIONS
 *
 * <slug>
 * : Project and child-theme slug in kebab-case (e.g. my-theme).
 *
 * [--path=<path>]
 * : Target directory. Defaults to ./<slug>.
 *
 * [--name=<name>]
 * : Human-readable theme name. Defaults to title-cased slug.
 *
 * [--namespace=<namespace>]
 * : PHP namespace for the child theme. Defaults to PascalCase slug.
 *
 * [--dbname=<dbname>]
 * : Database name. Defaults to slug with underscores.
 *
 * [--dbuser=<dbuser>]
 * : Database user.
 * ---
 * default: root
 * ---
 *
 * [--dbpass=<dbpass>]
 * : Database password.
 * ---
 * default:
 * ---
 *
 * [--dbhost=<dbhost>]
 * : Database host.
 * ---
 * default: localhost
 * ---
 *
 * [--dbprefix=<dbprefix>]
 * : WordPress table prefix.
 * ---
 * default: wp_
 * ---
 *
 * [--url=<url>]
 * : Site URL. Defaults to http://<slug>.test.
 *
 * [--title=<title>]
 * : Site title. Defaults to the theme name.
 *
 * [--admin-user=<admin-user>]
 * : WordPress admin username.
 * ---
 * default: admin
 * ---
 *
 * [--admin-password=<admin-password>]
 * : WordPress admin password. Auto-generated if omitted.
 *
 * [--admin-email=<admin-email>]
 * : WordPress admin email.
 * ---
 * default: admin@example.com
 * ---
 *
 * [--acf]
 * : Configure ACF Pro via Composer as an MU-plugin.
 *
 * [--force]
 * : Execute the plan (default is dry-run).
 *
 * [--dry-run]
 * : Show the execution plan without making changes (default behaviour).
 *
 * ## EXAMPLES
 *
 *     # Preview the plan
 *     wp capstan new my-theme --dbuser=root
 *
 *     # Create the project
 *     wp capstan new my-theme --dbuser=root --force
 *
 *     # With ACF Pro
 *     wp capstan new my-theme --dbuser=root --acf --force
 *
 *     # Custom database and URL
 *     wp capstan new my-theme --dbname=mytheme --dbuser=wp --dbpass=secret --url=http://mytheme.local --force
 *
 * @when before_wp_load
 */
class NewCommand
{
    private Shell $shell;
    private Filesystem $fs;

    public function __construct()
    {
        $this->shell = new Shell();
        $this->fs = new Filesystem();
    }

    /**
     * Entry point invoked by WP-CLI.
     *
     * Validates input, builds a config array, prints the execution plan,
     * and — when --force is passed — runs every setup step in sequence.
     *
     * @param array<int, string>    $args       Positional arguments. Index 0 is the slug.
     * @param array<string, string> $assoc_args Named arguments parsed by WP-CLI.
     * @return void
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        $slug = $args[0];
        $this->validateSlug($slug);
        $this->validateNamespace($assoc_args);
        $this->validateDbPrefix($assoc_args);
        $this->checkDependencies();

        $config = $this->buildConfig($slug, $assoc_args);
        $acf = array_key_exists('acf', $assoc_args);
        $force = array_key_exists('force', $assoc_args);

        $this->checkTargetDir($config['projectDir']);
        $this->printPlan($config, $acf);

        if (! $force) {
            \WP_CLI::log('');
            \WP_CLI::log('Dry run complete. Re-run with --force to execute this plan.');
            return;
        }

        $this->execute($config, $acf);
    }

    /**
     * Validate that the slug is kebab-case.
     *
     * Exits with WP_CLI::error() if the slug contains uppercase letters,
     * underscores, leading/trailing hyphens, or consecutive hyphens.
     *
     * @param string $slug The slug to validate.
     * @return void
     */
    private function validateSlug(string $slug): void
    {
        if (! Tokens::isValidSlug($slug)) {
            \WP_CLI::error(
                'Invalid slug "' . $slug . '": must be kebab-case '
                . '(lowercase letters, numbers, and hyphens; no leading/trailing hyphens).'
            );
        }
    }

    /**
     * Validate that --namespace, if provided, is a legal PHP namespace.
     *
     * Must be one or more PascalCase segments separated by backslashes.
     * Rejects values containing spaces, semicolons, or other characters
     * that would produce invalid PHP when injected into autoload config.
     *
     * @param array<string, string> $assoc_args Named arguments from WP-CLI.
     * @return void
     */
    private function validateNamespace(array $assoc_args): void
    {
        if (! isset($assoc_args['namespace'])) {
            return;
        }

        if (! Tokens::isValidNamespace($assoc_args['namespace'])) {
            \WP_CLI::error(
                'Invalid namespace "' . $assoc_args['namespace'] . '": '
                . 'must be a valid PHP namespace (e.g. MyTheme or Acme\\MyTheme).'
            );
        }
    }

    /**
     * Validate that --dbprefix, if provided, is safe for use as a table prefix.
     *
     * WordPress table prefixes should contain only letters, numbers, and
     * underscores. Other characters can break wp-config.php or SQL queries.
     *
     * @param array<string, string> $assoc_args Named arguments from WP-CLI.
     * @return void
     */
    private function validateDbPrefix(array $assoc_args): void
    {
        if (! isset($assoc_args['dbprefix'])) {
            return;
        }

        if (! preg_match('/^[a-zA-Z0-9_]+$/', $assoc_args['dbprefix'])) {
            \WP_CLI::error(
                'Invalid table prefix "' . $assoc_args['dbprefix'] . '": '
                . 'must contain only letters, numbers, and underscores.'
            );
        }
    }

    /**
     * Verify that required external tools are available.
     *
     * Currently checks for Composer only. WP-CLI is assumed present
     * because this command is running inside it.
     *
     * @return void
     */
    private function checkDependencies(): void
    {
        if ($this->shell->run('command -v composer') !== 0) {
            \WP_CLI::error(
                'Composer not found in PATH. Install it: https://getcomposer.org/download/'
            );
        }
    }

    /**
     * Ensure the target project directory does not already exist.
     *
     * @param string $projectDir Absolute path to the intended project directory.
     * @return void
     */
    private function checkTargetDir(string $projectDir): void
    {
        if (is_dir($projectDir)) {
            \WP_CLI::error(
                'Directory already exists: ' . $projectDir . '. '
                . 'Remove it first or choose a different --path.'
            );
        }
    }

    /**
     * Resolve a path to absolute so all subprocesses and plan output agree.
     *
     * If the path is already absolute it is returned as-is. Relative paths
     * are prefixed with the current working directory. This avoids ambiguity
     * when the path is passed to subprocesses via --path or as a cwd.
     *
     * @param string $path The raw path from the user or default.
     * @return string Absolute path.
     */
    private function resolveAbsolutePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return getcwd() . '/' . $path;
    }

    /**
     * Merge CLI arguments with sensible defaults into a config array.
     *
     * Derives theme name, namespace, database name, URL, and admin password
     * from the slug when the user does not supply them explicitly. A random
     * 16-character hex password is generated when --admin-password is omitted.
     *
     * The project directory is always resolved to an absolute path so that
     * subprocess calls and the dry-run plan show a consistent location.
     *
     * @param string                $slug       The validated kebab-case slug.
     * @param array<string, string> $assoc_args Named arguments from WP-CLI.
     * @return array<string, mixed> Normalised configuration keyed by setting name.
     */
    private function buildConfig(string $slug, array $assoc_args): array
    {
        $name = $assoc_args['name'] ?? Tokens::deriveThemeNameFromSlug($slug);
        $namespace = $assoc_args['namespace'] ?? Tokens::deriveNamespaceFromSlug($slug);
        $projectDir = $assoc_args['path'] ?? getcwd() . '/' . $slug;
        $projectDir = $this->resolveAbsolutePath(rtrim($projectDir, '/'));
        $password = $assoc_args['admin-password'] ?? bin2hex(random_bytes(8));

        return [
            'slug' => $slug,
            'name' => $name,
            'namespace' => $namespace,
            'projectDir' => $projectDir,
            'dbname' => $assoc_args['dbname'] ?? str_replace('-', '_', $slug),
            'dbuser' => $assoc_args['dbuser'] ?? 'root',
            'dbpass' => $assoc_args['dbpass'] ?? '',
            'dbhost' => $assoc_args['dbhost'] ?? 'localhost',
            'dbprefix' => $assoc_args['dbprefix'] ?? 'wp_',
            'url' => $assoc_args['url'] ?? 'http://' . $slug . '.test',
            'title' => $assoc_args['title'] ?? $name,
            'adminUser' => $assoc_args['admin-user'] ?? 'admin',
            'adminPassword' => $password,
            'adminEmail' => $assoc_args['admin-email'] ?? 'admin@example.com',
            'generatedPassword' => ! isset($assoc_args['admin-password']),
        ];
    }

    /**
     * Display the dry-run execution plan.
     *
     * Lists every resolved setting and the numbered steps that --force
     * would perform. This is the default output when --force is omitted.
     *
     * @param array<string, mixed> $config Normalised project configuration.
     * @param bool                 $acf    Whether the ACF step will be included.
     * @return void
     */
    private function printPlan(array $config, bool $acf): void
    {
        \WP_CLI::log('');
        \WP_CLI::log('new — execution plan');
        \WP_CLI::log('');
        \WP_CLI::log('Project directory:  ' . $config['projectDir']);
        \WP_CLI::log('Database:           ' . $config['dbname'] . ' (' . $config['dbuser'] . '@' . $config['dbhost'] . ')');
        \WP_CLI::log('Table prefix:       ' . $config['dbprefix']);
        \WP_CLI::log('Site URL:           ' . $config['url']);
        \WP_CLI::log('Site title:         ' . $config['title']);
        \WP_CLI::log('Admin:              ' . $config['adminUser'] . ' <' . $config['adminEmail'] . '>');
        \WP_CLI::log('Theme slug:         ' . $config['slug']);
        \WP_CLI::log('Theme name:         ' . $config['name']);
        \WP_CLI::log('PHP namespace:      ' . $config['namespace']);
        \WP_CLI::log('ACF Pro:            ' . ($acf ? 'yes' : 'no'));
        \WP_CLI::log('');
        \WP_CLI::log('Steps:');

        $step = 1;
        \WP_CLI::log('  ' . $step++ . '. Download WordPress core');
        \WP_CLI::log('  ' . $step++ . '. Create wp-config.php');
        \WP_CLI::log('  ' . $step++ . '. Create database "' . $config['dbname'] . '"');
        \WP_CLI::log('  ' . $step++ . '. Install WordPress');
        \WP_CLI::log('  ' . $step++ . '. Scaffold child theme "' . $config['slug'] . '"');
        \WP_CLI::log('  ' . $step++ . '. Install child theme Composer dependencies (PressGang parent)');

        if ($acf) {
            \WP_CLI::log('  ' . $step++ . '. Configure ACF Pro as MU-plugin (via Composer)');
        }

        \WP_CLI::log('  ' . $step++ . '. Activate child theme');
    }

    /**
     * Run every setup step in sequence.
     *
     * Called only when --force is passed. Each step exits via
     * WP_CLI::error() on failure, halting the pipeline.
     *
     * @param array<string, mixed> $config Normalised project configuration.
     * @param bool                 $acf    Whether to configure ACF Pro.
     * @return void
     */
    private function execute(array $config, bool $acf): void
    {
        \WP_CLI::log('');

        $this->downloadCore($config);
        $this->createWpConfig($config);
        $this->createDatabase($config);
        $this->installCore($config);
        $this->scaffoldChildTheme($config);
        $this->installChildDependencies($config);

        if ($acf) {
            $this->configureAcf($config);
        }

        $this->activateTheme($config);
        $this->printSummary($config, $acf);
    }

    /**
     * Download WordPress core into the project directory.
     *
     * Runs: wp core download --path=<projectDir>
     *
     * @param array<string, mixed> $config Normalised project configuration.
     * @return void
     */
    private function downloadCore(array $config): void
    {
        \WP_CLI::log('→ Downloading WordPress core...');

        $exitCode = $this->shell->stream(sprintf(
            'wp core download --path=%s',
            escapeshellarg($config['projectDir']),
        ));

        if ($exitCode !== 0) {
            \WP_CLI::error(
                'Failed to download WordPress core. '
                . 'Check your network connection and that wp-cli is installed.'
            );
        }
    }

    /**
     * Generate wp-config.php with the provided database credentials.
     *
     * Runs: wp config create --dbname=… --dbuser=… --dbpass=… --dbhost=… --dbprefix=… --path=…
     *
     * @param array<string, mixed> $config Normalised project configuration.
     * @return void
     */
    private function createWpConfig(array $config): void
    {
        \WP_CLI::log('→ Creating wp-config.php...');

        $exitCode = $this->shell->stream(sprintf(
            'wp config create --dbname=%s --dbuser=%s --dbpass=%s --dbhost=%s --dbprefix=%s --path=%s',
            escapeshellarg($config['dbname']),
            escapeshellarg($config['dbuser']),
            escapeshellarg($config['dbpass']),
            escapeshellarg($config['dbhost']),
            escapeshellarg($config['dbprefix']),
            escapeshellarg($config['projectDir']),
        ));

        if ($exitCode !== 0) {
            \WP_CLI::error('Failed to create wp-config.php. Check your database credentials.');
        }
    }

    /**
     * Create the MySQL/MariaDB database.
     *
     * Runs: wp db create --path=<projectDir>
     * Requires the database server to be running and the user to have CREATE privileges.
     *
     * @param array<string, mixed> $config Normalised project configuration.
     * @return void
     */
    private function createDatabase(array $config): void
    {
        \WP_CLI::log('→ Creating database...');

        $exitCode = $this->shell->stream(sprintf(
            'wp db create --path=%s',
            escapeshellarg($config['projectDir']),
        ));

        if ($exitCode !== 0) {
            \WP_CLI::error(
                'Failed to create database "' . $config['dbname'] . '". '
                . 'Check that the database server is running and your credentials are correct.'
            );
        }
    }

    /**
     * Run the WordPress core installation (creates tables, sets site options, creates admin user).
     *
     * Runs: wp core install --url=… --title=… --admin_user=… --admin_password=… --admin_email=… --path=…
     *
     * @param array<string, mixed> $config Normalised project configuration.
     * @return void
     */
    private function installCore(array $config): void
    {
        \WP_CLI::log('→ Installing WordPress...');

        $exitCode = $this->shell->stream(sprintf(
            'wp core install --url=%s --title=%s --admin_user=%s --admin_password=%s --admin_email=%s --path=%s',
            escapeshellarg($config['url']),
            escapeshellarg($config['title']),
            escapeshellarg($config['adminUser']),
            escapeshellarg($config['adminPassword']),
            escapeshellarg($config['adminEmail']),
            escapeshellarg($config['projectDir']),
        ));

        if ($exitCode !== 0) {
            \WP_CLI::error(
                'WordPress installation failed. '
                . 'Verify the database exists and is accessible with the provided credentials.'
            );
        }
    }

    /**
     * Scaffold the PressGang child theme from the built-in template preset.
     *
     * Loads the "child" preset, builds a token map from the config, and
     * writes the template files into wp-content/themes/<slug>/. Logs each
     * file path as it is written.
     *
     * @param array<string, mixed> $config Normalised project configuration.
     * @return void
     */
    private function scaffoldChildTheme(array $config): void
    {
        \WP_CLI::log('→ Scaffolding child theme "' . $config['slug'] . '"...');

        $presetDir = PresetLoader::resolveBuiltinPath('child');
        $preset = PresetLoader::load($presetDir);

        $tokens = array_merge($preset->defaults, [
            'theme_slug' => $config['slug'],
            'theme_name' => $config['name'],
            'theme_namespace' => $config['namespace'],
            'textdomain' => $config['slug'],
        ]);

        $targetDir = $config['projectDir'] . '/wp-content/themes/' . $config['slug'];

        $applier = new TemplateApplier($preset, $this->fs);
        $written = $applier->apply($tokens, $targetDir);

        foreach ($written as $file) {
            \WP_CLI::log('    ' . $file);
        }
    }

    /**
     * Run composer install in the child theme directory.
     *
     * This installs the PressGang parent theme into wp-content/themes/pressgang/
     * via the child theme's composer.json installer-paths, and sets up PSR-4
     * autoloading for the child theme's src/ directory.
     *
     * @param array<string, mixed> $config Normalised project configuration.
     * @return void
     */
    private function installChildDependencies(array $config): void
    {
        \WP_CLI::log('→ Installing child theme Composer dependencies...');

        $childDir = $config['projectDir'] . '/wp-content/themes/' . $config['slug'];

        $exitCode = $this->shell->stream(
            'composer install --no-interaction',
            $childDir,
        );

        if ($exitCode !== 0) {
            \WP_CLI::error(
                'Failed to install child theme Composer dependencies. '
                . 'Run "composer install" manually in ' . $childDir
            );
        }

        \PressGang\Capstan\Support\BosunBriefing::attempt($childDir);
    }

    /**
     * Write the root composer.json and MU-plugin loader for ACF Pro.
     *
     * Creates two files:
     *   - composer.json at the project root (ACF repository + installer-paths)
     *   - wp-content/mu-plugins/acf-loader.php (bridges WP's subdirectory limitation)
     *
     * ACF Pro itself is not downloaded because it requires licence authentication.
     * The summary output guides the user through the remaining auth + install steps.
     *
     * @param array<string, mixed> $config Normalised project configuration.
     * @return void
     */
    private function configureAcf(array $config): void
    {
        \WP_CLI::log('→ Configuring ACF Pro as MU-plugin...');

        $projectDir = $config['projectDir'];

        $this->fs->writeFile(
            $projectDir . '/composer.json',
            AcfSetup::composerJson($config['slug'], $config['name']),
        );
        \WP_CLI::log('    composer.json');

        $muPluginsDir = $projectDir . '/wp-content/mu-plugins';
        $this->fs->ensureDirectory($muPluginsDir);
        $this->fs->writeFile($muPluginsDir . '/acf-loader.php', AcfSetup::loaderContent());
        \WP_CLI::log('    wp-content/mu-plugins/acf-loader.php');
    }

    /**
     * Activate the child theme via WP-CLI.
     *
     * Uses WP_CLI::warning() rather than WP_CLI::error() on failure because
     * the project is fully usable at this point — the user can activate manually.
     *
     * @param array<string, mixed> $config Normalised project configuration.
     * @return void
     */
    private function activateTheme(array $config): void
    {
        \WP_CLI::log('→ Activating child theme "' . $config['slug'] . '"...');

        $exitCode = $this->shell->stream(sprintf(
            'wp theme activate %s --path=%s',
            escapeshellarg($config['slug']),
            escapeshellarg($config['projectDir']),
        ));

        if ($exitCode !== 0) {
            \WP_CLI::warning(
                'Could not activate theme. Activate it manually: '
                . 'wp theme activate ' . $config['slug'] . ' --path=' . $config['projectDir']
            );
        }
    }

    /**
     * Print the post-install summary with URLs, credentials, and next steps.
     *
     * When --admin-password was omitted and a password was auto-generated,
     * it is displayed here. When --acf was used, the remaining ACF Pro
     * authentication and install steps are printed.
     *
     * @param array<string, mixed> $config Normalised project configuration.
     * @param bool                 $acf    Whether ACF Pro was configured.
     * @return void
     */
    private function printSummary(array $config, bool $acf): void
    {
        \WP_CLI::log('');
        \WP_CLI::success('PressGang project "' . $config['slug'] . '" created at ' . $config['projectDir']);
        \WP_CLI::log('');
        \WP_CLI::log('  URL:       ' . $config['url']);
        \WP_CLI::log('  Admin:     ' . $config['url'] . '/wp-admin');
        \WP_CLI::log('  Username:  ' . $config['adminUser']);

        if ($config['generatedPassword']) {
            \WP_CLI::log('  Password:  ' . $config['adminPassword'] . '  (auto-generated)');
        }

        if ($acf) {
            \WP_CLI::log('');
            \WP_CLI::log('  ACF Pro is configured in composer.json but requires authentication.');
            \WP_CLI::log('  To complete ACF Pro installation:');
            \WP_CLI::log('');
            \WP_CLI::log('    1. Set your license key:');
            \WP_CLI::log('       composer config --global http-basic.connect.advancedcustomfields.com {license-key} {site-url}');
            \WP_CLI::log('');
            \WP_CLI::log('    2. Install the package:');
            \WP_CLI::log('       cd ' . $config['projectDir'] . ' && composer install');
        }

        \WP_CLI::log('');
    }
}
