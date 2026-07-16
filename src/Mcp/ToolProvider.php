<?php

namespace PressGang\Capstan\Mcp;

/**
 * The tool surface the {@see Server} talks to. A seam: the server depends on
 * this, not on {@see ToolRegistry}, so the protocol loop is testable with a
 * fake provider that never touches WordPress.
 */
interface ToolProvider
{
    /** @return list<array{name:string,description:string,inputSchema:array}> */
    public function list(): array;

    /** @return array{content:list<array>,isError?:bool} */
    public function call(string $name, array $arguments): array;
}
