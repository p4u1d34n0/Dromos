<?php

declare(strict_types=1);

namespace Bench\Controllers;

use Illuminate\Http\JsonResponse;

final class LaravelController
{
    public function index(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }

    public function show(string $id): JsonResponse
    {
        return new JsonResponse(['id' => $id]);
    }

    public function item(string $id, string $item): JsonResponse
    {
        return new JsonResponse(['id' => $id, 'item' => $item]);
    }

    public function store(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }
}
