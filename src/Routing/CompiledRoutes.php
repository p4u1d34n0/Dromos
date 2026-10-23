<?php

declare(strict_types=1);

namespace Dromos\Routing;

/**
 * A compiled route table (see RouteCompiler) that matches request paths.
 *
 * Routes are tried in registration order and the first match wins: a route
 * matches when its URL equals the path, or when its {placeholders} match the
 * path's segments. The table holds route URLs only, so it can be exported
 * with toArray() and cached.
 */
final class CompiledRoutes
{
    /**
     * Per method and path, the earliest parameterised route that also matches
     * an exact route's path (or false). Filled in on first lookup.
     *
     * @var array<string, array<string, array{0:string,1:array<string,string>,2:int}|false>>
     */
    private array $shadowedBy = [];

    /**
     * @param array<string, array{exact: array<string, int>, regexes: string[], parameterised: array<int, array{0:string,1:string[],2:int}>}> $table
     */
    private function __construct(private readonly array $table)
    {
    }

    /**
     * @param array<string, array{exact: array<string, int>, regexes: string[], parameterised: array<int, array{0:string,1:string[],2:int}>}> $table
     */
    public static function fromArray(array $table): self
    {
        return new self($table);
    }

    /**
     * The table as plain arrays and strings, suitable for var_export().
     *
     * @return array<string, array{exact: array<string, int>, regexes: string[], parameterised: array<int, array{0:string,1:string[],2:int}>}>
     */
    public function toArray(): array
    {
        return $this->table;
    }

    /**
     * The route for this method and path, or null if none matches.
     */
    public function match(string $method, string $path): ?RouteMatch
    {
        $table = $this->table[$method] ?? null;
        if ($table === null) {
            return null;
        }

        if (isset($table['exact'][$path])) {
            // A parameterised route registered earlier that also matches this path wins
            $earlier = $this->shadowedBy[$method][$path] ??= $this->matchParameterised($table, $path) ?? false;
            if ($earlier === false || $earlier[2] >= $table['exact'][$path]) {
                return new RouteMatch($path);
            }
            return new RouteMatch($earlier[0], $earlier[1]);
        }

        $match = $this->matchParameterised($table, $path);
        return $match === null ? null : new RouteMatch($match[0], $match[1]);
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
            if ($method !== $except && $this->match($method, $path) !== null) {
                $allowed[] = $method;
            }
        }
        return $allowed;
    }

    /**
     * @param array{regexes: string[], parameterised: array<int, array{0:string,1:string[],2:int}>} $table
     * @return array{0:string,1:array<string,string>,2:int}|null [route URL, params, position]
     */
    private function matchParameterised(array $table, string $path): ?array
    {
        foreach ($table['regexes'] as $regex) {
            if (preg_match($regex, $path, $m)) {
                [$url, $names, $position] = $table['parameterised'][(int) $m['MARK']];
                return [$url, array_combine($names, array_slice($m, 1, count($names))), $position];
            }
        }
        return null;
    }
}
