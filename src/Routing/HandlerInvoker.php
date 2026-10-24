<?php

// No strict_types here: handlers are called from this file, and route parameters
// (always strings) must keep coercing to scalar types such as `int $id`.

namespace Dromos\Routing;

use Closure;
use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Response;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequestInterface;
use InvalidArgumentException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use UnexpectedValueException;
use WeakMap;

/**
 * Calls a route handler (a Closure or [Controller::class, 'method']) with the
 * arguments its parameters ask for:
 *
 * 1. Parameters typed as a server request (Dromos's or PSR-7's) get the request.
 * 2. Parameters typed as a response (Dromos's or PSR-7's) get a new Response.
 * 3. Others get the route parameter with the same name,
 * 4. or their default value,
 * 5. or the call fails with "Cannot resolve parameter".
 *
 * The handler must return a PSR-7 response; one from another library is
 * copied into a Dromos Response (see Response::fromPsr()).
 *
 * Which rule applies to each parameter is worked out by reflection the first
 * time a handler runs and reused after that. Defaults are still evaluated on
 * every call, so an object default is fresh each time.
 */
final class HandlerInvoker
{
    private const ARG_REQUEST = 0;
    private const ARG_RESPONSE = 1;
    private const ARG_NAMED = 2;

    /** @var WeakMap<Closure, list<array{0:int,1:string,2:ReflectionParameter}>> Entries go when their closure is freed */
    private WeakMap $closurePlans;

    /** @var array<string, array<string, list<array{0:int,1:string,2:ReflectionParameter}>>> Keyed by class, then method */
    private array $methodPlans = [];

    public function __construct(private readonly ControllerResolverInterface $controllers)
    {
        $this->closurePlans = new WeakMap();
    }

    /**
     * @param Closure|array{0:class-string,1:string} $target
     * @param array<string, string>                  $params Route parameters, also added to the request as attributes
     */
    public function invoke(Closure|array $target, array $params, ServerRequestInterface $request): ResponseInterface
    {
        foreach ($params as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        if ($target instanceof Closure) {
            $response = $target(...$this->arguments($this->closurePlan($target), $params, $request));
        } else {
            [$class, $method] = $target;
            $controller = $this->controllers->resolve($class);
            $response = $controller->{$method}(...$this->arguments($this->methodPlan($controller, $method), $params, $request));
        }

        if (!$response instanceof PsrResponseInterface) {
            throw new UnexpectedValueException(
                'Route handler must return a ' . PsrResponseInterface::class . ', got ' . get_debug_type($response) . '.'
            );
        }
        return Response::fromPsr($response);
    }

    /**
     * @param list<array{0:int,1:string,2:ReflectionParameter}> $plan
     * @param array<string, string>                             $params
     * @return list<mixed>
     */
    private function arguments(array $plan, array $params, ServerRequestInterface $request): array
    {
        $args = [];
        foreach ($plan as [$source, $name, $parameter]) {
            $args[] = match ($source) {
                self::ARG_REQUEST  => $request,
                self::ARG_RESPONSE => new Response(),
                default => array_key_exists($name, $params)
                    ? $params[$name]
                    : ($parameter->isOptional()
                        ? $parameter->getDefaultValue()
                        : throw new InvalidArgumentException("Cannot resolve parameter {$name}")),
            };
        }
        return $args;
    }

    /** @return list<array{0:int,1:string,2:ReflectionParameter}> */
    private function closurePlan(Closure $closure): array
    {
        return $this->closurePlans[$closure] ??= $this->buildPlan(new ReflectionFunction($closure));
    }

    /** @return list<array{0:int,1:string,2:ReflectionParameter}> */
    private function methodPlan(object $controller, string $method): array
    {
        return $this->methodPlans[$controller::class][$method]
            ??= $this->buildPlan(new ReflectionMethod($controller, $method));
    }

    /** @return list<array{0:int,1:string,2:ReflectionParameter}> */
    private function buildPlan(ReflectionFunctionAbstract $function): array
    {
        $plan = [];
        foreach ($function->getParameters() as $parameter) {
            // Only a single class type selects the request or response; union and
            // scalar types are filled from route parameters by name
            $type = $parameter->getType();
            $class = $type instanceof ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;
            $source = match (true) {
                $class !== null && is_a($class, PsrServerRequestInterface::class, true) => self::ARG_REQUEST,
                $class !== null && is_a($class, PsrResponseInterface::class, true) => self::ARG_RESPONSE,
                default => self::ARG_NAMED,
            };
            $plan[] = [$source, $parameter->getName(), $parameter];
        }
        return $plan;
    }
}
