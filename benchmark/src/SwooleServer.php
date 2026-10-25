<?php

namespace Benchmark;

use Dromos\Http\Emitter\SwooleEmitter;
use Dromos\Router;
use OpenSwoole\HTTP\Server;
use OpenSwoole\HTTP\Request as SwooleRequest;
use OpenSwoole\HTTP\Response as SwooleResponse;

/**
 * Reusable OpenSwoole HTTP server wrapper for Dromos
 *
 * Bridges Swoole's event-driven request lifecycle to Dromos's PSR-15 Router,
 * converting each incoming Swoole request to a Dromos Request, dispatching it
 * through the Router, and emitting the response via SwooleEmitter.
 */
class SwooleServer
{
    private Server $server;

    public function __construct(
        private string $host,
        private int $port,
        private Router $router
    ) {}

    public function start(): void
    {
        $this->server = new Server($this->host, $this->port);

        $workers = (int) (getenv('SWOOLE_WORKERS') ?: 4);

        $this->server->set([
            'worker_num' => $workers,
            'open_tcp_nodelay' => true,
            'max_request' => 0,  // Never restart workers (keep in-memory state)
            'log_level' => 4,    // Warning level
        ]);

        $router = $this->router;

        $this->server->on('request', function (SwooleRequest $swooleRequest, SwooleResponse $swooleResponse) use ($router) {
            $request = SwooleRequestFactory::fromSwoole($swooleRequest);
            $response = $router->handle($request);
            $emitter = new SwooleEmitter($swooleResponse);
            $emitter->emit($response);
        });

        $this->server->on('start', function () use ($workers) {
            echo sprintf(
                "[Dromos] Server started on %s:%d (workers: %d)\n",
                $this->host,
                $this->port,
                $workers
            );
        });

        $this->server->start();
    }
}
