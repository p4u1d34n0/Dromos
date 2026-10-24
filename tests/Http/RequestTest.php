<?php

declare(strict_types=1);

namespace Dromos\Tests\Http;

use Dromos\Http\Message\Stream;
use Dromos\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Request::class)]
final class RequestTest extends TestCase
{
    /** @var array<string, array<mixed>> */
    private array $globals;

    protected function setUp(): void
    {
        $this->globals = ['server' => $_SERVER, 'get' => $_GET, 'post' => $_POST, 'cookie' => $_COOKIE, 'files' => $_FILES];
    }

    protected function tearDown(): void
    {
        [$_SERVER, $_GET, $_POST, $_COOKIE, $_FILES] = array_values($this->globals);
    }

    /**
     * A Request whose php://input is $input, counting how often it is read.
     */
    private function requestWithInput(string $input, array $server = []): CountingRequest
    {
        $_SERVER = $server + ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/things?page=2'];
        CountingRequest::$input = $input;
        CountingRequest::$reads = 0;
        return new CountingRequest();
    }

    #[Test]
    public function test_it_is_built_from_the_php_globals(): void
    {
        $_SERVER = ['REQUEST_METHOD' => 'PATCH', 'REQUEST_URI' => '/users/7?expand=1', 'HTTP_HOST' => 'api.test'];
        $_GET = ['expand' => '1'];
        $_COOKIE = ['session' => 'abc'];
        $_POST = [];
        $_FILES = [];

        $request = new Request();

        $this->assertSame('PATCH', $request->getMethod());
        $this->assertSame('/users/7', $request->getUri()->getPath());
        $this->assertSame('expand=1', $request->getUri()->getQuery());
        $this->assertSame(['expand' => '1'], $request->getQueryParams());
        $this->assertSame(['session' => 'abc'], $request->getCookieParams());
        $this->assertSame('api.test', $request->getServerParams()['HTTP_HOST']);
    }

    #[Test]
    public function test_the_body_is_not_read_until_it_is_used(): void
    {
        $request = $this->requestWithInput('{"a":1}', ['CONTENT_TYPE' => 'application/json']);
        $request->getMethod();
        $request->getUri();
        $request->getQueryParams();
        $request = $request->withAttribute('id', '7');

        $this->assertSame(0, CountingRequest::$reads);

        $this->assertSame(['a' => 1], $request->getParsedBody());
        $this->assertSame('{"a":1}', (string) $request->getBody());
        $this->assertSame(1, CountingRequest::$reads, 'the body is read once');
    }

    #[Test]
    public function test_a_json_body_is_decoded_into_the_parsed_body(): void
    {
        $request = $this->requestWithInput('{"name":"Ada","tags":["x"]}', ['CONTENT_TYPE' => 'application/json; charset=utf-8']);

        $this->assertSame(['name' => 'Ada', 'tags' => ['x']], $request->getParsedBody());
    }

    #[Test]
    public function test_a_form_post_keeps_the_post_fields_as_the_parsed_body(): void
    {
        $_POST = ['name' => 'Ada'];
        $request = $this->requestWithInput('name=Ada', ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(['name' => 'Ada'], $request->getParsedBody());
        $this->assertSame('name=Ada', (string) $request->getBody());
    }

    #[Test]
    public function test_invalid_json_leaves_the_post_fields_as_the_parsed_body(): void
    {
        $_POST = [];
        $request = $this->requestWithInput('{not json', ['CONTENT_TYPE' => 'application/json']);

        $this->assertSame([], $request->getParsedBody());
        $this->assertSame('{not json', (string) $request->getBody());
    }

    #[Test]
    public function test_a_body_over_one_megabyte_is_dropped_without_reading_it(): void
    {
        $request = $this->requestWithInput('{"a":1}', ['CONTENT_TYPE' => 'application/json', 'CONTENT_LENGTH' => (string) (1048576 + 1)]);

        $this->assertSame('', (string) $request->getBody());
        $this->assertSame(0, CountingRequest::$reads);
    }

    #[Test]
    public function test_a_body_without_a_content_length_is_dropped_when_over_one_megabyte(): void
    {
        $request = $this->requestWithInput(str_repeat('x', 1048576 + 1));

        $this->assertSame('', (string) $request->getBody());
    }

    #[Test]
    public function test_a_body_of_exactly_one_megabyte_is_kept(): void
    {
        $request = $this->requestWithInput(str_repeat('x', 1048576));

        $this->assertSame(1048576, $request->getBody()->getSize());
    }

    #[Test]
    public function test_with_parsed_body_and_with_body_leave_the_original_unchanged(): void
    {
        $original = $this->requestWithInput('{"a":1}', ['CONTENT_TYPE' => 'application/json']);

        $parsed = $original->withParsedBody(['b' => 2]);
        $replaced = $original->withBody(new Stream('other'));

        $this->assertSame(['b' => 2], $parsed->getParsedBody());
        $this->assertSame('{"a":1}', (string) $parsed->getBody(), 'the raw body is kept');
        $this->assertSame('other', (string) $replaced->getBody());
        $this->assertSame(['a' => 1], $replaced->getParsedBody(), 'the parsed body is kept');
        $this->assertSame(['a' => 1], $original->getParsedBody());
        $this->assertSame('{"a":1}', (string) $original->getBody());
    }

    #[Test]
    public function test_create_uses_the_given_values_and_never_reads_the_input(): void
    {
        CountingRequest::$reads = 0;
        $request = CountingRequest::create('PUT', '/items/3', [], ['X-Id' => '9'], ['q' => 'x'], ['n' => 1], 'raw');

        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('/items/3', $request->getUri()->getPath());
        $this->assertSame(['9'], $request->getHeader('X-Id'));
        $this->assertSame(['q' => 'x'], $request->getQueryParams());
        $this->assertSame(['n' => 1], $request->getParsedBody());
        $this->assertSame('raw', (string) $request->getBody());
        $this->assertSame(0, CountingRequest::$reads);
    }
}

/** Serves a fixed string as php://input and counts the reads. */
final class CountingRequest extends Request
{
    public static string $input = '';
    public static int $reads = 0;

    protected function readInput(int $maxBytes): string
    {
        self::$reads++;
        return substr(self::$input, 0, $maxBytes);
    }
}
