<?php

declare(strict_types=1);

namespace PressGang\Capstan\Support;

final readonly class Context
{
    public function __construct(
        public ?string $wpRoot,
        public ?string $themesDir,
        public string $currentDir,
    ) {}
}
