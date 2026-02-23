<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Horizon\TagResolverInterface;
use ArtemYurov\JobLog\Horizon\HorizonTagResolver;
use PHPUnit\Framework\TestCase;

class HorizonTagResolverTest extends TestCase
{
    public function test_implements_tag_resolver_interface(): void
    {
        $resolver = new HorizonTagResolver();
        $this->assertInstanceOf(TagResolverInterface::class, $resolver);
    }
}
