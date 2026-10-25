<?php

declare(strict_types=1);

namespace Dromos\Tests\Routing;

use Dromos\Routing\CompiledRoutes;
use Dromos\Routing\RouteCompiler;
use Dromos\Routing\RouteMatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteCompiler::class)]
#[CoversClass(CompiledRoutes::class)]
#[CoversClass(RouteMatch::class)]
final class CompiledRoutesTest extends TestCase
{
    #[Test]
    public function test_it_returns_the_matched_url_and_parameters(): void
    {
        $routes = $this->compile(['GET' => ['/users', '/users/{id}', '/users/{id}/posts/{post}']]);

        $this->assertEquals(new RouteMatch('/users'), $routes->match('GET', '/users'));
        $this->assertEquals(new RouteMatch('/users/{id}', ['id' => '4']), $routes->match('GET', '/users/4'));
        $this->assertEquals(
            new RouteMatch('/users/{id}/posts/{post}', ['id' => '4', 'post' => '2']),
            $routes->match('GET', '/users/4/posts/2')
        );
        $this->assertNull($routes->match('GET', '/nope'));
        $this->assertNull($routes->match('POST', '/users'));
    }

    #[Test]
    public function test_it_lists_other_methods_that_match_a_path(): void
    {
        $routes = $this->compile([
            'GET'    => ['/users/{id}'],
            'PUT'    => ['/users/{id}'],
            'DELETE' => ['/teams/{id}'],
        ]);

        $this->assertSame(['PUT'], $routes->allowedMethods('/users/1', 'GET'));
        $this->assertSame(['GET', 'PUT'], $routes->allowedMethods('/users/1', 'POST'));
        $this->assertSame([], $routes->allowedMethods('/nope', 'POST'));
    }

    #[Test]
    public function test_repeated_lookups_of_an_exact_route_give_the_same_answer_for_each_method(): void
    {
        // GET /a/b is shadowed by the earlier GET /a/{x}; POST /a/b is not
        $routes = $this->compile(['GET' => ['/a/{x}', '/a/b'], 'POST' => ['/a/b']]);

        foreach ([1, 2] as $lookup) {
            $this->assertEquals(new RouteMatch('/a/{x}', ['x' => 'b']), $routes->match('GET', '/a/b'), "GET, lookup $lookup");
            $this->assertEquals(new RouteMatch('/a/b'), $routes->match('POST', '/a/b'), "POST, lookup $lookup");
        }
    }

    #[Test]
    public function test_a_later_exact_route_is_not_answered_for_a_path_of_another_exact_route(): void
    {
        $routes = $this->compile(['GET' => ['/a', '/b']]);

        $this->assertSame('/a', $routes->match('GET', '/a')?->url);
        $this->assertSame('/b', $routes->match('GET', '/b')?->url);
        $this->assertSame('/a', $routes->match('GET', '/a')?->url);
    }

    #[Test]
    public function test_parameterised_routes_are_split_across_regexes(): void
    {
        $urls = array_map(fn(int $i) => "/r/{id}/v$i", range(1, RouteCompiler::ROUTES_PER_REGEX * 2 + 1));
        $routes = $this->compile(['GET' => $urls]);

        $this->assertCount(3, $routes->toArray()['GET']['prefixes']['/r']);
        $this->assertSame('/r/{id}/v101', $routes->match('GET', '/r/x/v101')?->url);
    }

    #[Test]
    public function test_regexes_are_grouped_by_prefix_and_include_shorter_prefixes(): void
    {
        $prefixes = $this->compile(['GET' => ['/{slug}/edit', '/users/{id}', '/users/{id}/posts/{post}', '/teams/{id}']])
            ->toArray()['GET']['prefixes'];

        $this->assertSame(['', '/users', '/teams'], array_keys($prefixes));
        $this->assertSame(1, substr_count($prefixes['']['0'], '(*MARK'), 'the root group holds only root routes');
        $this->assertSame(3, substr_count($prefixes['/users']['0'], '(*MARK'), 'its own two routes and the root route');
        $this->assertSame(2, substr_count($prefixes['/teams']['0'], '(*MARK'));
    }

    #[Test]
    public function test_a_route_with_a_shorter_prefix_still_wins_when_registered_first(): void
    {
        $first = $this->compile(['GET' => ['/{slug}/edit', '/users/{id}']]);
        $this->assertSame('/{slug}/edit', $first->match('GET', '/users/edit')?->url);
        $this->assertSame('/users/{id}', $first->match('GET', '/users/7')?->url);
        $this->assertSame('/{slug}/edit', $first->match('GET', '/teams/edit')?->url, 'a path whose prefix has no group of its own');

        $second = $this->compile(['GET' => ['/users/{id}', '/{slug}/edit']]);
        $this->assertSame('/users/{id}', $second->match('GET', '/users/edit')?->url);
    }

    #[Test]
    public function test_a_path_matches_through_its_longest_prefix_with_routes(): void
    {
        $routes = $this->compile(['GET' => ['/a/{x}', '/a/b/c/{y}']]);

        $this->assertSame(['/a/{x}', ['x' => 'b']], [$routes->match('GET', '/a/b')?->url, $routes->match('GET', '/a/b')?->params]);
        $this->assertSame('/a/b/c/{y}', $routes->match('GET', '/a/b/c/d')?->url);
        $this->assertNull($routes->match('GET', '/a/b/c'), '"/a/b" has no group, so "/a" is used and does not match');
        $this->assertNull($routes->match('GET', 'a/b'), 'a path without a leading slash');
        $this->assertNull($routes->match('GET', ''));
    }

    /** @return array<string, array{string, string}> */
    public static function prefixes(): array
    {
        return [
            'one static segment' => ['/users/{id}', '/users'],
            'several'            => ['/api/v1/users/{id}/posts', '/api/v1/users'],
            'placeholder first'  => ['/{slug}', ''],
            'mid-segment'        => ['/files/report.{ext}', '/files'],
            'no leading slash'   => ['users/{id}', 'users'],
            'no slash at all'    => ['{id}', ''],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('prefixes')]
    public function test_the_prefix_is_the_whole_segments_before_the_first_placeholder(string $url, string $prefix): void
    {
        $this->assertSame($prefix, RouteCompiler::prefixOf($url));
    }

    #[Test]
    public function test_parent_of_drops_the_last_segment(): void
    {
        $this->assertSame('/a', RouteCompiler::parentOf('/a/b'));
        $this->assertSame('', RouteCompiler::parentOf('/a'));
        $this->assertSame('', RouteCompiler::parentOf(''));
        $this->assertSame('/a', RouteCompiler::parentOf('/a/'));
    }

    #[Test]
    public function test_an_exported_table_matches_exactly_like_the_original(): void
    {
        $original = $this->compile([
            'GET'  => ['/users/{id}', '/users/me', '/a/{x}/{x}', '/files/{name}.json', '/'],
            'POST' => ['/users'],
        ]);

        $exported = var_export($original->toArray(), true);
        $restored = CompiledRoutes::fromArray(eval("return $exported;"));

        foreach (['/users/me', '/users/7', '/a/1/1', '/a/1/2', '/files/x.json', '/', '/users'] as $path) {
            foreach (['GET', 'POST'] as $method) {
                $this->assertEquals($original->match($method, $path), $restored->match($method, $path), "$method $path");
            }
        }
    }

    /**
     * @param array<string, string[]> $urls Route URLs per method, in registration order
     */
    private function compile(array $urls): CompiledRoutes
    {
        return (new RouteCompiler())->compile(array_map(fn(array $list) => array_fill_keys($list, true), $urls));
    }

    #[Test]
    public function test_find_gives_what_match_does_as_plain_data(): void
    {
        $routes = (new RouteCompiler())->compile(['GET' => ['/a/{x}' => 1, '/a/b' => 1, '/c' => 1], 'POST' => ['/a/b' => 1]]);

        foreach ([['GET', '/a/b'], ['GET', '/a/z'], ['GET', '/c'], ['POST', '/a/b'], ['GET', '/nope'], ['PUT', '/c']] as [$method, $path]) {
            for ($lookup = 1; $lookup <= 2; $lookup++) {
                $match = $routes->match($method, $path);
                $found = $routes->find($method, $path);
                $this->assertSame($match === null ? null : [$match->url, $match->params], $found === null ? null : [$found[0], $found[1]], "$method $path, lookup $lookup");
            }
        }
        $this->assertSame(['/a/{x}', ['x' => 'b']], array_slice((array) $routes->find('GET', '/a/b'), 0, 2), 'the earlier parameterised route wins');
    }
}
