<?php

declare(strict_types=1);

namespace Dromos\Routing;

/**
 * Compiles registered route URLs into a CompiledRoutes table.
 *
 * For each method, every route URL goes in a hash map for exact matches, and
 * routes with {placeholders} are folded, in registration order, into combined
 * regexes where (*MARK) identifies the route that matched. Only the URLs and
 * their order are compiled, never the handlers, so the table is plain data.
 */
final class RouteCompiler
{
    /** Parameterised routes per combined regex, keeping each pattern well inside PCRE's size limit. */
    public const ROUTES_PER_REGEX = 50;

    /**
     * @param array<string, array<string, mixed>> $routes Route URLs keyed by method, in registration order
     *                                                     (values are ignored)
     */
    public function compile(array $routes): CompiledRoutes
    {
        $table = [];
        foreach ($routes as $method => $list) {
            $table[$method] = $this->compileMethod(array_keys($list));
        }
        return CompiledRoutes::fromArray($table);
    }

    /**
     * @param array<int, string|int> $urls
     * @return array{exact: array<string, int>, regexes: string[], parameterised: array<int, array{0:string,1:string[],2:int}>}
     */
    private function compileMethod(array $urls): array
    {
        $exact = [];
        $parameterised = [];
        $patterns = [];

        foreach ($urls as $index => $url) {
            $url = (string) $url;
            $position = $index + 1;
            $exact[$url] = $position;

            $compiled = $this->compilePattern($url);
            if ($compiled === null) {
                continue;
            }
            [$pattern, $names] = $compiled;

            $mark = count($parameterised);
            $parameterised[$mark] = [$url, $names, $position];
            $patterns[] = $pattern . '(*MARK:' . $mark . ')';
        }

        // (?| ... ) restarts group numbering in each branch, so params are always $m[1..n]
        $regexes = [];
        foreach (array_chunk($patterns, self::ROUTES_PER_REGEX) as $chunk) {
            $regexes[] = '~^(?|' . implode('|', $chunk) . ')$~';
        }

        return ['exact' => $exact, 'regexes' => $regexes, 'parameterised' => $parameterised];
    }

    /**
     * Turn a route URL into a regex fragment, or null if it has no placeholders.
     *
     * @return array{0:string,1:string[]}|null [pattern, placeholder names]
     */
    private function compilePattern(string $url): ?array
    {
        if (!str_contains($url, '{')) {
            return null;
        }

        // Odd entries are the {placeholders}, even entries the literal text between them
        $parts = preg_split('/(\{[^}]+\})/', $url, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false || count($parts) === 1) {
            return null;
        }

        $pattern = '';
        $groups = [];
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $pattern .= preg_quote($part, '~');
                continue;
            }
            $name = substr($part, 1, -1);
            if (isset($groups[$name])) {
                // A repeated placeholder must capture the same value each time
                $pattern .= '\g{' . $groups[$name] . '}';
                continue;
            }
            $groups[$name] = count($groups) + 1;
            $pattern .= '([^/]+)';
        }

        return [$pattern, array_keys($groups)];
    }
}
