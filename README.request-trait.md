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
