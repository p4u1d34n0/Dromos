<?php

declare(strict_types=1);

namespace Bench\Controllers;

/** Plain controller for the matcher-only contenders: returns the JSON body. */
final class MatcherController
{
    public function index(): string
    {
        return json_encode(['ok' => true], JSON_THROW_ON_ERROR);
    }

    public function show(string $id): string
    {
        return json_encode(['id' => $id], JSON_THROW_ON_ERROR);
    }

    public function item(string $id, string $item): string
    {
        return json_encode(['id' => $id, 'item' => $item], JSON_THROW_ON_ERROR);
    }

    public function store(): string
    {
        return json_encode(['ok' => true], JSON_THROW_ON_ERROR);
    }
}
