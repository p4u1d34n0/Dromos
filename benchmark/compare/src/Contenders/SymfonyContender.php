<?php

declare(strict_types=1);

namespace Bench\Contenders;

use Bench\Contender;
use Bench\RouteSet;
use Bench\Controllers\MatcherController;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * symfony/routing is a matcher only (Symfony's HttpKernel does the rest), so
 * like FastRoute this adapter adds the least glue needed and is a lower
 * bound. With a cache directory it uses the compiled matcher that Symfony
 * apps run in production (and Laravel uses under route:cache), loading the
 * dumped routes without registering them.
 */
final class SymfonyContender implements Contender
{
    private UrlMatcherInterface $matcher;
    private RequestContext $context;

    public function name(): string
    {
        return 'symfony';
    }

    public function boot(RouteSet $routes, ?string $cacheDir): void
    {
        $this->context = new RequestContext();
        $cacheFile = $cacheDir === null ? null : "$cacheDir/symfony-routes.php";
        if ($cacheFile !== null && is_file($cacheFile)) {
            $this->matcher = new CompiledUrlMatcher(require $cacheFile, $this->context);
            return;
        }

        $collection = new RouteCollection();
        $n = 0;
        foreach ($routes->routes() as [$method, $path, $action]) {
            $collection->add('r' . $n++, new Route($path, ['_controller' => [MatcherController::class, $action]], methods: [$method]));
        }
        if ($cacheFile === null) {
            $this->matcher = new UrlMatcher($collection, $this->context);
            return;
        }
        $compiled = (new CompiledUrlMatcherDumper($collection))->getCompiledRoutes();
        file_put_contents($cacheFile, '<?php return ' . var_export($compiled, true) . ';');
        $this->matcher = new CompiledUrlMatcher($compiled, $this->context);
    }

    public function handle(string $method, string $path): array
    {
        $this->context->setMethod($method);
        try {
            $params = $this->matcher->match($path);
        } catch (ResourceNotFoundException) {
            return [404, ''];
        } catch (MethodNotAllowedException) {
            return [405, ''];
        }
        [$class, $action] = $params['_controller'];
        unset($params['_controller'], $params['_route']);
        return [200, (new $class())->{$action}(...$params)];
    }

    public function serve(): void
    {
        [$status, $body] = $this->handle($_SERVER['REQUEST_METHOD'], (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
        http_response_code($status);
        header('Content-Type: application/json');
        echo $body;
    }
}
