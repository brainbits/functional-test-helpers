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
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class FunctionalTestCase extends WebTestCase
{
    use SchemaTrait;

    final protected function setUp(): void
    {
        parent::setUp();

        $schemaBuilder = MySchemaBuilder::create();
        $dataBuilder = MyDataBuilder::create($schemaBuilder);

        $this->fixtureFromServiceConnection(
            self::$container->get('doctrine.dbal.default_connection'),
            $schemaBuilder,
            $dataBuilder,
            ,
            function ($data): void {
                $this->buildData($data);
            },
        );
    }

    abstract protected function buildData(MyDataBuilder $data): void;
}
```

In your test, implement the `buildData()` method. The database and test data will be created before each test.


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
        $data->user(1, 'bar', 'bar@baz.com', ['ROLE_SUPER_ADMIN']);
    }
}    
```
