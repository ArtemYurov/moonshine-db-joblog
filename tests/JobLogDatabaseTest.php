<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Database\Exceptions\DriverConnectionMismatchException;
use ArtemYurov\JobLog\Database\Exceptions\MissingMongoDbPackageException;
use ArtemYurov\JobLog\Database\Exceptions\UnsupportedDatabaseDriverException;
use ArtemYurov\JobLog\Database\JobLogDatabase;
use ArtemYurov\JobLog\Database\JobLogStores;
use ArtemYurov\JobLog\Database\ModelResolver;
use ArtemYurov\JobLog\Database\Stores\EloquentJobLogStore;
use ArtemYurov\JobLog\Database\Stores\MongoDbJobLogStore;
use ArtemYurov\JobLog\Models\JobLog;
use ArtemYurov\JobLog\Models\Mongo\JobLog as MongoJobLog;
use ArtemYurov\JobLog\Tests\Support\ArrayConfigRepository;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * The agreed driver resolution flow:
 *  - joblog.database.connection null → database.default (DB_CONNECTION);
 *  - joblog.database.driver 'auto' → the connection's actual driver;
 *  - an explicit driver must match the connection;
 *  - drivers outside {pgsql, mongodb} are rejected with the repository link.
 */
class JobLogDatabaseTest extends TestCase
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

        $this->assertSame('pgsql', JobLogDatabase::connectionName());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_explicit_connection_overrides_default(): void
    {
        $config = $this->postgres();
        $config['joblog']['database']['connection'] = 'mongodb';

        $this->withConfig($config);

        $this->assertSame('mongodb', JobLogDatabase::connectionName());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_auto_driver_resolves_from_connection(): void
    {
        $this->withConfig($this->postgres());

        $this->assertSame('pgsql', JobLogDatabase::effectiveDriver());
        $this->assertTrue(JobLogDatabase::isSql());
        $this->assertFalse(JobLogDatabase::isMongo());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_explicit_driver_overrides_auto(): void
    {
        $config = $this->postgres();
        $config['joblog']['database']['driver'] = 'mongodb';

        $this->withConfig($config);

        $this->assertSame('mongodb', JobLogDatabase::effectiveDriver());
        $this->assertTrue(JobLogDatabase::isMongo());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_assert_supported_accepts_pgsql(): void
    {
        $this->withConfig($this->postgres());

        JobLogDatabase::assertSupported();

        $this->assertTrue(JobLogDatabase::shouldRunSqlSchema());
        $this->assertFalse(JobLogDatabase::shouldRunMongoSchema());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_unsupported_driver_is_rejected_with_repository_link(): void
    {
        $config = $this->postgres();
        $config['database']['default'] = 'mysql';

        $this->withConfig($config);

        try {
            JobLogDatabase::assertSupported();
            $this->fail('mysql must be rejected');
        } catch (UnsupportedDatabaseDriverException $e) {
            $this->assertStringContainsString('mysql', $e->getMessage());
            $this->assertStringContainsString(JobLogDatabase::REPOSITORY_ISSUES_URL, $e->getMessage());
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_mismatched_explicit_driver_is_rejected(): void
    {
        $config = $this->postgres();
        $config['joblog']['database']['driver'] = 'mongodb'; // connection is actually pgsql

        $this->withConfig($config);

        $this->expectException(DriverConnectionMismatchException::class);

        JobLogDatabase::assertSupported();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_mongodb_without_package_is_rejected(): void
    {
        if (JobLogDatabase::mongodbPackageInstalled()) {
            $this->markTestSkipped('mongodb/laravel-mongodb is installed in this environment');
        }

        $config = $this->postgres();
        $config['database']['default'] = 'mongodb';

        $this->withConfig($config);

        $this->expectException(MissingMongoDbPackageException::class);

        JobLogDatabase::assertSupported();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_unknown_connection_is_rejected(): void
    {
        $config = $this->postgres();
        $config['joblog']['database']['connection'] = 'missing';

        $this->withConfig($config);

        $this->expectException(UnsupportedDatabaseDriverException::class);

        JobLogDatabase::assertSupported();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_guard_is_lenient_without_database_context(): void
    {
        // Unit harness: no database.default, no explicit connection.
        $this->withConfig(['joblog' => []]);

        JobLogDatabase::guardWhenConfigured();

        // SQL migrations proceed on the application default in that context.
        $this->assertTrue(JobLogDatabase::shouldRunSqlSchema());
        $this->assertFalse(JobLogDatabase::shouldRunMongoSchema());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_model_resolver_returns_sql_models_for_pgsql(): void
    {
        $this->withConfig($this->postgres());

        $this->assertSame(JobLog::class, ModelResolver::jobLog());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_model_resolver_returns_mongo_models_for_mongodb(): void
    {
        $config = $this->postgres();
        $config['database']['default'] = 'mongodb';

        $this->withConfig($config);

        $this->assertSame(MongoJobLog::class, ModelResolver::jobLog());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_store_fallback_constructs_implementation_by_driver(): void
    {
        $this->withConfig($this->postgres());

        $this->assertInstanceOf(EloquentJobLogStore::class, JobLogStores::resolve());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_store_fallback_prefers_container_binding(): void
    {
        $this->withConfig($this->postgres());

        $bound = new EloquentJobLogStore();
        $this->container->instance(\ArtemYurov\JobLog\Database\Contracts\JobLogStore::class, $bound);

        $this->assertSame($bound, JobLogStores::resolve());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_store_fallback_constructs_mongo_store_for_mongodb(): void
    {
        $config = $this->postgres();
        $config['database']['default'] = 'mongodb';

        $this->withConfig($config);

        $this->assertInstanceOf(MongoDbJobLogStore::class, JobLogStores::resolve());
    }
}
