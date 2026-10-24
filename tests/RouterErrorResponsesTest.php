<?php

declare(strict_types=1);

namespace Dromos\Tests;

use Dromos\Http\Response;
use Dromos\Router;
use Dromos\RouterException;
use Dromos\RouterExceptionHandler;
use Dromos\Tests\Support\RouterTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Router::class)]
final class RouterErrorResponsesTest extends RouterTestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logFile = (string) tempnam(sys_get_temp_dir(), 'dromos-log');
        ini_set('error_log', $this->logFile);

        Router::Get('/users/{id}', fn(Response $res) => $res->json([]));
        Router::Put('/users/{id}', fn(Response $res) => $res->json([]));
        Router::Get('/boom', fn() => throw new \RuntimeException('database is down'));
    }

    protected function tearDown(): void
    {
        @unlink($this->logFile);
        parent::tearDown();
    }

    #[Test]
    public function test_a_404_has_a_json_error_body_and_is_not_logged(): void
    {
        $response = $this->response('GET', '/nope');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['application/json'], $response->getHeader('Content-Type'));
        $this->assertSame('{"error":"Not Found","message":"Route not found: /nope","status":404}', (string) $response->getBody());
        $this->assertSame('', file_get_contents($this->logFile), 'client errors are not server errors');
    }

    #[Test]
    public function test_a_405_lists_the_allowed_methods_in_the_body_and_allow_header(): void
    {
        $response = $this->response('DELETE', '/users/1');

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame(['GET, PUT'], $response->getHeader('Allow'), 'RFC 9110 requires Allow on a 405');
        $this->assertSame(
            '{"error":"Method Not Allowed","message":"Method not allowed. Available methods: GET, PUT","status":405,"details":{"available_methods":["GET","PUT"]}}',
            (string) $response->getBody()
        );
        $this->assertSame('', file_get_contents($this->logFile));
    }

    #[Test]
    public function test_a_handler_exception_is_a_logged_500_that_hides_the_message(): void
    {
        $response = $this->response('GET', '/boom');

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('{"error":"Internal Server Error","message":"Internal Server Error","status":500}', (string) $response->getBody());
        $this->assertStringContainsString('database is down', (string) file_get_contents($this->logFile));
    }

    #[Test]
    public function test_a_404_names_a_path_that_is_not_utf8_percent_encoded(): void
    {
        $response = $this->response('GET', "/caf\xE9");

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Route not found: /caf%E9', $this->decode($response)['message']);
    }

    #[Test]
    public function test_an_error_message_that_is_not_utf8_still_gives_a_json_body(): void
    {
        $error = RouterException::routeNotFound("/caf\xE9");

        $response = RouterExceptionHandler::render($error, 404);

        $this->assertSame("Route not found: /caf\u{FFFD}", $this->decode($response)['message']);
    }
}
