<?php

declare(strict_types=1);

namespace Bench\Controllers;

use flight\Engine;

final class FlightController
{
    public function __construct(private readonly Engine $app)
    {
    }

    public function index(): void
    {
        $this->app->json(['ok' => true]);
    }

    public function show(string $id): void
    {
        $this->app->json(['id' => $id]);
    }

    public function item(string $id, string $item): void
    {
        $this->app->json(['id' => $id, 'item' => $item]);
    }

    public function store(): void
    {
        $this->app->json(['ok' => true]);
    }
}
