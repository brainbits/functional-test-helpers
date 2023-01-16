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
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Brainbits\FunctionalTestHelpers\Schema\SchemaTrait */
final class SchemaTraitTest extends TestCase
{
    use ProphecyTrait;
    use SchemaTrait;

    public function testApplySchema(): void
    {
        $schemaBuilder = $this->createSchemaBuilder();
        $schemaBuilder->foo();

        $connection = $this->prophesize(Connection::class);
        $connection->getDatabasePlatform()
            ->willReturn(new SqlitePlatform());
        $connection->executeStatement('CREATE TABLE foo (bar VARCHAR(255) NOT NULL)')
            ->shouldBeCalled();

        $this->applySchema($schemaBuilder, $connection->reveal());
    }

    public function testApplyDataWithQuoteTableName(): void
    {
        $dataBuilder = $this->createDataBuilder();
        $dataBuilder->foo('baz');

        $connection = $this->prophesize(Connection::class);
        $connection->getDatabasePlatform()
            ->willReturn(new SqlitePlatform());
        $connection->quoteIdentifier('foo')
            ->willReturn('#foo#');
        $connection->insert('#foo#', ['bar' => 'baz'])
            ->shouldBeCalled();

        $this->applyData($dataBuilder, $connection->reveal());
    }

    public function testApplyDataWithoutQuoteTableName(): void
    {
        $dataBuilder = $this->createDataBuilder();
        $dataBuilder->foo('baz');

        $connection = $this->prophesize(Connection::class);
        $connection->getDatabasePlatform()
            ->willReturn(new SqlitePlatform());
        $connection->quoteIdentifier('foo')
            ->shouldNotBeCalled();
        $connection->insert('foo', ['bar' => 'baz'])
            ->shouldBeCalled();

        $this->applyData($dataBuilder, $connection->reveal(), false);
    }

    public function testFixtureFromConnectionWithTableNameQuote(): void
    {
        $schemaBuilder = $this->createSchemaBuilder();
        $dataBuilder = $this->createDataBuilder($schemaBuilder);

        $connection = $this->prophesize(Connection::class);
        $connection->getDatabasePlatform()
            ->willReturn(new SqlitePlatform());
        $connection->executeStatement('CREATE TABLE foo (bar VARCHAR(255) NOT NULL)')
            ->shouldBeCalled();
        $connection->quoteIdentifier('foo')
            ->willReturn('#foo#');
        $connection->insert('#foo#', ['bar' => 'baz'])
            ->shouldBeCalled();

        $this->fixtureFromConnection(
            $connection->reveal(),
            $schemaBuilder,
            $dataBuilder,
            static function ($dataBuilder): void {
                $dataBuilder->foo('baz');
            },
        );
    }

    public function testFixtureFromConnectionWithoutTableNameQuote(): void
    {
        $schemaBuilder = $this->createSchemaBuilder();
        $dataBuilder = $this->createDataBuilder($schemaBuilder);

        $connection = $this->prophesize(Connection::class);
        $connection->getDatabasePlatform()
            ->willReturn(new SqlitePlatform());
        $connection->executeStatement('CREATE TABLE foo (bar VARCHAR(255) NOT NULL)')
            ->shouldBeCalled();
        $connection->quoteIdentifier('foo')
            ->willReturn('foo');
        $connection->insert('foo', ['bar' => 'baz'])
            ->shouldBeCalled();

        $this->fixtureFromConnection(
            $connection->reveal(),
            $schemaBuilder,
            $dataBuilder,
            static function ($dataBuilder): void {
                $dataBuilder->foo('baz');
            },
            false,
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
