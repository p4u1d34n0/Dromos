<?php

declare(strict_types=1);

namespace Dromos\Tests;

use Dromos\Http\Request;
use Dromos\Http\Response;
use Dromos\Router;
use Dromos\Tests\Support\RouterTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Checks the compiled matcher against a straightforward linear scan (the
 * matching algorithm Dromos used up to v1.1.0) over random route tables.
 */
#[CoversClass(Router::class)]
final class RouterReferenceTest extends RouterTestCase
{
    private const METHODS = ['GET', 'POST', 'PUT', 'DELETE'];
    private const WORDS = ['users', 'me', 'posts', 'a', 'all', 'v1', 'x.json', 'new'];
    private const VALUES = ['7', 'me', 'all', 'x.json', 'new', 'zz'];

    /** @return array<string, array{int}> */
    public static function seeds(): array
    {
        $seeds = [];
        foreach (range(1, 12) as $seed) {
            $seeds["seed $seed"] = [$seed];
        }
        return $seeds;
    }

    #[Test]
    #[DataProvider('seeds')]
    public function test_it_matches_the_reference_linear_scan(int $seed): void
    {
        mt_srand($seed);

        for ($table = 0; $table < 5; $table++) {
            Router::reset();
            /** @var array<string, array<string, string>> $routes method => [url => url] in registration order */
            $routes = [];
            $count = mt_rand(1, 160);
            for ($i = 0; $i < $count; $i++) {
                $method = self::METHODS[mt_rand(0, 3)];
                $url = $this->randomPath(true);
                Router::{ucfirst(strtolower($method))}(
                    $url,
                    static fn(Request $request, Response $response) =>
                        $response->json(['route' => $url] + $request->getAttributes())
                );
                $routes[$method][$url] = $url;
            }

            $found = 0;
            for ($i = 0; $i < 200; $i++) {
                // Half the requests fill in a registered route, so most of those match
                if ($i % 2 === 0) {
                    $method = self::METHODS[mt_rand(0, 3)];
                    $path = $this->randomPath(false);
                } else {
                    $method = array_rand($routes);
                    $path = $this->fillIn(array_rand($routes[$method]));
                }

                $response = $this->response($method, $path);
                $actual = [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];

                $this->assertSame($this->expected($routes, $method, $path), $this->normalise($actual), "$method $path (seed $seed, table $table)");
                $found += $actual[0] === 200 ? 1 : 0;
            }
            $this->assertGreaterThan(20, $found, "too few requests matched a route to test matching (seed $seed, table $table)");
        }
    }

    // ────────────────────────────────────────────────────────────────────────────────
    // Reference implementation
    // ────────────────────────────────────────────────────────────────────────────────

    /**
     * @param array<string, array<string, string>> $routes
     * @return array{0:int,1:mixed}
     */
    private function expected(array $routes, string $method, string $path): array
    {
        $match = $this->referenceMatch($routes[$method] ?? [], $path);
        if ($match !== null) {
            return [200, ['route' => $match[0]] + $match[1]];
        }

        $allowed = [];
        foreach ($routes as $other => $list) {
            if ($other !== $method && $this->referenceMatch($list, $path) !== null) {
                $allowed[] = $other;
            }
        }
        return $allowed ? [405, $allowed] : [404, null];
    }

    /**
     * The pre-1.2 matcher: try each route in order, exact match first, then
     * a per-route regex, then confirm by rebuilding the URL from the params.
     *
     * @param array<string, string> $list
     * @return array{0:string,1:array<string,string>}|null
     */
    private function referenceMatch(array $list, string $path): ?array
    {
        foreach ($list as $routeUrl) {
            if ($routeUrl === $path) {
                return [$routeUrl, []];
            }

            preg_match_all('/\{([^}]+)\}/', $routeUrl, $matches);
            $parts = preg_split('/(\{[^}]+\})/', $routeUrl, -1, PREG_SPLIT_DELIM_CAPTURE);
            $pattern = '';
            foreach ($parts as $part) {
                $pattern .= preg_match('/^\{[^}]+\}$/', $part) ? '([^/]+)' : preg_quote($part, '#');
            }
            if (!preg_match("#^{$pattern}$#", $path, $values)) {
                continue;
            }
            array_shift($values);
            $params = array_combine($matches[1], $values);
            if (empty($params)) {
                continue;
            }

            $rebuilt = $routeUrl;
            foreach ($params as $name => $value) {
                $rebuilt = str_replace("{{$name}}", $value, $rebuilt);
            }
            if ($rebuilt === $path) {
                return [$routeUrl, $params];
            }
        }
        return null;
    }

    // ────────────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────────────

    /**
     * A random route template (with placeholders, whole-segment or after a
     * literal such as "x.{p1}") or request path (with values, sometimes with
     * a trailing slash).
     */
    private function randomPath(bool $template): string
    {
        $segments = [];
        for ($i = mt_rand(1, 4); $i > 0; $i--) {
            $roll = mt_rand(0, 8);
            if ($roll <= 2) {
                $segments[] = $template ? '{p' . mt_rand(0, 2) . '}' : self::VALUES[mt_rand(0, count(self::VALUES) - 1)];
            } elseif ($roll === 3) {
                $segments[] = 'x.' . ($template ? '{p' . mt_rand(0, 2) . '}' : self::VALUES[mt_rand(0, count(self::VALUES) - 1)]);
            } else {
                $segments[] = self::WORDS[mt_rand(0, count(self::WORDS) - 1)];
            }
        }
        $path = '/' . implode('/', $segments);
        return !$template && mt_rand(0, 9) === 0 ? $path . '/' : $path;
    }

    /** A request path for a route template: each placeholder gets a random value. */
    private function fillIn(string $template): string
    {
        $path = (string) preg_replace_callback('/\{[^}]+\}/', fn() => self::VALUES[mt_rand(0, count(self::VALUES) - 1)], $template);
        return mt_rand(0, 9) === 0 ? $path . '/' : $path;
    }

    /**
     * @param array{0:int,1:mixed} $actual
     * @return array{0:int,1:mixed}
     */
    private function normalise(array $actual): array
    {
        [$status, $body] = $actual;
        return match ($status) {
            200 => [200, $body],
            405 => [405, $body['details']['available_methods']],
            default => [$status, null],
        };
    }
}
