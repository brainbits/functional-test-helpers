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
