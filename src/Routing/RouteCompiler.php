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
 *
 * The regexes are grouped by prefix: the whole segments before a route's
 * first placeholder ("/users" for "/users/{id}/posts"). A path can only match
 * routes whose prefix is one of its own leading segments, so each prefix gets
 * regexes holding its routes and those of its shorter prefixes, in
 * registration order. Matching then tries one small group instead of every
 * route, and the first registered route still wins.
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
     * @return array{exact: array<string, int>, prefixes: array<string, string[]>, parameterised: array<int, array{0:string,1:string[],2:int}>}
     */
    private function compileMethod(array $urls): array
    {
        $exact = [];
        $parameterised = [];
        /** @var array<string, array<int, string>> $patternsByPrefix prefix => [mark => pattern] */
        $patternsByPrefix = [];

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
            $patternsByPrefix[self::prefixOf($url)][$mark] = $pattern . '(*MARK:' . $mark . ')';
        }

        $prefixes = [];
        foreach (array_keys($patternsByPrefix) as $prefix) {
            // This prefix's routes plus those of its shorter prefixes, in registration order
            $patterns = [];
            for ($shorter = (string) $prefix; ; $shorter = self::parentOf($shorter)) {
                $patterns += $patternsByPrefix[$shorter] ?? [];
                if ($shorter === '') {
                    break;
                }
            }
            ksort($patterns);

            // (?| ... ) restarts group numbering in each branch, so params are always $m[1..n]
            foreach (array_chunk($patterns, self::ROUTES_PER_REGEX) as $chunk) {
                $prefixes[(string) $prefix][] = '~^(?|' . implode('|', $chunk) . ')$~';
            }
        }

        return ['exact' => $exact, 'prefixes' => $prefixes, 'parameterised' => $parameterised];
    }

    /**
     * The whole segments before a route's first placeholder: "/users" for
     * "/users/{id}" and "/files/report.{ext}", "" for "/{slug}".
     */
    public static function prefixOf(string $url): string
    {
        $static = substr($url, 0, (int) strpos($url, '{'));
        return substr($static, 0, (int) strrpos($static, '/'));
    }

    /**
     * The next shorter prefix: "/a/b" gives "/a", "/a" gives "" (and "" gives "").
     */
    public static function parentOf(string $prefix): string
    {
        return substr($prefix, 0, (int) strrpos($prefix, '/'));
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
