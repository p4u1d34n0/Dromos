<?php

declare(strict_types=1);

namespace Dromos\Tests\Http;

use Dromos\Http\Message\Stream;
use Dromos\Http\Response;
use Dromos\Http\Message\ResourceStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Response::class)]
final class ResponseTest extends TestCase
{
    #[Test]
    public function test_json_sets_status_content_type_and_encoded_body(): void
    {
        $response = (new Response())->json(['id' => 7, 'name' => 'Ada'], 202);

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame(['application/json'], $response->getHeader('Content-Type'));
        $this->assertSame('{"id":7,"name":"Ada"}', (string) $response->getBody());
    }

    /**
     * @return array<string, array{0:callable(Response): Response, 1:int, 2:string, 3:string}>
     */
    public static function payloads(): array
    {
        return [
            'json'    => [fn(Response $r) => $r->json(['a' => 1]), 200, 'application/json', '{"a":1}'],
            'text'    => [fn(Response $r) => $r->text('hi', 201), 201, 'text/plain', 'hi'],
            'html'    => [fn(Response $r) => $r->html('<p>hi</p>'), 200, 'text/html; charset=UTF-8', '<p>hi</p>'],
            'created' => [fn(Response $r) => $r->created(['id' => 1]), 201, 'application/json', '{"id":1}'],
        ];
    }

    #[Test]
    #[DataProvider('payloads')]
    public function test_payload_helpers_set_status_type_and_body(callable $make, int $status, string $type, string $body): void
    {
        $response = $make(new Response());

        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame([$type], $response->getHeader('Content-Type'));
        $this->assertSame($body, (string) $response->getBody());
        $this->assertSame(strlen($body), $response->getBody()->getSize());
    }

    #[Test]
    #[DataProvider('payloads')]
    public function test_payload_helpers_replace_an_existing_body(callable $make, int $status, string $type, string $body): void
    {
        $earlier = (new Response())->text('an earlier, much longer body than the new one');

        $this->assertSame($body, (string) $make($earlier)->getBody());
    }

    #[Test]
    #[DataProvider('payloads')]
    public function test_payload_helpers_leave_the_original_response_unchanged(callable $make): void
    {
        $original = (new Response())->withHeader('X-Trace', 'abc');
        $original->getBody()->write('original');

        $changed = $make($original);

        $this->assertNotSame($original, $changed);
        $this->assertSame(200, $original->getStatusCode());
        $this->assertSame([], $original->getHeader('Content-Type'));
        $this->assertSame('original', (string) $original->getBody());
        $this->assertSame(['abc'], $changed->getHeader('X-Trace'), 'other headers are kept');
    }

    #[Test]
    public function test_a_payload_helper_replaces_the_content_type_whatever_its_case(): void
    {
        $response = (new Response())->withHeader('content-type', 'text/plain')->json([]);

        $this->assertSame(['Content-Type' => ['application/json']], $response->getHeaders());
    }

    #[Test]
    public function test_the_body_of_a_payload_response_can_still_be_written_to(): void
    {
        $response = (new Response())->text('abc');
        $response->getBody()->write('!');

        $this->assertSame('abc!', (string) $response->getBody());
    }

    #[Test]
    public function test_a_new_response_keeps_what_is_written_to_its_body(): void
    {
        $response = new Response();
        $response->getBody()->write('abc');

        $this->assertSame('abc', (string) $response->getBody());
        $this->assertSame($response->getBody(), $response->getBody(), 'the same stream every time');
    }

    #[Test]
    public function test_a_clone_made_before_the_body_is_used_gets_its_own_body(): void
    {
        $original = new Response();
        $clone = $original->withStatus(201);
        $clone->getBody()->write('clone');

        $this->assertSame('', (string) $original->getBody());
        $this->assertSame('clone', (string) $clone->getBody());
    }

    #[Test]
    public function test_with_status_copies_the_body_so_clones_stay_independent(): void
    {
        $original = new Response();
        $original->getBody()->write('one');

        $clone = $original->withStatus(201);
        $clone->getBody()->write('-two');

        $this->assertSame('one', (string) $original->getBody());
        $this->assertSame('one-two', (string) $clone->getBody());
    }

    #[Test]
    public function test_json_throws_on_values_that_cannot_be_encoded(): void
    {
        $this->expectException(\JsonException::class);
        (new Response())->json(['bad' => "\xB1\x31"]);
    }

    #[Test]
    public function test_no_content_and_redirect(): void
    {
        $this->assertSame(204, (new Response())->noContent()->getStatusCode());

        $redirect = (new Response())->redirect('/login');
        $this->assertSame(302, $redirect->getStatusCode());
        $this->assertSame(['/login'], $redirect->getHeader('Location'));
    }

    #[Test]
    public function test_headers_with_line_breaks_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Response())->withHeader('X-Bad', "a\r\nSet-Cookie: x=1");
    }

    #[Test]
    public function test_a_stream_can_be_created_with_content(): void
    {
        $stream = new Stream('hello');

        $this->assertSame('hello', (string) $stream);
        $this->assertSame(5, $stream->getSize());
        $this->assertSame(0, $stream->tell());
        $this->assertSame('hel', $stream->read(3));
    }

    #[Test]
    public function test_the_reason_phrase_follows_the_status_code(): void
    {
        $this->assertSame('OK', (new Response())->getReasonPhrase());
        $this->assertSame('Not Found', (new Response(404))->getReasonPhrase());
        $this->assertSame('Created', (new Response())->withStatus(201)->getReasonPhrase());
        $this->assertSame('Too Many Requests', (new Response())->json([], 429)->getReasonPhrase());
        $this->assertSame('', (new Response(299))->getReasonPhrase(), 'no phrase for an unregistered code');
    }

    #[Test]
    public function test_a_custom_reason_phrase_is_kept_until_the_status_changes(): void
    {
        $custom = (new Response())->withStatus(200, 'Fine');
        $this->assertSame('Fine', $custom->getReasonPhrase());
        $this->assertSame('Fine', $custom->withHeader('X-A', '1')->getReasonPhrase());
        $this->assertSame('Accepted', $custom->withStatus(202)->getReasonPhrase());
        $this->assertSame('Teapot', (new Response(418, 'Teapot'))->getReasonPhrase());
    }

    #[Test]
    public function test_headers_are_matched_case_insensitively(): void
    {
        $response = (new Response())->withHeader('X-Request-Id', 'a');

        $this->assertTrue($response->hasHeader('x-request-id'));
        $this->assertSame(['a'], $response->getHeader('X-REQUEST-ID'));
        $this->assertSame(['x-request-id' => ['b']], $response->withHeader('x-request-id', 'b')->getHeaders(), 'replaced under the new name');
        $this->assertSame(['X-Request-Id' => ['a', 'b']], $response->withAddedHeader('x-REQUEST-id', 'b')->getHeaders());
        $this->assertSame([], $response->withoutHeader('X-REQUEST-ID')->getHeaders());
        $this->assertSame('a, c', $response->withAddedHeader('X-Request-Id', ['c'])->getHeaderLine('x-request-id'));
    }

    #[Test]
    public function test_json_accepts_encoding_flags(): void
    {
        $this->assertSame('{"path":"/a"}', (string) (new Response())->json(['path' => '/a'], 200, JSON_UNESCAPED_SLASHES)->getBody());
        $this->assertSame('{"path":"\\/a"}', (string) (new Response())->json(['path' => '/a'])->getBody());
    }

    #[Test]
    public function test_clones_share_a_stream_that_is_not_in_memory(): void
    {
        $file = ResourceStream::fromString('file contents');
        $response = (new Response())->withBody($file);

        $this->assertSame($file, $response->withStatus(201)->getBody(), 'not copied into memory');
    }
}
