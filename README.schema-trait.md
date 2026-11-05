# brainbits Functional Test Helpers

## Schema Trait

To use the schema helper, provide a SchemaBuilder and a DataBuilder implementation, which will handle schema and data creation, and use it in a custom FunctionalTestCase.

```php
// MySchemaBuilder.php

use Brainbits\FunctionalTestHelpers\Schema\SchemaBuilder;
use Doctrine\DBAL\Schema\Schema;

final class MySchemaBuilder implements SchemaBuilder
{
    private Schema $schema;

    private function __construct()
    {
        $this->schema = new Schema();
    }

    public static function create(): self
    {
        return new self();
    }

    public function user(): self
    {
        if ($this->schema->hasTable('user')) {
            return $this;
        }

        $table = $this->schema->createTable('user');
        $table->addColumn('id', 'integer');
        $table->addColumn('username', 'string');
        $table->addColumn('email', 'string');
        $table->addColumn('roles', 'string');

        $table->setPrimaryKey(['id']);

        return $this;
    }

    public function getSchema(): Schema
    {
        return $this->schema;
    }
}
```

```php
// MyDataBuilder.php

use Brainbits\FunctionalTestHelpers\Schema\DataBuilder;
use Brainbits\FunctionalTestHelpers\Schema\SchemaBuilder;

final class MyDataBuilder implements DataBuilder
{
    private MySchemaBuilder $schemaBuilder;
    /** @var mixed[] */
    private array $data;

    private function __construct(MySchemaBuilder $schemaBuilder)
    {
        $this->schemaBuilder = $schemaBuilder;
        $this->data = [];
    }

    public static function create(SchemaBuilder $schemaBuilder): self
    {
        return new self($schemaBuilder);
    }

    /**
     * @param mixed[] $roles
     */
    public function user(int $id, string $username, string $email, array $roles = []): self
    {
        $this->schemaBuilder->user();

        $this->data['user'][] = [
            'id' => $id,
            'username' => $username,
            'email' => $email,
            'roles' => $roles ? serialize($roles) : '',
        ];

        return $this;
    }

    /**
     * @return mixed[]
     */
    public function getData(): array
    {
        return $this->data;
    }
}
```

```php
// FunctionalTestCase.php

use Brainbits\FunctionalTestHelpers\Schema\SchemaTrait;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class FunctionalTestCase extends WebTestCase
{
    use SchemaTrait;

    final protected function setUp(): void
    {
        parent::setUp();

        $schemaBuilder = MySchemaBuilder::create();
        $dataBuilder = MyDataBuilder::create($schemaBuilder);

        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        assert($connection instanceof Connection);

        $this->fixtureFromConnection(
            $connection,
            $schemaBuilder,
            $dataBuilder,
            function (MyDataBuilder $data): void {
                $this->buildData($data);
            },
        );
    }

    abstract protected function buildData(MyDataBuilder $data): void;
}
```

In your test, implement the `buildData()` method. The database and test data will be created before each test.
`fixtureFromNewConnection()` creates an SQLite in-memory connection instead and returns it.


```php
// MyTest.php

final class MyTest extends FunctionalTestCase
{
    public function testGet(): void
    {
        $response = $this->request(
            $this->build('GET', '/my/endpoint')
        );
        
        self::assertSame(200, $response->getStatusCode());
    }
    
    protected function buildData(MyDataBuilder $data): void
    {
        $data->user(1, 'foo', 'foo@baz.com', ['ROLE_USER']);
        $data->user(2, 'bar', 'bar@baz.com', ['ROLE_SUPER_ADMIN']);
    }
}    
```

## Schema strategies

The strategy is detected from the connection: SQLite in memory, SQLite file or MySQL, each with or
without the DAMA doctrine-test-bundle. If the detection doesn't fit your setup, force a strategy with
the `SCHEMA_STRATEGY` environment variable: `sqlite-memory`, `sqlite-file`, `sqlite-file-dama`,
`mysql` or `mysql-dama`.

## Assertions

```php
$this->assertTableRowCount(2, 'user');
$this->assertTableContains('user', ['username' => 'foo', 'deleted_at' => null]);
```

Both use the connection of the last `fixtureFromConnection()` / `fixtureFromNewConnection()` call.
