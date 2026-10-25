<?php

namespace Dromos\Middleware;

use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Middleware\RequestHandlerInterface;
use Dromos\Http\Middleware\MiddlewareInterface;

class RequestParameters implements MiddlewareInterface
{
    public function handle(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $request->getAttribute('route'); // assume your router sets this
        $uri   = $request->getUri()->getPath();

        // extract params
        $names = $this->extractNames($route);
        $pattern = $this->makePattern($route);

        if (preg_match("#^$pattern$#", $uri, $matches)) {
            array_shift($matches);
            $params = array_combine($names, $matches);
            $request = $request->withAttribute('params', $params);
        }

        return $handler->handle($request);
    }

    private function extractNames(string $route): array
    {
        preg_match_all('/\{([^}]+)\}/', $route, $m);
        return $m[1];
    }

    private function makePattern(string $route): string
    {
        return preg_replace('/\{([^}]+)\}/', '([^/]+)', $route);
    }
}
