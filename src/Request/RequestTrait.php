<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Request;

use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

use function assert;
use function method_exists;
use function Safe\sprintf;

/** @mixin TestCase */
trait RequestTrait
{
    private static AbstractBrowser|null $requestClient = null;

    protected function loginUser(): callable
    {
        return static fn () => null;
    }

    protected function createToken(): callable
    {
        return static fn () => null;
    }

    /** @before */
    protected function setUpRequest(): void
    {
        self::$requestClient = static::createClient();
    }

    /** @after */
    protected function tearDownRequest(): void
    {
        self::$requestClient = null;
    }

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

        $container = $client->getContainer();
        $tokenGenerator = $container->get('security.csrf.token_generator');

        return $tokenGenerator->generateToken();
    }

    final protected function build(string $method, string $uri): RequestBuilder
    {
        if (method_exists($this, 'findUser')) {
            $callable = $this->findUser();
            $isFindUser = true;
        } else {
            $callable = $this->loginUser();
            $isFindUser = false;
        }

        return RequestBuilder::create(
            $callable,
            $this->createToken(),
            $method,
            $uri,
            $isFindUser,
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
        assert($client instanceof AbstractBrowser);

        $cookie = $client->getCookieJar()->get('MOCKSESSID');

        // create a new session object
        $container = $client->getContainer();
        $session = $container->get('session.factory')->createSession();
        assert($session instanceof SessionInterface);

        if ($cookie) {
            // get the session id from the session cookie if it exists
            $session->setId($cookie->getValue());
            $session->start();
        } else {
            // or create a new session id and a session cookie
            $session->start();
            $session->save();

            $sessionCookie = new Cookie(
                $session->getName(),
                $session->getId(),
                null,
                null,
                'localhost',
            );
            $client->getCookieJar()->set($sessionCookie);
        }

        foreach ($sessionValues as $key => $value) {
            $session->set($key, $value);
        }

        $session->save();
    }
}
