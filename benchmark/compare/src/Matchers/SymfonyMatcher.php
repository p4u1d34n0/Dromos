<?php

declare(strict_types=1);

namespace Bench\Matchers;

use Bench\Matcher;
use Bench\RouteSet;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/** Symfony's compiled matcher, read from its dumped routes, as SymfonyContender uses it. */
final class SymfonyMatcher implements Matcher
{
    private CompiledUrlMatcher $matcher;
    private RequestContext $context;

    public function name(): string
    {
        return 'symfony';
    }

    public function boot(RouteSet $routes, string $cacheDir): void
    {
        $cacheFile = "$cacheDir/symfony-matcher.php";
        if (!is_file($cacheFile)) {
            $collection = new RouteCollection();
            $n = 0;
            foreach ($routes->routes() as [$method, $path, $action]) {
                $collection->add('r' . $n++, new Route($path, ['_action' => $action], methods: [$method]));
            }
            file_put_contents($cacheFile, '<?php return ' . var_export((new CompiledUrlMatcherDumper($collection))->getCompiledRoutes(), true) . ';');
        }
        $this->context = new RequestContext();
        $this->matcher = new CompiledUrlMatcher(require $cacheFile, $this->context);
    }

    public function match(string $method, string $path): array
    {
        $this->context->setMethod($method);
        try {
            $params = $this->matcher->match($path);
        } catch (ResourceNotFoundException) {
            return [404, null, []];
        } catch (MethodNotAllowedException) {
            return [405, null, []];
        }
        $action = $params['_action'];
        unset($params['_action'], $params['_route']);
        return [200, $action, $params];
    }
}
