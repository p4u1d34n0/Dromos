<?php

declare(strict_types=1);

namespace Dromos\Routing;

/**
 * Compiles registered route URLs into a CompiledRoutes table.
 *
 * For each method, every route URL goes in a hash map for exact matches.
 * Routes with {placeholders} are grouped by prefix: the whole segments
 * before their first placeholder ("/users" for "/users/{id}/posts"). A path
 * can only match routes whose prefix is one of its own leading segments, so
 * a group's routes, with those of its shorter prefixes, are folded in
 * registration order into combined regexes, where (*MARK) identifies the
 * route that matched. Matching then tries one small group instead of every
 * route, and the first registered route still wins.
 *
 * compile() only sorts the routes into groups; each group's regex is built
 * by group() when CompiledRoutes first needs it. Only the URLs and their
 * order are compiled, never the handlers, so the table is plain data.
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
            $exact = [];
            $urls = [];
            $mark = 0;
            foreach (array_keys($list) as $index => $url) {
                $url = (string) $url;
                $exact[$url] = $index + 1;
                if (str_contains($url, '{')) {
                    $urls[self::prefixOf($url)][$mark++] = [$url, $index + 1];
                }
            }
            $table[$method] = [
                'exact'         => $exact,
                'prefixes'      => array_fill_keys(array_keys($urls), false),
                'parameterised' => [],
                'urls'          => $urls,
            ];
        }
        return CompiledRoutes::fromArray($table);
    }

    /**
     * Build one group: the combined regexes for these routes, and for each
     * route that has placeholders, its URL, placeholder names and position.
     *
     * @param array<int, array{0:string,1:int}> $routes [route URL, position] keyed by mark, in registration order
     * @return array{0:string[],1:array<int, array{0:string,1:string[],2:int}>}
     */
    public static function group(array $routes): array
    {
        $patterns = [];
        $parameterised = [];
        foreach ($routes as $mark => [$url, $position]) {
            $compiled = self::pattern($url);
            if ($compiled === null) {
                continue;
            }
            $parameterised[$mark] = [$url, $compiled[1], $position];
            $patterns[] = $compiled[0] . '(*MARK:' . $mark . ')';
        }

        // (?| ... ) restarts group numbering in each branch, so params are always $m[1..n]
        $regexes = [];
        foreach (array_chunk($patterns, self::ROUTES_PER_REGEX) as $chunk) {
            $regexes[] = '~^(?|' . implode('|', $chunk) . ')$~';
        }
        return [$regexes, $parameterised];
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
    private static function pattern(string $url): ?array
    {
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
