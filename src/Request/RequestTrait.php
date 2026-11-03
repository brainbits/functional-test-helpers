<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Request;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

use function assert;
use function method_exists;
use function sprintf;

/** @mixin TestCase */
trait RequestTrait
{
    /** @var AbstractBrowser<covariant object, covariant object>|null */
    private static AbstractBrowser|null $requestClient = null;

    protected function loginUser(): callable
    {
        return static fn () => null;
    }

    protected function createToken(): callable
    {
        return static fn () => null;
    }

    #[Before(12)]
    protected function setUpRequest(): void
    {
        self::$requestClient = static::createClient();
    }

    #[After]
    protected function tearDownRequest(): void
    {
        self::$requestClient = null;
    }

    /** @return AbstractBrowser<covariant object, covariant object> */
    protected static function getRequestClient(): AbstractBrowser
    {
        if (self::$requestClient) {
            return self::$requestClient;
        }

        static::fail(sprintf(
            'A client must be set to make assertions on it. Did you forget to call "%s::createClient()"?',
            __CLASS__,
        ));
    }

    final public function generateCsrfToken(): string
    {
        $client = self::getRequestClient();
        assert($client instanceof KernelBrowser);

        $container = $client->getContainer();
        $tokenGenerator = $container->get('security.csrf.token_generator');

        return $tokenGenerator->generateToken();
    }

    final protected function build(string $method, string $uri): RequestBuilder
    {
        if (method_exists($this, 'findUser')) {
            // deprecated findUser() returns the user, the login on the request client happens here
            $findUser = $this->findUser();
            $callable = static function (mixed $username) use ($findUser): void {
                $client = self::getRequestClient();
                assert($client instanceof KernelBrowser);

                $client->loginUser($findUser($username));
            };
        } else {
            $callable = $this->loginUser();
        }

        return RequestBuilder::create(
            $callable,
            $this->createToken(),
            $method,
            $uri,
            false,
        );
    }

    final protected function request(RequestBuilder $requestBuilder): Response
    {
        $client = self::getRequestClient();

        if ($requestBuilder->getSessionValues()) {
            $this->applySessionValues($requestBuilder->getSessionValues());
        }

        $client->request(
            $requestBuilder->getMethod(),
            $requestBuilder->getUri(),
            $requestBuilder->getParameters(),
            $requestBuilder->getFiles(),
            $requestBuilder->getServer(),
            $requestBuilder->getContent(),
            $requestBuilder->getChangeHistory(),
        );

        return $client->getResponse();
    }

    /** @param array<string, mixed> $sessionValues */
    private function applySessionValues(array $sessionValues): void
    {
        $client = self::getRequestClient();
        assert($client instanceof KernelBrowser);

        // KernelBrowser::getSession() reuses the session cookie of the client (whatever its name and domain is)
        // or creates one that is valid for every host
        $session = $client->getSession();
        if (!$session instanceof SessionInterface) {
            static::fail('Session values need an enabled session, check framework.session in your test configuration.');
        }

        foreach ($sessionValues as $key => $value) {
            $session->set($key, $value);
        }

        $session->save();
    }
}
