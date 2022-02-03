<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\NoMatchingMockRequest;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

use function assert;
use function Safe\sprintf;
use function ucfirst;

/**
 * @mixin TestCase
 */
trait HttpClientMockTrait
{
    protected function registerNoMatchingMockRequestAsserts(
        EventDispatcherInterface $eventDispatcher,
        Logger ...$loggers,
    ): void {
        $storage = new ArrayObject();

        $callbackHandler = new CallbackHandler(static function ($record) use (&$storage): void {
            if (!($record['context']['exception'] ?? null)) {
                return;
            }

            $exception = $record['context']['exception'];
            while (!($exception instanceof NoMatchingMockRequest) && $exception->getPrevious()) {
                $exception = $exception->getPrevious();
            }

            if (!($exception instanceof NoMatchingMockRequest)) {
                return;
            }

            $storage['exception'] = $exception;
        });

        foreach ($loggers as $logger) {
            $logger->pushHandler($callbackHandler);
        }

        $eventDispatcher->addListener('kernel.exception', static function (ExceptionEvent $event) use ($storage): void {
            $exception = $event->getThrowable();
            while (!($exception instanceof NoMatchingMockRequest) && $exception->getPrevious()) {
                $exception = $exception->getPrevious();
            }

            if (!($exception instanceof NoMatchingMockRequest)) {
                return;
            }

            $storage['exception'] = $exception;
        }, 255);

        $eventDispatcher->addListener('console.error', static function (ConsoleErrorEvent $event) use ($storage): void {
            $exception = $event->getError();
            while (!($exception instanceof NoMatchingMockRequest) && $exception->getPrevious()) {
                $exception = $exception->getPrevious();
            }

            if (!($exception instanceof NoMatchingMockRequest)) {
                return;
            }

            $storage['exception'] = $exception;
        }, 255);

        $eventDispatcher->addListener(
            'kernel.terminate',
            static function (TerminateEvent $event) use ($storage): void {
                if (!($storage['exception'] ?? false) || !$storage['exception'] instanceof NoMatchingMockRequest) {
                    return;
                }

                self::fail($storage['exception']->getMessage());
            },
            255,
        );

        $eventDispatcher->addListener(
            'console.terminate',
            static function (ConsoleTerminateEvent $event) use ($storage): void {
                if (!($storage['exception'] ?? false) || !$storage['exception'] instanceof NoMatchingMockRequest) {
                    return;
                }

                self::fail($storage['exception']->getMessage());
            },
            255,
        );
    }

    protected function mockRequest(?string $method = null, string|callable|null $uri = null): MockRequestBuilder // phpcs:ignore Generic.Files.LineLength.TooLong,SlevomatCodingStandard.TypeHints.ParameterTypeHintSpacing.NoSpaceBetweenTypeHintAndParameter
    {
        if (!self::getContainer()) {
            static::fail(sprintf(
                'A client must be set to make assertions on it. Did you forget to call "%s::createClient()"?',
                static::class,
            ));
        }

        if (!self::getContainer()->has(MockRequestBuilderCollection::class)) {
            static::fail(sprintf(
                '%s not found, did you forget to include it in your test services?',
                MockRequestBuilderCollection::class
            ));
        }

        $stack = self::getContainer()->get(MockRequestBuilderCollection::class);
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
        if (!self::getContainer()) {
            static::fail(sprintf(
                'A client must be set to make assertions on it. Did you forget to call "%s::createClient()"?',
                static::class,
            ));
        }

        if (!self::getContainer()->has(MockRequestBuilderCollection::class)) {
            static::fail(sprintf(
                '%s not found, did you forget to include it in your test services?',
                MockRequestBuilderCollection::class
            ));
        }

        $stack = self::getContainer()->get(MockRequestBuilderCollection::class);
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
        if (!self::getContainer()) {
            static::fail(self::mockRequestMessage(
                $message,
                'A client must be set to make assertions on it. Did you forget to call "%s::createClient()"?',
                static::class,
            ));
        }

        if (!self::getContainer()->has(MockRequestBuilderCollection::class)) {
            static::fail(self::mockRequestMessage(
                $message,
                '%s not found, did you forget to include it in your test services?',
                MockRequestBuilderCollection::class
            ));
        }

        $stack = self::getContainer()->get(MockRequestBuilderCollection::class);
        assert($stack instanceof MockRequestBuilderCollection);

        foreach ($stack as $request) {
            self::assertFalse(
                $request->getCallStack()->isEmpty(),
                self::mockRequestMessage($message, 'Request not called: %s', (string) $request),
            );
        }
    }

    protected static function mockRequestMessage(
        string $userMessage,
        string $messageTemplate,
        mixed ...$values
    ): string {
        $message = sprintf($messageTemplate, ...$values);

        return $userMessage !== ''
            ? ucfirst($userMessage) . '. ' . $message
            : $message;
    }
}
