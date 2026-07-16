<?php

namespace PressGang\Capstan\Mcp;

/**
 * Minimal MCP stdio server: a JSON-RPC 2.0 message loop over STDIN/STDOUT.
 *
 * Transport contract (MCP stdio):
 *  - one JSON-RPC message per line, UTF-8, no embedded newlines;
 *  - STDOUT carries protocol messages ONLY — all logging goes to STDERR;
 *  - requests carry an `id` and expect a response; notifications carry none
 *    and get no reply.
 *
 * This is deliberately tiny: it handles the three methods an editor needs to
 * discover and call tools (`initialize`, `tools/list`, `tools/call`) plus
 * `ping`, and delegates every tool to the {@see ToolRegistry}.
 */
final class Server
{
    private const PROTOCOL_VERSION = '2025-06-18';

    public function __construct(
        private ToolProvider $tools,
        private string $name = 'pressgang-capstan',
        private string $version = 'dev',
    ) {
    }

    /**
     * Block reading messages until the input stream closes.
     *
     * @param resource $in  protocol input  (STDIN)
     * @param resource $out protocol output (STDOUT)
     * @param resource $log diagnostics     (STDERR)
     */
    public function serve($in, $out, $log): void
    {
        while (($line = fgets($in)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $message = json_decode($line, true);
            if (! is_array($message)) {
                $this->write($out, $this->error(null, -32700, 'Parse error'));
                continue;
            }

            try {
                $response = $this->dispatch($message);
            } catch (\Throwable $e) {
                fwrite($log, '[capstan mcp] ' . $e->getMessage() . "\n");
                $response = $this->error($message['id'] ?? null, -32603, 'Internal error: ' . $e->getMessage());
            }

            if ($response !== null) {
                $this->write($out, $response);
            }
        }
    }

    private function dispatch(array $m): ?array
    {
        $id     = $m['id'] ?? null;
        $method = $m['method'] ?? '';

        return match ($method) {
            'initialize' => $this->result($id, [
                'protocolVersion' => self::PROTOCOL_VERSION,
                'capabilities'    => ['tools' => ['listChanged' => false]],
                'serverInfo'      => ['name' => $this->name, 'version' => $this->version],
            ]),
            'tools/list' => $this->result($id, ['tools' => $this->tools->list()]),
            'tools/call' => $this->result($id, $this->tools->call(
                $m['params']['name'] ?? '',
                $m['params']['arguments'] ?? [],
            )),
            'ping' => $this->result($id, (object) []),
            // notifications/* (initialized, cancelled, …) are fire-and-forget.
            default => str_starts_with($method, 'notifications/')
                ? null
                : $this->error($id, -32601, 'Method not found: ' . $method),
        };
    }

    /** Build a result envelope, or null when the message was a notification. */
    private function result(mixed $id, mixed $result): ?array
    {
        if ($id === null) {
            return null;
        }

        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    private function error(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }

    /** @param resource $out */
    private function write($out, array $message): void
    {
        fwrite($out, json_encode($message, JSON_UNESCAPED_SLASHES) . "\n");
    }
}
