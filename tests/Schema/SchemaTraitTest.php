<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Schema;

use Brainbits\FunctionalTestHelpers\Schema\DataBuilder;
use Brainbits\FunctionalTestHelpers\Schema\SchemaBuilder;
use Brainbits\FunctionalTestHelpers\Schema\SchemaTrait;
use Brainbits\FunctionalTestHelpers\Schema\Strategy\SchemaStrategy;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function getenv;
use function Safe\putenv;
use function sprintf;

/** @covers \Brainbits\FunctionalTestHelpers\Schema\SchemaTrait */
final class SchemaTraitTest extends TestCase
{
    use SchemaTrait;

    private string $oldEnvUsePreInitializedSchema;

    private MockObject&Connection $connectionMock;
    private MockObject&SchemaStrategy $schemaStrategyMock;

    private SchemaBuilder $schemaBuilder;
    private DataBuilder $dataBuilder;

    protected function setUp(): void
    {
        $this->oldEnvUsePreInitializedSchema = (string) getenv('USE_PRE_INITIALIZED_SCHEMA');

        self::$isSchemaDatabaseClean = false;

        $this->connectionMock = $this->createMock(Connection::class);
        $this->schemaStrategyMock = $this->createMock(SchemaStrategy::class);

        $this->schemaBuilder = $this->createSchemaBuilder();
        $this->dataBuilder = $this->createDataBuilder();
    }

    protected function tearDown(): void
    {
        putenv(sprintf('USE_PRE_INITIALIZED_SCHEMA=%s', $this->oldEnvUsePreInitializedSchema));
    }

    public function testFixtureFromNewConnectionExecutesBuildDataCallback(): void
    {
        $callbackHasBeenCalled = false;

        $this->fixtureFromNewConnection(
            $this->schemaBuilder,
            $this->dataBuilder,
            function ($dataBuilderInCallback) use (&$callbackHasBeenCalled): void {
                self::assertSame($this->dataBuilder, $dataBuilderInCallback);

                $callbackHasBeenCalled = true;
            },
        );

        self::assertTrue($callbackHasBeenCalled);
    }

    public function testFixtureFromNewConnectionAppliesSchemaAndData(): void
    {
        $this->schemaStrategyMock->expects($this->once())
            ->method('applySchema')
            ->with($this->schemaBuilder, $this->isInstanceOf(Connection::class));

        $this->schemaStrategyMock->expects($this->once())
            ->method('applyData')
            ->with($this->dataBuilder, $this->isInstanceOf(Connection::class));

        $this->fixtureFromNewConnection(
            $this->schemaBuilder,
            $this->dataBuilder,
            static fn () => null,
        );
    }

    public function testFixtureFromConnectionExecutesBuildDataCallback(): void
    {
        $callbackHasBeenCalled = false;

        $this->fixtureFromConnection(
            $this->connectionMock,
            $this->schemaBuilder,
            $this->dataBuilder,
            function ($dataBuilderInCallback) use (&$callbackHasBeenCalled): void {
                self::assertSame($this->dataBuilder, $dataBuilderInCallback);

                $callbackHasBeenCalled = true;
            },
        );

        self::assertTrue($callbackHasBeenCalled);
    }

    public function testFixtureFromConnectionAppliesSchemaAndData(): void
    {
        $this->schemaStrategyMock->expects($this->once())
            ->method('applySchema')
            ->with($this->schemaBuilder, $this->connectionMock);

        $this->schemaStrategyMock->expects($this->once())
            ->method('applyData')
            ->with($this->dataBuilder, $this->connectionMock);

        $this->fixtureFromConnection(
            $this->connectionMock,
            $this->schemaBuilder,
            $this->dataBuilder,
            static fn () => null,
        );
    }

    public function testFixtureFromConnectionWithPreInitializedSchema(): void
    {
        putenv('USE_PRE_INITIALIZED_SCHEMA=1');

        $this->schemaStrategyMock->expects($this->never())
            ->method('applySchema');

        $this->schemaStrategyMock->expects($this->once())
            ->method('applyData')
            ->with($this->dataBuilder, $this->connectionMock);

        $this->fixtureFromConnection(
            $this->connectionMock,
            $this->schemaBuilder,
            $this->dataBuilder,
            static fn () => null,
        );
    }

    public function testSchemaIsCleanedUpBeforeApplyingDataIfDatabaseIsDirty(): void
    {
        self::$isSchemaDatabaseClean = false;

        $this->schemaStrategyMock->expects($this->once())
            ->method('deleteData')
            ->with($this->connectionMock);

        $this->schemaStrategyMock->expects($this->once())
            ->method('resetSequences')
            ->with($this->connectionMock);

        $this->fixtureFromConnection(
            $this->connectionMock,
            $this->schemaBuilder,
            $this->dataBuilder,
            static fn () => null,
        );
    }

    public function testSchemaIsNotCleanedUpBeforeApplyingDataIfDatabaseIsClean(): void
    {
        self::$isSchemaDatabaseClean = true;

        $this->schemaStrategyMock->expects($this->never())
            ->method('deleteData');

        $this->schemaStrategyMock->expects($this->never())
            ->method('resetSequences');

        $this->fixtureFromConnection(
            $this->connectionMock,
            $this->schemaBuilder,
            $this->dataBuilder,
            static fn () => null,
        );
    }

    public function testDatabaseIsMarkedDirtyIfDataWasApplied(): void
    {
        self::$isSchemaDatabaseClean = true;

        $this->fixtureFromConnection(
            $this->connectionMock,
            $this->schemaBuilder,
            $this->dataBuilder,
            static fn () => null,
        );

        self::assertFalse(self::$isSchemaDatabaseClean);
    }

    public function testDatabaseIsMarkedCleanAfterFixupIsCleanedUp(): void
    {
        self::$isSchemaDatabaseClean = false;

        $this->fixtureFromConnection(
            $this->connectionMock,
            $this->schemaBuilder,
            $this->dataBuilder,
            static fn () => null,
        );

        $this->cleanupFixture();

        self::assertTrue(self::$isSchemaDatabaseClean);
    }

    private function createSchemaBuilder(): SchemaBuilder
    {
        return new class implements SchemaBuilder {
            private Schema $schema;

            public function __construct()
            {
                $this->schema = new Schema();
            }

            public static function create(): SchemaBuilder
            {
                return new self();
            }

            public function getSchema(): Schema
            {
                return $this->schema;
            }
        };
    }

    private function createDataBuilder(): DataBuilder
    {
        return new class () implements DataBuilder {
            /** @var mixed[] */
            private array $data = [];

            public function __construct()
            {
            }

            public static function create(): DataBuilder
            {
                return new self();
            }

            /** @return mixed[] */
            public function getData(): array
            {
                return $this->data;
            }
        };
    }

    /**
     * Overridden this method to use a schema strategy mock.
     */
    protected function createSchemaStrategy(Connection $connection): SchemaStrategy
    {
        return $this->schemaStrategyMock;
    }
}
