<?php

declare(strict_types=1);

namespace Dromos\Routing;

/**
 * The default controller resolver: creates each controller with no
 * constructor arguments the first time it is needed and reuses it after
 * that, so there is one instance per class per process.
 */
final class SharedControllerResolver implements ControllerResolverInterface
{
    /** @var array<string, object> Instances keyed by lower-case class name */
    private array $instances = [];

    public function resolve(string $class): object
    {
        return $this->instances[strtolower(ltrim($class, '\\'))] ??= new $class();
    }
}
