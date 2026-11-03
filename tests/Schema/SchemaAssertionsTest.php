<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Schema;

use Brainbits\FunctionalTestHelpers\Schema\DataBuilder;
use Brainbits\FunctionalTestHelpers\Schema\SchemaBuilder;
use Brainbits\FunctionalTestHelpers\Schema\SchemaTrait;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SchemaAssertionsTest extends TestCase
{
    use SchemaTrait;

    protected function setUp(): void
    {
        $schema = new Schema();
        $table = $schema->createTable('user');
        $table->addColumn('id', 'integer');
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->addColumn('deleted_at', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('active', 'boolean');

        $schemaBuilder = $this->createStub(SchemaBuilder::class);
        $schemaBuilder->method('getSchema')->willReturn($schema);

        $dataBuilder = $this->createStub(DataBuilder::class);
        $dataBuilder->method('getData')->willReturn([
            'user' => [
                ['id' => 1, 'name' => 'peter', 'deleted_at' => null, 'active' => 1],
                ['id' => 2, 'name' => 'paul', 'deleted_at' => '2026-01-01', 'active' => 0],
            ],
        ]);

        $this->fixtureFromNewConnection($schemaBuilder, $dataBuilder, static fn () => null);
    }

    public function testAssertTableRowCount(): void
    {
        $this->assertTableRowCount(2, 'user');
    }

    public function testAssertTableContains(): void
    {
        $this->assertTableContains('user', ['id' => 1, 'name' => 'peter', 'deleted_at' => null]);
        $this->assertTableContains('user', ['name' => 'paul']);
        $this->assertTableContains('user', ['name' => 'peter', 'active' => true]);
        $this->assertTableContains('user', ['name' => 'paul', 'active' => false]);
    }

    public function testAssertTableContainsFails(): void
    {
        try {
            $this->assertTableContains('user', ['name' => 'mary']);
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString(
                'Table user does not contain a row with {"name":"mary"}',
                $e->getMessage(),
            );

            return;
        }

        $this->fail('Assertion did not fail');
    }
}
