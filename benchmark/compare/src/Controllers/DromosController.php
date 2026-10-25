<?php

declare(strict_types=1);

namespace Bench\Controllers;

use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Response;

final class DromosController
{
    public function index(Response $response): ResponseInterface
    {
        return $response->json(['ok' => true]);
    }

    public function show(Response $response, string $id): ResponseInterface
    {
        return $response->json(['id' => $id]);
    }

    public function item(Response $response, string $id, string $item): ResponseInterface
    {
        return $response->json(['id' => $id, 'item' => $item]);
    }

    public function store(Response $response): ResponseInterface
    {
        return $response->json(['ok' => true]);
    }
}
