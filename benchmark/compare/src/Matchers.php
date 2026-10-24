<?php

declare(strict_types=1);

namespace Bench;

final class Matchers
{
    /** @var array<string, class-string<Matcher>> */
    public const ALL = [
        'dromos'    => Matchers\DromosMatcher::class,
        'fastroute' => Matchers\FastRouteMatcher::class,
        'symfony'   => Matchers\SymfonyMatcher::class,
    ];

    public static function create(string $name): Matcher
    {
        $class = self::ALL[$name] ?? throw new \InvalidArgumentException(
            "Unknown matcher \"$name\". Choose from: " . implode(', ', array_keys(self::ALL))
        );
        return new $class();
    }

    /**
     * Fail loudly unless every request finds the expected status, action and
     * parameters, so no matcher is timed while it is doing less than the others.
     */
    public static function verify(Matcher $matcher, RouteSet $routes): void
    {
        foreach ($routes->requests() as $name => [$method, $path, $status, , $action, $params]) {
            $expected = [$status, $action, $params];
            $got = $matcher->match($method, $path);
            if ($got !== $expected) {
                throw new \RuntimeException(sprintf(
                    '%s matcher, %d routes, %s (%s %s): expected %s, got %s',
                    $matcher->name(), $routes->count(), $name, $method, $path, json_encode($expected), json_encode($got)
                ));
            }
        }
    }
}
