<?php

declare(strict_types=1);

namespace Dromos\Tests\Support;

use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Request;
use Dromos\Router;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that register routes and dispatch requests through
 * the static Router. Resets router state around each test and silences the
 * error_log line RouterExceptionHandler writes for every 404, 405 and 500.
 */
abstract class RouterTestCase extends TestCase
{
    private string $errorLog;

    protected function setUp(): void
    {
        parent::setUp();
        Router::reset();
        $this->errorLog = (string) ini_get('error_log');
        ini_set('error_log', '/dev/null');
    }

    protected function tearDown(): void
    {
        Router::reset();
        ini_set('error_log', $this->errorLog);
        parent::tearDown();
    }

    protected function response(string $method, string $uri): ResponseInterface
    {
        return (new Router())->handle(Request::create($method, $uri));
    }

    protected function statusOf(string $method, string $uri): int
    {
        return $this->response($method, $uri)->getStatusCode();
    }

    /**
     * The decoded JSON body of a request that must succeed.
     *
     * @return array<mixed>
     */
    protected function body(string $method, string $uri): array
    {
        $response = $this->response($method, $uri);
        $this->assertSame(200, $response->getStatusCode(), "$method $uri: " . $response->getBody());
        return $this->decode($response);
    }

    /** @return mixed */
    protected function decode(ResponseInterface $response): mixed
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
