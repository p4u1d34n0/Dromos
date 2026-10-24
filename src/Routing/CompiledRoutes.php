<?php

declare(strict_types=1);

namespace Dromos\Routing;

/**
 * A compiled route table (see RouteCompiler) that matches request paths.
 *
 * Routes are tried in registration order and the first match wins: a route
 * matches when its URL equals the path, or when its {placeholders} match the
 * path's segments. Each prefix group's regexes are built the first time a
 * path needs them, so a request compiles only the routes it could match.
 * toArray() builds every group and returns the table as plain data for
 * caching; a table read back with fromArray() needs no building.
 *
 * @phpstan-type Parameterised array<int, array{0:string,1:string[],2:int}>
 * @phpstan-type MethodTable array{exact: array<string, int>, prefixes: array<string, string[]|false>, parameterised: Parameterised, urls?: array<string, array<int, array{0:string,1:int}>>}
 * @phpstan-type BuiltMethodTable array{exact: array<string, int>, prefixes: array<string, string[]>, parameterised: Parameterised}
 */
final class CompiledRoutes
{
    /**
     * Per method and path, the match for a path that is an exact route's URL.
     * It never changes, so it's worked out on first lookup and reused.
     *
     * @var array<string, array<string, array{0:string,1:array<string,string>,2:int}>>
     */
    private array $exactMatches = [];

    /**
     * @param array<string, MethodTable> $table Per method: exact URLs, prefix groups
     *        (false until built), built routes' details, and the routes still to build
     */
    private function __construct(private array $table)
    {
    }

    /**
     * @param array<string, MethodTable> $table
     */
    public static function fromArray(array $table): self
    {
        return new self($table);
    }

    /**
     * The table with every group built, as plain arrays and strings,
     * suitable for var_export().
     *
     * @return array<string, BuiltMethodTable>
     */
    public function toArray(): array
    {
        $built = [];
        foreach ($this->table as $method => $table) {
            foreach (array_keys($table['prefixes']) as $prefix) {
                $this->group($method, (string) $prefix);
            }
            /** @var BuiltMethodTable $complete every group was built above */
            $complete = array_diff_key($this->table[$method], ['urls' => true]);
            $built[$method] = $complete;
        }
        return $built;
    }

    /**
     * The route for this method and path, or null if none matches.
     */
    public function match(string $method, string $path): ?RouteMatch
    {
        $found = $this->find($method, $path);
        return $found === null ? null : new RouteMatch($found[0], $found[1]);
    }

    /**
     * match() as plain data, without creating a RouteMatch, for Router,
     * which runs it on every request.
     *
     * @return array{0:string,1:array<string,string>,2:int}|null [route URL, params, position]
     */
    public function find(string $method, string $path): ?array
    {
        $position = $this->table[$method]['exact'][$path] ?? null;
        if ($position !== null) {
            return $this->exactMatches[$method][$path] ??= $this->exactMatch($method, $path, $position);
        }
        return $this->matchParameterised($method, $path);
    }

    /**
     * The match for a path that is the URL of the exact route at $position:
     * that route, unless a parameterised route registered earlier also
     * matches the path, in which case the earlier route wins.
     *
     * @return array{0:string,1:array<string,string>,2:int}
     */
    private function exactMatch(string $method, string $path, int $position): array
    {
        $earlier = $this->matchParameterised($method, $path);
        return $earlier === null || $earlier[2] >= $position ? [$path, [], $position] : $earlier;
    }

    /**
     * The other methods with a route matching this path, in registration order.
     *
     * @return string[]
     */
    public function allowedMethods(string $path, string $except): array
    {
        $allowed = [];
        foreach (array_keys($this->table) as $method) {
            if ($method !== $except && $this->find($method, $path) !== null) {
                $allowed[] = $method;
            }
        }
        return $allowed;
    }

    /**
     * @return array{0:string,1:array<string,string>,2:int}|null [route URL, params, position]
     */
    private function matchParameterised(string $method, string $path): ?array
    {
        $prefixes = $this->table[$method]['prefixes'] ?? [];

        // The longest prefix of the path that has routes holds every route that can match it.
        // The loop is RouteCompiler::parentOf() inlined: this runs on every request
        for ($prefix = $path; !isset($prefixes[$prefix]); $prefix = substr($prefix, 0, (int) strrpos($prefix, '/'))) {
            if ($prefix === '') {
                return null;
            }
        }

        foreach ($prefixes[$prefix] ?: $this->group($method, $prefix) as $regex) {
            if (preg_match($regex, $path, $m)) {
                [$url, $names, $position] = $this->table[$method]['parameterised'][(int) $m['MARK']];
                $params = [];
                foreach ($names as $i => $name) {
                    $params[$name] = $m[$i + 1];
                }
                return [$url, $params, $position];
            }
        }
        return null;
    }

    /**
     * The regexes of a prefix group, built now if they haven't been: its own
     * routes and those of its shorter prefixes, in registration order.
     *
     * @return string[]
     */
    private function group(string $method, string $prefix): array
    {
        $table = &$this->table[$method];
        if ($table['prefixes'][$prefix] !== false) {
            return $table['prefixes'][$prefix];
        }

        $routes = [];
        for ($shorter = $prefix; ; $shorter = RouteCompiler::parentOf($shorter)) {
            $routes += $table['urls'][$shorter] ?? [];
            if ($shorter === '') {
                break;
            }
        }
        ksort($routes);

        [$regexes, $parameterised] = RouteCompiler::group($routes);
        $table['parameterised'] += $parameterised;
        return $table['prefixes'][$prefix] = $regexes;
    }
}
