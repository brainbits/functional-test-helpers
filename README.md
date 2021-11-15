# brainbits Functional Test Helpers

## Request Trait

The request trait provides a fluent interface to call your controller actions.

Require the RequestTrait and supply a `build()` method, which must return a `RequestBuilder`.
It might be useful to put this into an abstract `FunctionalTestCase` base class.

In your test, call `$builder = $this->build('<method>', '<uri>')`, which will provide you with a fluent interface for building requests.
The request is done when `$this->request($builder)` is called.
 
```php
// MyTest.php

use Brainbits\FunctionalTestHelpers\Request\RequestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MyTest extends WebTestCase
{
    use RequestTrait;

    public function testGet(): void
    {
        $response = $this->request(
            $this->build('GET', '/my/endpoint')
        );
        
        self::assertSame(200, $response->getStatusCode());
    }
}
```

The request builder provides auth functions to help with token and session based logins.

### Auth Token

If you want to use the `authToken()` method, your need to provide a factory method `createToken()` which creates the token string.

```php
// FunctionalTestCase.php

use Brainbits\FunctionalTestHelpers\Request\RequestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MyTokenTest extends WebTestCase
{
    use RequestTrait;

    protected function createToken(): callable
    {
    	 return static fn (string $user, array $roles = []) => return 'my_secret_token';
    }

    public function testGet(): void
    {
        $response = $this->request(
            $this->build('GET', '/my/endpoint')
            	   ->authToken('my_user')
        );
        
        // will send header Authorization: Bearer my_secret_token
        
        self::assertSame(200, $response->getStatusCode());
    }
}
```

### Auth Login


If you want to use the `authLogin()` method, your need to provide a factory method `findUser()` which returns the user to be logged in the firewall.

```php
// FunctionalTestCase.php

use Brainbits\FunctionalTestHelpers\Request\RequestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MyTokenTest extends WebTestCase
{
    use RequestTrait;

    protected function findUser(): callable
    {
    	 return static fn (string $user, array $roles = []) => return new User(1, $username);
    }

    public function testGet(): void
    {
        $response = $this->request(
            $this->build('GET', '/my/endpoint')
            	   ->authLogin('my_user')
        );
        
        // will send login the user "my_user" in the firewall
        
        self::assertSame(200, $response->getStatusCode());
    }
}
```

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

## HTTP Client Trait

To use the mock http client, replace your desired http_client with the `MockHttpClient`, and provide it with a `MockRequestBuilderCollection`.

Example symfony config:

```yaml
# config/packages/test/http_client.yaml
services:
    http_mock_client:
        class: Symfony\Component\HttpClient\MockHttpClient
        public: true
        arguments:
            - '@Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilderCollection'
            - 'http://127.0.0.1/'

    http_client: "@http_mock_client"

    Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilderCollection:
        public: true
        arguments:
            - '@Brainbits\FunctionalTestHelpers\HttpClientMock\SymfonyMockResponseFactory'

    Brainbits\FunctionalTestHelpers\HttpClientMock\SymfonyMockResponseFactory: ~
```

In your test, you can provide mock responses, which will be matched by the given mock requests.

```php
// MyTest.php

public function testRequest(): void
{
    $this->mockRequest('GET', 'http://127.0.0.1/my/endpoint')
        ->willRespond($this->mockResponse()->json([]));
    
    // ...

    Assert::assertNotEmpty($this->callStack()->first);
}

```

## Snapshot Trait

To use snapshot tests, use the `SnapshotTrait` in your test class.

Optionally modify the default path of the `__snapshot__` directories appropriate to your
PSR-4 autoload configuration in `composer.json` by overwriting the `snapshotPath()` method.

```php
// FunctionalTestCase.php

use Brainbits\FunctionalTestHelpers\Snapshot\SnapshotTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class FunctionalTestCase extends WebTestCase
{
    use SnapshotTrait;

    /**
     * Overwrite Snapshot::snapshotPath() to locate __snapshot__ directory next to the test classes.
     */
    private function snapshotPath(): string
    {
        // parent directory is the base path of all tests    
        $basePath = dirname(__DIR__);

        // convert namespace to path
        $path = preg_replace('/\\\\[^\\\\]+$/', '', static::class);
        $path = str_replace('\\', '/', $path);
        
        // strip 'App/Tests' from path
        $path = substr($path, strlen('App/Tests/'));

        return $basePath . '/' . $path;
    }
}
```

Call assertions to verify snapshot. The snapshot file is created automatically on the first run.

```php
public function testJsonFactoryCreatesJsonCorrectly()
{
    $jsonFactory = new JsonFactory();

    $json = $jsonFactory->createJson();

    $this->assertMatchesJsonSnapshot($json);
}
```

To recreate/update snapshot files run PHPUnit with the environment variable `UPDATE_SNAPSHOTS`.

### Snapshot Assertions
* Array `assertMatchesArraySnapshot()`
* JSON `assertMatchesJsonSnapshot()`
* XML `assertMatchesXmlSnapshot()`

```shell
UPDATE_SNAPSHOTS=1 phpunit
UPDATE_SNAPSHOTS=1 phpunit tests/Functinoal/MyTest.php
```
