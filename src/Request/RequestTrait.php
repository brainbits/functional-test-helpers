<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Request;

use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\HttpFoundation\Response;

use function Safe\sprintf;

/** @mixin TestCase */
trait RequestTrait
{
    private static AbstractBrowser|null $requestClient = null;

    protected function findUser(): callable
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

    final protected function build(string $method, string $uri): RequestBuilder
    {
        return RequestBuilder::create(
            $this->findUser(),
            $this->createToken(),
            $method,
            $uri,
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
