<?php

declare(strict_types=1);

namespace Dromos\Routing;

/**
 * The route a request matched: the route URL as registered and the values
 * captured by its {placeholders}.
 */
final readonly class RouteMatch
{
    /**
     * @param string                $url    Route URL as registered, e.g. "/users/{id}"
     * @param array<string, string> $params Placeholder values, e.g. ['id' => '42']
     */
    public function __construct(
        public string $url,
        public array $params = [],
    ) {
    }
}
