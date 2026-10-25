<?php

declare(strict_types=1);

namespace Bench\Controllers\Mezzio;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ItemHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['id' => $request->getAttribute('id'), 'item' => $request->getAttribute('item')]);
    }
}
