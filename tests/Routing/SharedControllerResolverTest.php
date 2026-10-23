<?php

declare(strict_types=1);

namespace Dromos\Tests\Routing;

use Dromos\Routing\SharedControllerResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SharedControllerResolver::class)]
final class SharedControllerResolverTest extends TestCase
{
    #[Test]
    public function test_it_returns_one_instance_per_class(): void
    {
        $resolver = new SharedControllerResolver();

        $first = $resolver->resolve(\ArrayObject::class);

        $this->assertInstanceOf(\ArrayObject::class, $first);
        $this->assertSame($first, $resolver->resolve(\ArrayObject::class));
        $this->assertSame($first, $resolver->resolve('\\arrayobject'));
        $this->assertNotSame($first, $resolver->resolve(\SplStack::class));
    }

    #[Test]
    public function test_separate_resolvers_do_not_share_instances(): void
    {
        $this->assertNotSame(
            (new SharedControllerResolver())->resolve(\ArrayObject::class),
            (new SharedControllerResolver())->resolve(\ArrayObject::class)
        );
    }
}
