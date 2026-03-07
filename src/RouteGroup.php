<?php

namespace Dromos;

use Dromos\Http\Middleware\MiddlewareInterface;

/**
 * Route group with shared URL prefix and optional per-group middleware.
 *
 * Supports nested groups that inherit the parent prefix and middleware stack.
 *
 * Usage:
 *   Router::group('/api', function (RouteGroup $group) {
 *       $group->middleware(new AuthMiddleware());
 *       $group->get('/users', [UserController::class, 'index']);
 *       $group->group('/admin', function (RouteGroup $admin) {
 *           $admin->middleware(new AdminMiddleware());
 *           $admin->get('/dashboard', [AdminController::class, 'dashboard']);
 *       });
 *   });
 */
class RouteGroup
{
    private string $prefix;

    /** @var MiddlewareInterface[] */
    private array $middleware = [];

    public function __construct(string $prefix)
    {
        $this->prefix = rtrim($prefix, '/');
    }

    /**
     * Add middleware to this group. All routes registered within will inherit it.
     *
     * @param MiddlewareInterface ...$middleware
     * @return self
     */
    public function middleware(MiddlewareInterface ...$middleware): self
    {
        foreach ($middleware as $mw) {
            $this->middleware[] = $mw;
        }
        return $this;
    }

    /**
     * Create a nested group that inherits the current prefix and middleware.
     *
     * @param string   $prefix   Additional prefix to append
     * @param callable $callback Receives the nested RouteGroup instance
     * @return self
     */
    public function group(string $prefix, callable $callback): self
    {
        $nested = new self($this->prefix . '/' . ltrim($prefix, '/'));
        $nested->middleware = $this->middleware;
        $callback($nested);
        return $this;
    }

    // ────────────────────────────────────────────────────────────────────────────────
    // Route registration methods (lowercase, instance-based)
    // ────────────────────────────────────────────────────────────────────────────────

    public function get(string $url, $target): self
    {
        return $this->addGroupRoute('GET', $url, $target);
    }

    public function post(string $url, $target): self
    {
        return $this->addGroupRoute('POST', $url, $target);
    }

    public function put(string $url, $target): self
    {
        return $this->addGroupRoute('PUT', $url, $target);
    }

    public function patch(string $url, $target): self
    {
        return $this->addGroupRoute('PATCH', $url, $target);
    }

    public function delete(string $url, $target): self
    {
        return $this->addGroupRoute('DELETE', $url, $target);
    }

    public function head(string $url, $target): self
    {
        return $this->addGroupRoute('HEAD', $url, $target);
    }

    public function options(string $url, $target): self
    {
        return $this->addGroupRoute('OPTIONS', $url, $target);
    }

    // ────────────────────────────────────────────────────────────────────────────────
    // Internal: register a route with prefix and attach group middleware
    // ────────────────────────────────────────────────────────────────────────────────

    /**
     * Build the full URL, delegate registration to Router, and attach middleware.
     *
     * @param string $method HTTP method
     * @param string $url    Route path (relative to group prefix)
     * @param mixed  $target Closure or [Controller::class, 'method']
     * @return self
     */
    private function addGroupRoute(string $method, string $url, $target): self
    {
        $fullUrl = $this->prefix . '/' . ltrim($url, '/');

        // Delegate to the Router's static registration methods
        $routerMethod = ucfirst(strtolower($method));
        Router::$routerMethod($fullUrl, $target);

        // Attach per-group middleware to this specific route
        if (!empty($this->middleware)) {
            Router::setRouteMiddleware($method, $fullUrl, $this->middleware);
        }

        return $this;
    }
}
