<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Horizon\NullTagResolver;
use ArtemYurov\JobLog\Horizon\TagResolverInterface;
use PHPUnit\Framework\TestCase;

class NullTagResolverTest extends TestCase
{
    public function test_implements_tag_resolver_interface(): void
    {
        $resolver = new NullTagResolver();
        $this->assertInstanceOf(TagResolverInterface::class, $resolver);
    }

    public function test_resolve_returns_empty_array(): void
    {
        $resolver = new NullTagResolver();
        $result = $resolver->resolve(new \stdClass());
        $this->assertSame([], $result);
    }
}
