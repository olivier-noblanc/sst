<?php
/** Pipeline — Simple chain-of-responsibility middleware runner. */

namespace App\Middleware;

class Pipeline
{
    private array $middlewares = [];

    public function pipe(callable $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function run(callable $final): mixed
    {
        $pipeline = $final;

        foreach (array_reverse($this->middlewares) as $middleware) {
            $next = $pipeline;
            $pipeline = function ($request) use ($middleware, $next) {
                return $middleware($request, $next);
            };
        }

        return $pipeline(null);
    }
}
