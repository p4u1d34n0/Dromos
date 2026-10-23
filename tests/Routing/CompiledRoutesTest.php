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
    public function test_parameterised_routes_are_split_across_regexes(): void
    {
        $urls = array_map(fn(int $i) => "/r$i/{id}", range(1, RouteCompiler::ROUTES_PER_REGEX * 2 + 1));
        $routes = $this->compile(['GET' => $urls]);

        $this->assertCount(3, $routes->toArray()['GET']['regexes']);
        $this->assertSame('/r101/{id}', $routes->match('GET', '/r101/x')?->url);
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
}
