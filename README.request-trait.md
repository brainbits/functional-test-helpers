# brainbits Functional Test Helpers

## Request Trait

The request trait provides a fluent interface to call your controller actions.

Use the `RequestTrait` in a `WebTestCase`, for example in an abstract `FunctionalTestCase` base class.
In your test, call `$this->build('<method>', '<uri>')` to get a `RequestBuilder`. The request is sent
when `$this->request($builder)` is called, which returns the response.

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
                ->queryParams(['page' => 2])
                ->acceptJson(),
        );

        self::assertSame(200, $response->getStatusCode());
    }
}
```

### Building requests

```php
$this->build('POST', '/users')
    ->json(['name' => 'peter'])                 // JSON body with content type
    ->form(['name' => 'peter'])                 // or an application/x-www-form-urlencoded form
    ->queryParam('filter', 'active')            // single query parameter
    ->queryParams(['ids' => [1, 2]])            // like http_build_query()
    ->header('X-Request-Id', 'abc')             // mapped to HTTP_X_REQUEST_ID
    ->ajax()                                    // X-Requested-With: XMLHttpRequest
    ->acceptLanguage('de')
    ->host('app.test')
    ->sessionValue('cart', ['item' => 1])       // stored in the session before the request
    ->followRedirects()                         // for this request only
    ->disableReboot();                          // keep the kernel for the following requests
```

### File uploads

```php
$this->build('POST', '/documents')
    ->fileByPath('document', __DIR__ . '/fixtures/report.pdf', 'application/pdf')
    ->fileByContent('attachment', 'content', 'a.txt', 'text/plain');
```

The temporary files are removed after each test.

### Login

`loginAs()` logs the user in via `KernelBrowser::loginUser()` when the request is sent. The login stays
active for the following requests of the client until `logout()` is called.

```php
$this->request($this->build('GET', '/admin')->loginAs($user));
$this->request($this->build('GET', '/admin')->loginAs($user, 'api', ['some' => 'attribute']));
$this->request($this->build('GET', '/admin')->logout());
```

The default firewall is `main`, override `loginFirewall()` to change it for a test class.

### CSRF tokens

`csrfToken()` stores a token in the session and sends it as request parameter, so Symfony's stateful
CSRF protection (e.g. forms) accepts the request:

```php
$this->request($this->build('POST', '/delete')->csrfToken('delete-item'));          // sent as _token
$this->request($this->build('POST', '/delete')->csrfToken('delete-item', 'token')); // other field name

// a named form type (here "profile") expects the token nested in the form data
$this->request(
    $this->build('POST', '/profile')
        ->form(['profile' => ['name' => 'peter']])
        ->csrfToken('profile', 'profile[_token]'),
);
```

The token is stored for HTTPS if the request is secure: an `https://` URI, the `HTTPS` server parameter, or a
relative URI after an HTTPS request.

Token ids that are configured as stateless (`framework.csrf_protection.stateless_token_ids`, e.g. `submit`,
`authenticate` and `logout` in the Symfony recipe) are not checked against the session. Symfony validates them
via the `Origin`/`Referer` header instead, so send one with `->header('Origin', 'http://localhost')`.

`generateCsrfToken()` is deprecated: its token is not stored in the session, so stateful CSRF protection rejects it.

### Custom client

Override `createRequestClient()` to create the client with kernel options or server defaults. `loginAs()`,
`disableReboot()` and session values need a `KernelBrowser`:

```php
protected static function createRequestClient(): AbstractBrowser
{
    return static::createClient(['environment' => 'test'], ['HTTP_HOST' => 'app.test']);
}
```

### Auth Token

If you want to use the `authToken()` method, you need to provide a factory method `createToken()` which creates the token string.

```php
final class MyTokenTest extends WebTestCase
{
    use RequestTrait;

    protected function createToken(): callable
    {
        return static fn (string $user, array $roles = []): string => 'my_secret_token';
    }

    public function testGet(): void
    {
        $response = $this->request(
            $this->build('GET', '/my/endpoint')
                ->authToken('my_user'),
        );

        // sends the header Authorization: Bearer my_secret_token

        self::assertSame(200, $response->getStatusCode());
    }
}
```

### Auth Login

`authLogin()` calls the callable returned by your `loginUser()` method with the given identifier. Prefer
`loginAs()` for new code.

```php
final class MyLoginTest extends WebTestCase
{
    use RequestTrait;

    protected function loginUser(): callable
    {
        return static function (string $username): void {
            static::getClient()->loginUser(new User(1, $username));
        };
    }

    public function testGet(): void
    {
        $response = $this->request(
            $this->build('GET', '/my/endpoint')
                ->authLogin('my_user'),
        );

        self::assertSame(200, $response->getStatusCode());
    }
}
```

The older `findUser()` method, which returns a callable that returns the user, still works but is deprecated.

### Request data

`DataBuilder` helps to derive request payloads from a base payload, e.g. to test validation errors:

```php
use Brainbits\FunctionalTestHelpers\Request\DataBuilder;

$data = DataBuilder::from(['name' => 'peter', 'address' => ['city' => 'Berlin']])
    ->with('Hamburg', 'address', 'city')    // set a nested value
    ->without('name');                      // remove a value

$this->request($this->build('POST', '/users')->json($data()));
```

`with()` and `without()` change the builder itself. Create a new builder with `DataBuilder::from()` for each variant.
