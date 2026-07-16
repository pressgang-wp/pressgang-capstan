<?php

namespace PressGang\Capstan\Mcp;

/**
 * The MCP tool surface (ADR 0001 in pressgang-bosun).
 *
 * Every read tool is a THIN PROXY over an existing `wp capstan … --format=json`
 * command, captured in-process via WP_CLI::runcommand(return: stdout) so the
 * proxied command's output never reaches the protocol STDOUT. That is the whole
 * trick: Capstan already produces the structured data — this layer just speaks
 * MCP in front of it.
 *
 * The only genuinely new work is marked TODO: `docs_search` (over the installed
 * api-index.json corpus) and `logs` (over Shakedown's observer signals), plus
 * the write tools gated behind --allow-write.
 */
final class ToolRegistry implements ToolProvider
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function __construct(private bool $allowWrite = false)
    {
        $this->registerReadTools();

        if ($this->allowWrite) {
            $this->registerWriteTools();
        }
    }

    /** @return list<array{name:string,description:string,inputSchema:array}> */
    public function list(): array
    {
        return array_values(array_map(
            static fn (Tool $t): array => [
                'name'        => $t->name,
                'description' => $t->description,
                'inputSchema' => $t->inputSchema,
            ],
            $this->tools,
        ));
    }

    /** @return array{content:list<array>,isError?:bool} */
    public function call(string $name, array $arguments): array
    {
        $tool = $this->tools[$name] ?? null;

        if (! $tool) {
            return $this->text("Unknown tool: {$name}", isError: true);
        }

        return ($tool->handler)($arguments);
    }

    // -- tool definitions -----------------------------------------------------

    private function registerReadTools(): void
    {
        $this->add(
            'pressgang_resolve',
            'Resolve a URL to its template-hierarchy candidates and the winning PressGang controller.',
            [
                'type'       => 'object',
                'properties' => ['url' => ['type' => 'string', 'description' => 'Path relative to site home, e.g. /events/']],
                'required'   => ['url'],
            ],
            fn (array $a): array => $this->proxy('capstan resolve %s --format=json', [$a['url'] ?? '/']),
        );

        $this->add(
            'pressgang_matrix',
            'Enumerate every route the theme serves: post types, taxonomies, templates, menus.',
            ['type' => 'object', 'properties' => (object) []],
            fn (array $a): array => $this->proxy('capstan matrix --resolve --format=json', []),
        );

        $this->add(
            'pressgang_doctor',
            'Run deterministic theme configuration health checks.',
            ['type' => 'object', 'properties' => (object) []],
            fn (array $a): array => $this->proxy('capstan doctor --format=json', []),
        );

        $this->add(
            'pressgang_config',
            'Dump the merged PressGang config the theme boots with (optionally one key).',
            [
                'type'       => 'object',
                'properties' => ['key' => ['type' => 'string', 'description' => 'Optional dotted config key, e.g. menus']],
            ],
            fn (array $a): array => isset($a['key'])
                ? $this->proxy('capstan config dump %s --format=json', [$a['key']])
                : $this->proxy('capstan config dump --format=json', []),
        );

        $this->add(
            'pressgang_snippets',
            'List registered snippets, their resolved classes, and constructor args.',
            ['type' => 'object', 'properties' => (object) []],
            fn (array $a): array => $this->proxy('capstan snippets --format=json', []),
        );

        $this->add(
            'pressgang_context',
            "Show a controller's context manifest: its getters, framework overrides, and unpublished getters.",
            [
                'type'       => 'object',
                'properties' => ['controller' => ['type' => 'string', 'description' => 'Controller name or FQCN, e.g. FrontPage']],
                'required'   => ['controller'],
            ],
            fn (array $a): array => $this->proxy('capstan context %s --format=json', [$a['controller'] ?? '']),
        );

        $this->add(
            'pressgang_about',
            'Capstan version, PHP version, and the detected WordPress root.',
            ['type' => 'object', 'properties' => (object) []],
            fn (array $a): array => $this->proxy('capstan about --format=json', []),
        );

        // The headline capability: search each installed package's
        // docs/api-index.json (versions pinned by vendor = composer.lock),
        // returning ranked hits with signatures and WordPress-doc links.
        $this->add(
            'pressgang_docs_search',
            'Search installed PressGang package API indexes, version-matched to composer.lock.',
            [
                'type'       => 'object',
                'properties' => [
                    'query'   => ['type' => 'string'],
                    'package' => ['type' => 'string', 'description' => 'Optional package filter, e.g. quartermaster'],
                ],
                'required'   => ['query'],
            ],
            fn (array $a): array => $this->data((new \PressGang\Capstan\Support\DocsIndex())->search(
                (string) ($a['query'] ?? ''),
                isset($a['package']) ? (string) $a['package'] : null,
            )),
        );

        // TODO net-new — surface Shakedown's observer.php per-request signals
        // (resolved template/controller, captured PHP notices/errors) as a
        // dev-time logs tool. Optionally fetch `url` and read the observer headers.
        $this->add(
            'pressgang_logs',
            "Recent template/controller resolutions and PHP issues from Shakedown's observer.",
            [
                'type'       => 'object',
                'properties' => [
                    'url'   => ['type' => 'string', 'description' => 'Optional URL to fetch and read observer headers for'],
                    'limit' => ['type' => 'integer'],
                ],
            ],
            fn (array $a): array => $this->text('TODO: read observer.php signals', isError: true),
        );
    }

    private function registerWriteTools(): void
    {
        // TODO (gated, preview-first — honour Capstan's dry-run default):
        //   pressgang_eval  → wp eval in theme context (Tinker analogue)
        //   pressgang_make  → wp capstan make {controller|cpt|block|muster}, preview then --force
    }

    // -- plumbing -------------------------------------------------------------

    private function add(string $name, string $description, array $inputSchema, callable $handler): void
    {
        $this->tools[$name] = new Tool($name, $description, $inputSchema, $handler);
    }

    /**
     * Run a capstan command in-process and wrap its JSON output as MCP content.
     *
     * return: stdout captures what the command would have printed, so the
     * proxied WP_CLI::log() never lands on the protocol channel; exit_error:
     * false stops a WP_CLI::error() inside the command from killing the server;
     * launch: false reuses this process's WordPress bootstrap.
     *
     * (Quoting via escapeshellarg is illustrative — a shipped version should
     * validate/whitelist arguments rather than shell-quote.)
     */
    private function proxy(string $template, array $args): array
    {
        $command = vsprintf($template, array_map('escapeshellarg', $args));

        $json = \WP_CLI::runcommand($command, [
            'return'     => 'stdout',
            'exit_error' => false,
            'launch'     => false,
        ]);

        return $this->text(
            is_string($json) && $json !== '' ? $json : '{"error":"command produced no output"}',
        );
    }

    /** Wrap a structured payload as JSON text content. */
    private function data(array $payload): array
    {
        return $this->text((string) json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    /** @return array{content:list<array>,isError?:bool} */
    private function text(string $text, bool $isError = false): array
    {
        $out = ['content' => [['type' => 'text', 'text' => $text]]];

        if ($isError) {
            $out['isError'] = true;
        }

        return $out;
    }
}
