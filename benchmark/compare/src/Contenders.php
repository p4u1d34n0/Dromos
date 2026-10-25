<?php

declare(strict_types=1);

namespace Bench;

final class Contenders
{
    /** @var array<string, class-string<Contender>> */
    public const ALL = [
        'dromos'       => Contenders\DromosContender::class,
        'dromos-lean'  => Contenders\DromosLeanContender::class,
        'flight'       => Contenders\FlightContender::class,
        'laravel'      => Contenders\LaravelContender::class,
        'slim'         => Contenders\SlimContender::class,
        'mezzio'       => Contenders\MezzioContender::class,
        'fastroute'    => Contenders\FastRouteContender::class,
        'symfony'      => Contenders\SymfonyContender::class,
    ];

    public static function create(string $name): Contender
    {
        $class = self::ALL[$name] ?? throw new \InvalidArgumentException(
            "Unknown router \"$name\". Choose from: " . implode(', ', array_keys(self::ALL))
        );
        return new $class();
    }

    /**
     * Fail loudly unless every request returns the expected status and body,
     * so no contender is timed while it is doing less than the others.
     */
    public static function verify(Contender $contender, RouteSet $routes): void
    {
        foreach ($routes->requests() as $name => [$method, $path, $status, $body]) {
            [$gotStatus, $gotBody] = $contender->handle($method, $path);
            if ($gotStatus !== $status || ($body !== null && $gotBody !== $body)) {
                throw new \RuntimeException(sprintf(
                    '%s, %d routes, %s (%s %s): expected %d %s, got %d %s',
                    $contender->name(), $routes->count(), $name, $method, $path,
                    $status, $body ?? '(any body)', $gotStatus, $gotBody
                ));
            }
        }
    }
}
