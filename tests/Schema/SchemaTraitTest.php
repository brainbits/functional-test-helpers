<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Schema;

use Brainbits\FunctionalTestHelpers\Schema\DataBuilder;
use Brainbits\FunctionalTestHelpers\Schema\SchemaBuilder;
use Brainbits\FunctionalTestHelpers\Schema\SchemaTrait;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;

use function getenv;
use function Safe\putenv;
use function sprintf;

/** @covers \Brainbits\FunctionalTestHelpers\Schema\SchemaTrait */
final class SchemaTraitTest extends TestCase
{
    use SchemaTrait;

    private string $oldEnvUsePreInitializedSchema;

    protected function setUp(): void
    {
        $this->oldEnvUsePreInitializedSchema = (string) getenv('USE_PRE_INITIALIZED_SCHEMA');
    }

    protected function tearDown(): void
    {
        putenv(sprintf('USE_PRE_INITIALIZED_SCHEMA=%s', $this->oldEnvUsePreInitializedSchema));
    }

    public function testFixtureFromConnectionWithTableNameQuote(): void
    {
        $schemaBuilder = $this->createSchemaBuilder();
        $dataBuilder = $this->createDataBuilder($schemaBuilder);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->any())
            ->method('getParams')
            ->willReturn(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->expects($this->any())
            ->method('getDatabasePlatform')
            ->willReturn(new SqlitePlatform());
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with('CREATE TABLE foo (bar VARCHAR(255) NOT NULL)');
        $connection->expects($this->once())
            ->method('insert')
            ->with('"foo"', ['"bar"' => 'baz']);

        $this->fixtureFromConnection(
            $connection,
            $schemaBuilder,
            $dataBuilder,
            static function ($dataBuilder): void {
                $dataBuilder->foo('baz');
            },
        );
    }

    public function testFixtureFromConnectionWithPreInitializedSchema(): void
    {
        putenv('USE_PRE_INITIALIZED_SCHEMA=1');

        $schemaBuilder = $this->createSchemaBuilder();
        $dataBuilder = $this->createDataBuilder($schemaBuilder);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->any())
            ->method('getParams')
            ->willReturn(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->expects($this->any())
            ->method('getDatabasePlatform')
            ->willReturn(new SqlitePlatform());
        $connection->expects($this->never())
            ->method('executeStatement');
        $connection->expects($this->once())
            ->method('insert')
            ->with('"foo"', ['"bar"' => 'baz']);

        $this->fixtureFromConnection(
            $connection,
            $schemaBuilder,
            $dataBuilder,
            static function ($dataBuilder): void {
                $dataBuilder->foo('baz');
            },
        );
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

            public function foo(): void
            {
                $table = $this->schema->createTable('foo');
                $table->addColumn('bar', 'string');
            }
        };
    }

    private function createDataBuilder(SchemaBuilder|null $schemaBuilder = null): DataBuilder
    {
        if (!$schemaBuilder) {
            $schemaBuilder = $this->createSchemaBuilder();
        }

        return new class ($schemaBuilder) implements DataBuilder {
            /** @var mixed[] */
            private array $data = [];

            public function __construct(private SchemaBuilder $schemaBuilder)
            {
            }

            public static function create(SchemaBuilder $schemaBuilder): DataBuilder
            {
                return new self($schemaBuilder);
            }

            /** @return mixed[] */
            public function getData(): array
            {
                return $this->data;
            }

            public function foo(string $bar): void
            {
                $this->schemaBuilder->foo();

                $this->data['foo'][] = ['bar' => $bar];
            }
        };
    }
}
