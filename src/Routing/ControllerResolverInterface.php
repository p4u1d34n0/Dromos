<?php

declare(strict_types=1);

namespace Dromos\Routing;

/**
 * Supplies controller instances for [Controller::class, 'method'] routes.
 *
 * Implement this to have controllers built by a dependency injection
 * container, and register it with Router::setControllerResolver().
 */
interface ControllerResolverInterface
{
    /**
     * @param class-string $class
     */
    public function resolve(string $class): object;
}
