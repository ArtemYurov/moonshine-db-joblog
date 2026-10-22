<?php

declare(strict_types=1);

namespace ArtemYurov\JobLog\MoonShine\Traits;

use MoonShine\Support\Enums\Ability;

/**
 * Job logs are visible to super users only: MoonShine user contract isSuperUser()
 * (MoonshineUser out of the box; a custom user model implements it).
 */
trait SuperUserAccess
{
    protected function isCan(Ability $ability): bool
    {
        return request()->user()?->isSuperUser() ?? false;
    }
}
