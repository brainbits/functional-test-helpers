<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Request;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\HttpFoundation\Response;

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

    #[Before]
    protected function setUpRequest(): void
    {
        self::$requestClient = static::createClient();
    }

    #[After]
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
}
