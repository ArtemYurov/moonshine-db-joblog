<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\MoonShine\Resources\JobLogRecordResource;
use ArtemYurov\JobLog\MoonShine\Resources\JobLogResource;
use ArtemYurov\JobLog\MoonShine\Resources\JobLogStepResource;
use ArtemYurov\JobLog\MoonShine\Traits\SuperUserAccess;
use PHPUnit\Framework\TestCase;

/**
 * Job log resources are available to super users only
 */
class SuperUserAccessTest extends TestCase
{
    public function test_all_resources_use_super_user_access(): void
    {
        foreach ([JobLogResource::class, JobLogStepResource::class, JobLogRecordResource::class] as $resource) {
            $this->assertContains(SuperUserAccess::class, class_uses($resource), $resource);
        }
    }
}
