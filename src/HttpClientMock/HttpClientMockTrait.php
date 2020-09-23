<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Monolog\Logger;
use PHPUnit\Framework\TestCase;

use function assert;
use function Safe\sprintf;
use function ucfirst;

/**
 * @mixin TestCase
 */
trait HttpClientMockTrait
{
    protected ?NoMatchingMockRequestHandler $mockRequestLogHandler = null;

    protected function expectNoMismatchingMockRequestsInLog(Logger $logger): void
    {
        $logger->pushHandler($this->mockRequestLogHandler = new NoMatchingMockRequestHandler());
    }

    protected function mockRequest(?string $method = null, ?string $uri = null): MockRequestBuilder
    {
        if (!self::$container) {
            static::fail(sprintf(
                'A client must be set to make assertions on it. Did you forget to call "%s::createClient()"?',
                static::class,
            ));
        }

        if (!self::$container->has(MockRequestBuilderCollection::class)) {
            static::fail(sprintf(
                '%s not found, did you forget to include it in your test services?',
                MockRequestBuilderCollection::class
            ));
        }

        $stack = self::$container->get(MockRequestBuilderCollection::class);
        assert($stack instanceof MockRequestBuilderCollection);

        $builder = (new MockRequestBuilder())
            ->method($method)
            ->uri($uri);

        $stack->addMockRequestBuilder($builder);

        return $builder;
    }

    protected function mockResponse(): MockResponseBuilder
    {
        return new MockResponseBuilder();
    }

    protected function callStack(): CallStack
    {
        if (!self::$container) {
            static::fail(sprintf(
                'A client must be set to make assertions on it. Did you forget to call "%s::createClient()"?',
                static::class,
            ));
        }

        if (!self::$container->has(MockRequestBuilderCollection::class)) {
            static::fail(sprintf(
                '%s not found, did you forget to include it in your test services?',
                MockRequestBuilderCollection::class
            ));
        }

        $stack = self::$container->get(MockRequestBuilderCollection::class);
        assert($stack instanceof MockRequestBuilderCollection);

        return $stack->getCallStack();
    }

    /**
     * @param mixed[] $expected
     */
    protected static function assertRequestMockIsCalledWithJson(
        array $expected,
        MockRequestBuilder $actualRequest,
        string $message = ''
    ): void {
        $callStack = $actualRequest->getCallStack();
        self::assertNotEmpty($callStack, $message);

        foreach ($callStack as $call) {
            self::assertEquals(
                $expected,
                $call->getJson(),
                self::mockRequestMessage($message, 'Request not called with expected json data: %s', (string) $call)
            );
        }
    }

    /**
     * @param mixed[] $expected
     */
    protected static function assertRequestMockIsCalledWithRequestParameters(
        array $expected,
        MockRequestBuilder $actualRequest,
        string $message = ''
    ): void {
        $callStack = $actualRequest->getCallStack();
        self::assertNotEmpty($callStack, $message);

        foreach ($callStack as $call) {
            self::assertEquals(
                $expected,
                $call->getRequestParams(),
                self::mockRequestMessage(
                    $message,
                    'Request not called with expected request parameters: %s',
                    (string) $call
                )
            );
        }
    }

    /**
     * @param mixed[] $expected
     */
    protected static function assertRequestMockIsCalledWithQueryParameters(
        array $expected,
        MockRequestBuilder $actualRequest,
        string $message = ''
    ): void {
        $callStack = $actualRequest->getCallStack();
        self::assertNotEmpty($callStack, $message);

        foreach ($callStack as $call) {
            self::assertEquals(
                $expected,
                $call->getQueryParams(),
                self::mockRequestMessage(
                    $message,
                    'Request not called with expected query parameters: %s',
                    (string) $call
                )
            );
        }
    }

    protected static function assertRequestMockIsCalledWithQueryParameter(
        string $name,
        string $expected,
        MockRequestBuilder $actualRequest,
        string $message = ''
    ): void {
        $callStack = $actualRequest->getCallStack();
        self::assertNotEmpty($callStack, $message);

        foreach ($callStack as $call) {
            $queryParams = $call->getQueryParams();
            self::assertIsArray(
                $queryParams,
                self::mockRequestMessage(
                    $message,
                    'Request called without parameters: %s',
                    (string) $call
                )
            );
            self::assertArrayHasKey(
                $name,
                $queryParams,
                self::mockRequestMessage(
                    $message,
                    'Request not called with expected query parameter "%s": %s',
                    $name,
                    (string) $call
                )
            );
            self::assertEquals(
                $expected,
                $queryParams[$name],
                self::mockRequestMessage(
                    $message,
                    'Request not called with expected query parameter value "%s": %s',
                    $name,
                    (string) $call
                )
            );
        }
    }

    /**
     * @param mixed[] $expected
     */
    protected static function assertRequestMockIsCalledWithContent(
        string $expected,
        MockRequestBuilder $actualRequest,
        string $message = ''
    ): void {
        $callStack = $actualRequest->getCallStack();
        self::assertNotEmpty($callStack, $message);

        foreach ($callStack as $call) {
            self::assertSame(
                $expected,
                $call->getContent(),
                self::mockRequestMessage($message, 'Request not called with expected content: %s', (string) $call)
            );
        }
    }

    /**
     * @param mixed[] $expected
     */
    protected static function assertRequestMockIsCalledWithFile(
        string $expectedKey,
        string $expectedFilename,
        int $expectedSize,
        MockRequestBuilder $actualRequest,
        string $message = ''
    ): void {
        $callStack = $actualRequest->getCallStack();
        self::assertNotEmpty($callStack, $message);

        foreach ($callStack as $call) {
            $multiparts = $call->getMultiparts();

            self::assertArrayHasKey(
                $expectedKey,
                $multiparts,
                self::mockRequestMessage(
                    $message,
                    'Request not called with file "%s": %s',
                    $expectedKey,
                    (string) $call
                )
            );

            self::assertSame(
                $expectedFilename,
                $multiparts[$expectedKey]['filename'],
                self::mockRequestMessage(
                    $message,
                    'Request not called with expected filename "%s": %s',
                    $expectedFilename,
                    (string) $call
                )
            );

            self::assertSame(
                $expectedSize,
                $multiparts[$expectedKey]['size'],
                self::mockRequestMessage(
                    $message,
                    'Request not called with expected file size "%s": %s',
                    $expectedSize,
                    (string) $call
                )
            );
        }
    }

    protected static function assertRequestMockIsCalledWithHeaderContaining(
        string $expectedHeader,
        string $expectedSubstring,
        MockRequestBuilder $actualRequest,
        string $message = ''
    ): void {
        $callStack = $actualRequest->getCallStack();
        self::assertNotEmpty($callStack, self::mockRequestMessage($message, 'Request not called', $actualRequest));

        foreach ($callStack as $call) {
            self::assertStringContainsString(
                $expectedSubstring,
                $call->getHeader($expectedHeader),
                self::mockRequestMessage($message, 'Request not called with expected header: %s', (string) $call)
            );
        }
    }

    protected static function assertRequestMockIsCalledWithHeaderNotContaining(
        string $expectedHeader,
        string $expectedSubstring,
        MockRequestBuilder $actualRequest,
        string $message = ''
    ): void {
        $callStack = $actualRequest->getCallStack();
        self::assertNotEmpty($callStack, self::mockRequestMessage($message, 'Request not called', $actualRequest));

        foreach ($callStack as $call) {
            self::assertStringNotContainsString(
                $expectedSubstring,
                $call->getHeader($expectedHeader),
                self::mockRequestMessage($message, 'Request not called with expected header: %s', (string) $call)
            );
        }
    }

    protected static function assertRequestMockIsCalledWithHeaderSame(
        string $expectedHeader,
        string $expectedValue,
        MockRequestBuilder $actualRequest,
        string $message = ''
    ): void {
        $callStack = $actualRequest->getCallStack();
        self::assertNotEmpty(
            $callStack,
            self::mockRequestMessage($message, 'Request not called: %s', (string) $actualRequest)
        );

        foreach ($callStack as $call) {
            self::assertSame(
                $expectedValue,
                $call->getHeader($expectedHeader),
                self::mockRequestMessage($message, 'Request not called with expected header: %s', (string) $call)
            );
        }
    }

    protected static function assertRequestMockIsCalledWithoutHeader(
        string $expectedHeader,
        MockRequestBuilder $actualRequest,
        string $message = ''
    ): void {
        $callStack = $actualRequest->getCallStack();
        self::assertNotEmpty(
            $callStack,
            self::mockRequestMessage($message, 'Request not called: %s', (string) $actualRequest)
        );

        foreach ($callStack as $call) {
            self::assertNull(
                $call->getHeader($expectedHeader),
                self::mockRequestMessage($message, 'Request not called without expected header: %s', (string) $call)
            );
        }
    }

    protected static function assertRequestMockIsCalledNTimes(
        int $expected,
        MockRequestBuilder $actualRequest,
        string $message = ''
    ): void {
        self::assertCount(
            $expected,
            $actualRequest->getCallStack(),
            self::mockRequestMessage($message, 'Request not called expected times: %s', (string) $actualRequest)
        );
    }

    protected static function assertAllRequestMocksAreCalled(string $message = ''): void
    {
        if (!self::$container) {
            static::fail(self::mockRequestMessage(
                $message,
                'A client must be set to make assertions on it. Did you forget to call "%s::createClient()"?',
                static::class,
            ));
        }

        if (!self::$container->has(MockRequestBuilderCollection::class)) {
            static::fail(self::mockRequestMessage(
                $message,
                '%s not found, did you forget to include it in your test services?',
                MockRequestBuilderCollection::class
            ));
        }

        $stack = self::$container->get(MockRequestBuilderCollection::class);
        assert($stack instanceof MockRequestBuilderCollection);

        foreach ($stack as $request) {
            self::assertFalse(
                $request->getCallStack()->isEmpty(),
                self::mockRequestMessage($message, 'Request not called: %s', (string) $request),
            );
        }
    }

    /**
     * @param mixed ...$values
     */
    protected static function mockRequestMessage(
        string $userMessage,
        string $messageTemplate,
        ...$values
    ): string {
        $message = sprintf($messageTemplate, ...$values);

        return $userMessage !== ''
            ? ucfirst($userMessage) . '. ' . $message
            : $message;
    }
}
