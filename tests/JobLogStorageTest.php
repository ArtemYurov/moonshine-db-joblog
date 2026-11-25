<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Database\Exceptions\DatabaseConfigurationException;
use ArtemYurov\JobLog\Database\JobLogStorage;
use ArtemYurov\JobLog\Database\JobLogModelResolver;
use ArtemYurov\JobLog\Models\JobLog;
use ArtemYurov\JobLog\Models\Mongo\JobLog as MongoJobLog;
use ArtemYurov\JobLog\Tests\Support\ArrayConfigRepository;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Storage resolution:
 *  - joblog.database.connection null → database.default (DB_CONNECTION);
 *  - the driver is always the connection's own;
 *  - drivers outside SUPPORTED_DRIVERS are rejected with the repository link.
 */
class JobLogStorageTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        // app()/config() only inside isolated processes (see the harness pattern).
        require_once __DIR__ . '/Support/integration_helpers.php';

        $this->container = new Container;
        Container::setInstance($this->container);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    private function withConfig(array $config): void
    {
        $this->container->instance('config', new ArrayConfigRepository($config));
    }

    private function postgres(): array
    {
        return [
            'database' => [
                'default' => 'pgsql',
                'connections' => [
                    'pgsql' => ['driver' => 'pgsql'],
                    'mongodb' => ['driver' => 'mongodb'],
                    'mysql' => ['driver' => 'mysql'],
                ],
            ],
            'joblog' => [],
        ];
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_default_connection_resolves_to_database_default(): void
    {
        $this->withConfig($this->postgres());

        $this->assertSame('pgsql', JobLogStorage::connectionName());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_explicit_connection_overrides_default(): void
    {
        $config = $this->postgres();
        $config['joblog']['database']['connection'] = 'mongodb';

        $this->withConfig($config);

        $this->assertSame('mongodb', JobLogStorage::connectionName());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_auto_driver_resolves_from_connection(): void
    {
        $this->withConfig($this->postgres());

        $this->assertSame('pgsql', JobLogStorage::driver());
        $this->assertFalse(JobLogStorage::isMongo());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_assert_supported_accepts_pgsql(): void
    {
        $this->withConfig($this->postgres());

        JobLogStorage::assertSupported();

        $this->assertFalse(JobLogStorage::isMongo());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_unsupported_driver_is_rejected_with_repository_link(): void
    {
        $config = $this->postgres();
        $config['database']['default'] = 'mysql';

        $this->withConfig($config);

        try {
            JobLogStorage::assertSupported();
            $this->fail('mysql must be rejected');
        } catch (DatabaseConfigurationException $e) {
            $this->assertStringContainsString('mysql', $e->getMessage());
            $this->assertStringContainsString(JobLogStorage::REPOSITORY_ISSUES_URL, $e->getMessage());
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_mongodb_without_package_is_rejected(): void
    {
        if (JobLogStorage::hasMongoPackage()) {
            $this->markTestSkipped('mongodb/laravel-mongodb is installed in this environment');
        }

        $config = $this->postgres();
        $config['database']['default'] = 'mongodb';

        $this->withConfig($config);

        $this->expectException(DatabaseConfigurationException::class);
        $this->expectExceptionMessage('composer require mongodb/laravel-mongodb');

        JobLogStorage::assertSupported();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_unknown_connection_is_rejected(): void
    {
        $config = $this->postgres();
        $config['joblog']['database']['connection'] = 'missing';

        $this->withConfig($config);

        $this->expectException(DatabaseConfigurationException::class);
        $this->expectExceptionMessage('connection "missing" is not defined');

        JobLogStorage::assertSupported();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_guard_is_lenient_without_database_context(): void
    {
        // Unit harness: no database.default, no explicit connection.
        $this->withConfig(['joblog' => []]);

        JobLogStorage::guardWhenConfigured();

        $this->assertFalse(JobLogStorage::isMongo());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_model_resolver_returns_sql_models_for_pgsql(): void
    {
        $this->withConfig($this->postgres());

        $this->assertSame(JobLog::class, JobLogModelResolver::log());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_model_resolver_returns_mongo_models_for_mongodb(): void
    {
        $config = $this->postgres();
        $config['database']['default'] = 'mongodb';

        $this->withConfig($config);

        $this->assertSame(MongoJobLog::class, JobLogModelResolver::log());
    }
}
