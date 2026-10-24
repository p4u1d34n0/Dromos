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

    /** @var array<string, object> The same instances keyed by the name as given, to skip normalising it */
    private array $byName = [];

    /**
     * @throws \InvalidArgumentException If the class doesn't exist
     */
    public function resolve(string $class): object
    {
        return $this->byName[$class] ??= $this->instances[strtolower(ltrim($class, '\\'))] ??= class_exists($class)
            ? new $class()
            : throw new \InvalidArgumentException("Controller class {$class} does not exist.");
    }
}
