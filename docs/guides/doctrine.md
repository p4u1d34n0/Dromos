# Using Doctrine with Dromos

Dromos has no database layer, so Doctrine ORM fits alongside it without conflict. Dromos gets the request to your controller, and Doctrine does the database work inside it.

The examples were tested with Doctrine ORM 3.7, DBAL 4.5 and PHP-DI 7.1 on PHP 8.4.

## Give controllers the EntityManager

The default resolver builds controllers with no constructor arguments, so a controller can't receive Doctrine's `EntityManager` until you register a resolver that passes it in.

```php
use App\Controller\UserController;
use Doctrine\ORM\EntityManagerInterface;
use Dromos\Router;
use Dromos\Routing\ControllerResolverInterface;

final class AppResolver implements ControllerResolverInterface
{
    private array $instances = [];

    public function __construct(private EntityManagerInterface $em) {}

    public function resolve(string $class): object
    {
        return $this->instances[$class] ??= match ($class) {
            UserController::class => new UserController($this->em),
            default => new $class(),
        };
    }
}

Router::setControllerResolver(new AppResolver($entityManager));
```

The resolver also builds middleware given by class name, which is why the `default` arm creates everything else with no arguments.

The controller asks for the route parameter by name, typed as `int`, and for the response:

```php
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Dromos\Http\Response;

final class UserController
{
    public function __construct(private EntityManagerInterface $em) {}

    public function show(int $id, Response $response): Response
    {
        $user = $this->em->find(User::class, $id);

        return $user
            ? $response->json(['id' => $user->getId(), 'name' => $user->getName()])
            : $response->json(['error' => 'Not found'], 404);
    }
}
```

### With a container

Once you have more than a few controllers, let a container build them. PHP-DI works out constructor arguments by itself, so you only tell it which `EntityManager` to use:

```php
use Doctrine\ORM\EntityManagerInterface;
use Dromos\Routing\ControllerResolverInterface;
use Psr\Container\ContainerInterface;

final class ContainerControllerResolver implements ControllerResolverInterface
{
    public function __construct(private ContainerInterface $container) {}

    public function resolve(string $class): object
    {
        return $this->container->get($class);
    }
}

$container = new DI\Container([EntityManagerInterface::class => $entityManager]);

Router::setControllerResolver(new ContainerControllerResolver($container));
```

Any PSR-11 container works the same way, including Symfony's DependencyInjection component.

## Under PHP-FPM

Nothing else to do. PHP-FPM starts every request with fresh state, so Doctrine behaves as it does in any other PHP app.

## Under OpenSwoole

An OpenSwoole worker handles thousands of requests in one process, and Dromos reuses each controller for all of them. Every request in a worker therefore shares one `EntityManager`, which Doctrine wasn't designed for:

- **The identity map keeps growing.** Every entity a request loads stays in memory and goes stale. Call `$em->clear()` after each request.
- **A failed write closes the `EntityManager`.** After an exception during `flush()`, every later write in that worker fails with "The EntityManager is closed". Reads still work, so this can go unnoticed for a while. Replace the `EntityManager` when it's closed.
- **Connections can time out while a worker is idle.** See [Idle connections](#idle-connections).

Controllers hold on to the `EntityManager` they were given, so to replace it you need a wrapper that can swap what's inside. Doctrine's `EntityManagerDecorator` does most of the work:

```php
use Closure;
use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\ORM\EntityManagerInterface;

final class ResettableEntityManager extends EntityManagerDecorator
{
    /** @param Closure(): EntityManagerInterface $factory */
    public function __construct(private readonly Closure $factory)
    {
        parent::__construct($factory());
    }

    public function resetIfClosed(): void
    {
        if (!$this->wrapped->isOpen()) {
            $this->wrapped = ($this->factory)();
        }
    }
}
```

Then a middleware cleans up after every request, whether it succeeded or threw:

```php
use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Middleware\MiddlewareInterface;
use Dromos\Http\Middleware\RequestHandlerInterface;

final class DoctrineRequestScope implements MiddlewareInterface
{
    public function __construct(private ResettableEntityManager $em) {}

    public function handle(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } finally {
            $this->em->clear();          // forget this request's entities
            $this->em->resetIfClosed();  // replace the EntityManager if an error closed it
        }
    }
}
```

Create both once per worker, and give controllers the wrapper:

```php
$em = new ResettableEntityManager(fn () => new EntityManager(
    DriverManager::getConnection($dbParams, $config),
    $config,
));

Router::setControllerResolver(new AppResolver($em));

$router = new Router();
$router->addMiddleware(new DoctrineRequestScope($em));
```

### Idle connections

If a worker can sit idle for longer than the database's connection timeout, its next query fails. The simplest fix is to close the connection at the end of each request, in the same `finally` block:

```php
$this->em->getConnection()->close();
```

DBAL reconnects on the next query. This costs a new connection per request, so only do it if your workers really do go quiet for that long, or put a connection pooler such as PgBouncer or ProxySQL in front of the database.
