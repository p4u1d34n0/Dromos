<?php

declare(strict_types=1);

namespace Bench\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Slim's default handler signature: request, response and route arguments. */
final class SlimController
{
    public function index(Request $request, Response $response): Response
    {
        return self::json($response, ['ok' => true]);
    }

    /** @param array{id: string} $args */
    public function show(Request $request, Response $response, array $args): Response
    {
        return self::json($response, ['id' => $args['id']]);
    }

    /** @param array{id: string, item: string} $args */
    public function item(Request $request, Response $response, array $args): Response
    {
        return self::json($response, ['id' => $args['id'], 'item' => $args['item']]);
    }

    public function store(Request $request, Response $response): Response
    {
        return self::json($response, ['ok' => true]);
    }

    /** @param array<string, mixed> $data */
    private static function json(Response $response, array $data): Response
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
