<?php 

namespace App;

use App\Router;

class RouteResource
{
    protected $url;
    protected $controller;
    protected $excludedMethods = ['OPTIONS', 'HEAD'];

    // Define all methods by default
    protected $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

    public function __construct(string $url, string $controller)
    {
        $this->url = $url;
        $this->controller = $controller;
    }

    // Method to exclude specific HTTP methods
    public function exceptMethods(array $methods): self
    {
        $this->excludedMethods = array_map('strtoupper', $methods);
        return $this;
    }

    // Method to include specific HTTP methods
    public function onlyMethods(array $methods): self
    {
        $this->methods = array_map('strtoupper', $methods);
        return $this;
    }

    // Method to exclude specific HTTP methods
    public function apiResource(array $methods = []): self
    {
        if (!empty($methods)) {
            $this->excludedMethods = array_map('strtoupper', $methods);
            return $this;
        }

        $this->excludedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        return $this;
    }

    // Registers the routes based on the methods
    public function register(): void
    {
        foreach ($this->methods as $method) {
            if (!in_array($method, $this->excludedMethods)) {
                $controllerMethod = strtolower($method);
                Router::addRoute($method, $this->url, [$this->controller, $controllerMethod]);
            }
        }
    }
}
