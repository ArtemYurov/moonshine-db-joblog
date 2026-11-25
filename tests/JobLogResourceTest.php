<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\MoonShine\Resources\JobLogResource;
use ArtemYurov\JobLog\Tests\Support\ArrayConfigRepository;
use Illuminate\Container\Container;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class JobLogResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/Support/integration_helpers.php';

        $container = new Container;
        Container::setInstance($container);
        $container->instance('config', new ArrayConfigRepository([
            'database' => [
                'default' => 'mongodb',
                'connections' => ['mongodb' => ['driver' => 'mongodb']],
            ],
            'joblog' => [],
        ]));
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_mongo_drops_only_the_package_default_with(): void
    {
        $core = $this->createMock(CoreContract::class);

        $this->assertSame([], $this->withOf(new JobLogResource($core)));
        $this->assertSame(['latestStep'], $this->withOf(new class ($core) extends JobLogResource {
            protected array $with = ['latestStep'];
        }));
    }

    private function withOf(JobLogResource $resource): array
    {
        return (new \ReflectionProperty($resource, 'with'))->getValue($resource);
    }
}
