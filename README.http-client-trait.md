# brainbits Functional Test Helpers

## HTTP Client Trait

To use the mock http client, configure the `MockRequestBuilderCollection` as `mock_response_factory` in the framework configuration section.

Example symfony config:

```yaml
# config/packages/mock_request_builder.yaml
when@test:
    services:
        Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilderCollection: ~
```

```yaml
# config/packages/framework.yaml
when@test:
    framework:
        test: true
        http_client:
            mock_response_factory: 'Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilderCollection'
```

In your test, you can provide mock responses, which will be matched by the given mock requests.

```php
// MyTest.php

use Brainbits\FunctionalTestHelpers\HttpClientMock\HttpClientMockTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MyTest extends WebTestCase
{
    use HttpClientMockTrait;

    public function testRequest(): void
    {
        $this->mockRequest('GET', 'http://127.0.0.1/my/endpoint')
            ->willRespond($this->mockResponse()->json([]));
    
        // ...

        Assert::assertNotEmpty($this->callStack()->first);
    }
}

```

### Failures, timeouts and streamed responses

`willThrow()` throws when the request is sent. Real network errors and timeouts surface when the
response is read (`getStatusCode()`, `getContent()`, `toArray()`, ...). To test that kind of error handling, use:

```php
$this->mockRequest('GET', 'http://127.0.0.1/my/endpoint')
    ->willTimeout();                                    // TimeoutException when the response is read

$this->mockRequest('GET', 'http://127.0.0.1/other/endpoint')
    ->willFailWithTransportError('Connection refused'); // TransportException when the response is read
```

The response builder can also stream its content in chunks and delay the response:

```php
$this->mockResponse()->chunks(['first ', 'second']);
$this->mockResponse()->json(['ok' => true])->delay(0.5);
$this->mockResponse()->transportError('Connection reset');
$this->mockResponse()->timeout();
```

Use an array, not a generator, for `chunks()` when the response is returned more than once (`willAlwaysRespond()`).

### Call expectations

```php
$this->mockRequest('POST', 'http://127.0.0.1/orders')->once()->willRespond($this->mockResponse());
$this->mockRequest('DELETE', 'http://127.0.0.1/orders/1')->never();
$this->mockRequest('GET', 'http://127.0.0.1/status')->times(3)->willAlwaysRespond($this->mockResponse());
```

`once()`, `never()` and `times()` are checked automatically after each test.

```php
self::assertAllMockResponsesAreConsumed();               // no sequential response was left unused
self::assertRequestMocksAreCalledInOrder($login, $order); // first calls happened in this order
$this->chronologicalCallStack();                         // all calls of all mocks in call order
```

### Flexible matching and response fixtures

```php
$this->mockRequest('POST')
    ->uriMatches('#^https://api\.test/users/\d+$#')       // regular expression for the URI without query string
    ->headerMatches('Authorization', '/^Bearer .+/')      // or header('X-Foo', fn (string $value) => ...)
    ->withoutHeader('X-Debug')                            // header must be absent
    ->queryParams(['page' => '1'])                         // exactly these query parameters
    ->jsonContains(['user' => ['name' => 'peter']])        // JSON body contains these keys and values
    ->willRespond(
        $this->mockResponse()
            ->jsonFile(__DIR__ . '/fixtures/user.json', 201) // or json($data, 201), contentFromFile($path)
            ->addHeader('Set-Cookie', 'a=1')
            ->addHeader('Set-Cookie', 'b=2'),
    );
```
