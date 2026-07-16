<?php

namespace PressGang\Capstan\Tests\Mcp;

use PHPUnit\Framework\TestCase;
use PressGang\Capstan\Mcp\Server;
use PressGang\Capstan\Mcp\ToolProvider;

class ServerTest extends TestCase
{
    /** A provider that echoes, so tools/call can be tested without WordPress. */
    private function tools(): ToolProvider
    {
        return new class implements ToolProvider {
            public function list(): array
            {
                return [['name' => 'echo', 'description' => 'echo back', 'inputSchema' => ['type' => 'object']]];
            }

            public function call(string $name, array $arguments): array
            {
                return ['content' => [['type' => 'text', 'text' => $name . ':' . json_encode($arguments)]]];
            }
        };
    }

    /**
     * Feed newline-delimited JSON-RPC messages through a server and decode the
     * replies. Accepts raw strings (to test malformed input) or arrays.
     *
     * @param array<int, array|string> $messages
     * @return array<int, array>
     */
    private function converse(array $messages): array
    {
        $in = fopen('php://memory', 'r+');
        foreach ($messages as $m) {
            fwrite($in, (is_string($m) ? $m : json_encode($m)) . "\n");
        }
        rewind($in);

        $out = fopen('php://memory', 'r+');
        $log = fopen('php://memory', 'r+');

        (new Server($this->tools(), 'test', '1.2.3'))->serve($in, $out, $log);

        rewind($out);
        $lines = array_filter(explode("\n", (string) stream_get_contents($out)), 'strlen');

        return array_map(static fn (string $l): array => json_decode($l, true), array_values($lines));
    }

    public function testInitializeReturnsServerInfoAndCapabilities(): void
    {
        [$res] = $this->converse([['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => []]]);

        $this->assertSame('2.0', $res['jsonrpc']);
        $this->assertSame(1, $res['id']);
        $this->assertSame('test', $res['result']['serverInfo']['name']);
        $this->assertSame('1.2.3', $res['result']['serverInfo']['version']);
        $this->assertArrayHasKey('tools', $res['result']['capabilities']);
    }

    public function testToolsListReturnsTheCatalogue(): void
    {
        [$res] = $this->converse([['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']]);

        $this->assertSame('echo', $res['result']['tools'][0]['name']);
    }

    public function testToolsCallInvokesTheHandler(): void
    {
        [$res] = $this->converse([
            ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'echo', 'arguments' => ['a' => 1]]],
        ]);

        $this->assertStringContainsString('echo:{"a":1}', $res['result']['content'][0]['text']);
    }

    public function testNotificationsGetNoReply(): void
    {
        $this->assertSame([], $this->converse([['jsonrpc' => '2.0', 'method' => 'notifications/initialized']]));
    }

    public function testUnknownMethodReturnsMethodNotFound(): void
    {
        [$res] = $this->converse([['jsonrpc' => '2.0', 'id' => 9, 'method' => 'no/such']]);

        $this->assertSame(-32601, $res['error']['code']);
    }

    public function testMalformedJsonReturnsParseError(): void
    {
        [$res] = $this->converse(['this is not json']);

        $this->assertSame(-32700, $res['error']['code']);
        $this->assertNull($res['id']);
    }

    public function testProcessesMultipleMessagesInOrder(): void
    {
        $out = $this->converse([
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'],
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'],
        ]);

        // Two replies (the notification is silent), ids preserved and ordered.
        $this->assertCount(2, $out);
        $this->assertSame(1, $out[0]['id']);
        $this->assertSame(2, $out[1]['id']);
    }
}
