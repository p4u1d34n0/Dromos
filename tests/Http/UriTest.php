<?php

declare(strict_types=1);

namespace Dromos\Tests\Http;

use Dromos\Http\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** What the PSR-7 suite (tests/Psr7) doesn't cover: building a request's URI from $_SERVER. */
#[CoversClass(Uri::class)]
final class UriTest extends TestCase
{
    /** @return array<string, array{array<string, mixed>, string}> */
    public static function servers(): array
    {
        return [
            'plain HTTP'              => [['HTTP_HOST' => 'api.test', 'REQUEST_URI' => '/users/7?page=2'], 'http://api.test/users/7?page=2'],
            'HTTPS'                   => [['HTTPS' => 'on', 'HTTP_HOST' => 'api.test', 'REQUEST_URI' => '/'], 'https://api.test/'],
            'HTTPS off'               => [['HTTPS' => 'off', 'HTTP_HOST' => 'api.test', 'REQUEST_URI' => '/'], 'http://api.test/'],
            'port in the Host header' => [['HTTP_HOST' => 'API.test:8080', 'SERVER_PORT' => '80', 'REQUEST_URI' => '/'], 'http://api.test:8080/'],
            'default port dropped'    => [['HTTPS' => 'on', 'HTTP_HOST' => 'api.test:443', 'REQUEST_URI' => '/'], 'https://api.test/'],
            'IPv6 with a port'        => [['HTTP_HOST' => '[::1]:8080', 'REQUEST_URI' => '/'], 'http://[::1]:8080/'],
            'IPv6 without a port'     => [['HTTP_HOST' => '[::1]', 'SERVER_PORT' => '8000', 'REQUEST_URI' => '/'], 'http://[::1]:8000/'],
            'SERVER_NAME and port'    => [['SERVER_NAME' => 'internal', 'SERVER_PORT' => '8000', 'REQUEST_URI' => '/x'], 'http://internal:8000/x'],
            'no host'                 => [['REQUEST_URI' => '/x'], 'http:/x'],
            'nothing'                 => [[], 'http:/'],
            'unencoded characters'    => [['HTTP_HOST' => 'a.test', 'REQUEST_URI' => '/a b/caf%C3%A9?q=a b'], 'http://a.test/a%20b/caf%C3%A9?q=a%20b'],
        ];
    }

    /** @param array<string, mixed> $server */
    #[Test]
    #[DataProvider('servers')]
    public function test_it_is_built_from_server_params(array $server, string $expected): void
    {
        $this->assertSame($expected, (string) Uri::fromServerParams($server));
    }

    #[Test]
    public function test_the_path_and_query_are_split_at_the_first_question_mark(): void
    {
        $uri = Uri::fromServerParams(['REQUEST_URI' => '/search?q=what?&x=1']);

        $this->assertSame('/search', $uri->getPath());
        $this->assertSame('q=what?&x=1', $uri->getQuery());
    }

    #[Test]
    public function test_values_that_are_not_strings_are_ignored(): void
    {
        $uri = Uri::fromServerParams(['HTTP_HOST' => ['evil'], 'REQUEST_URI' => '/a']);

        $this->assertSame('', $uri->getHost());
        $this->assertSame('/a', $uri->getPath());
    }

    #[Test]
    public function test_a_port_out_of_range_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Uri::fromServerParams(['HTTP_HOST' => 'a.test:70000']);
    }
}
