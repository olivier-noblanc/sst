<?php

namespace App\Router\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    /**
     * @param list<string> $methods
     */
    public function __construct(
        public string $path,
        public string $name,
        public array $methods = ['GET'],
        public ?string $middleware = null,
    ) {}
}
