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
    public function test_a_port_out_of_range_is_dropped(): void
    {
        // The Host header comes from the client, so a bad one mustn't stop the request
        $uri = Uri::fromServerParams(['HTTP_HOST' => 'a.test:70000', 'REQUEST_URI' => '/']);

        $this->assertSame('a.test', $uri->getHost());
        $this->assertNull($uri->getPort());
    }

    #[Test]
    public function test_numbers_and_booleans_from_other_servers_are_read(): void
    {
        // Swoole and RoadRunner adapters can give SERVER_PORT as an int and HTTPS as a bool
        $uri = Uri::fromServerParams(['HTTPS' => true, 'SERVER_NAME' => 'api.test', 'SERVER_PORT' => 8443, 'REQUEST_URI' => '/']);

        $this->assertSame('https://api.test:8443/', (string) $uri);
    }

    /** @return array<string, array{callable(Uri): mixed, mixed}> */
    public static function authorityReads(): array
    {
        return [
            'getScheme'    => [static fn(Uri $uri) => $uri->getScheme(), 'https'],
            'getAuthority' => [static fn(Uri $uri) => $uri->getAuthority(), 'api.test:8080'],
            'getUserInfo'  => [static fn(Uri $uri) => $uri->getUserInfo(), ''],
            'getHost'      => [static fn(Uri $uri) => $uri->getHost(), 'api.test'],
            'getPort'      => [static fn(Uri $uri) => $uri->getPort(), 8080],
            '__toString'   => [static fn(Uri $uri) => (string) $uri, 'https://api.test:8080/a?b=1'],
        ];
    }

    /**
     * The scheme, host and port are read on first use, so each way of
     * reading them must see the server params.
     *
     * @param callable(Uri): mixed $read
     */
    #[Test]
    #[DataProvider('authorityReads')]
    public function test_the_authority_is_read_whichever_getter_comes_first(callable $read, mixed $expected): void
    {
        $uri = Uri::fromServerParams(['HTTPS' => 'on', 'HTTP_HOST' => 'API.test:8080', 'REQUEST_URI' => '/a?b=1']);

        $this->assertSame($expected, $read($uri));
    }

    /** @return array<string, array{callable(Uri): Uri, string}> */
    public static function authorityChanges(): array
    {
        return [
            'withScheme'   => [static fn(Uri $uri) => $uri->withScheme('https'), 'https://api.test:8080/a'],
            'withUserInfo' => [static fn(Uri $uri) => $uri->withUserInfo('me'), 'http://me@api.test:8080/a'],
            'withHost'     => [static fn(Uri $uri) => $uri->withHost('other.test'), 'http://other.test:8080/a'],
            'withPort'     => [static fn(Uri $uri) => $uri->withPort(null), 'http://api.test/a'],
            'withPath'     => [static fn(Uri $uri) => $uri->withPath('/b'), 'http://api.test:8080/b'],
        ];
    }

    /**
     * A copy made before the authority is read must keep its change, not
     * have it overwritten when the server params are read later, and must
     * leave the original as it was.
     *
     * @param callable(Uri): Uri $change
     */
    #[Test]
    #[DataProvider('authorityChanges')]
    public function test_a_change_made_before_the_authority_is_read_is_kept(callable $change, string $expected): void
    {
        $uri = Uri::fromServerParams(['HTTP_HOST' => 'api.test:8080', 'REQUEST_URI' => '/a']);

        $this->assertSame($expected, (string) $change($uri));
        $this->assertSame('http://api.test:8080/a', (string) $uri);
    }
}
