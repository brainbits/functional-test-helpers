<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Countable;
use IteratorAggregate;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Traversable;

use function array_map;
use function count;
use function is_callable;

/** @implements IteratorAggregate<MockRequestBuilder> */
final class MockRequestBuilderCollection implements IteratorAggregate, Countable
{
    private RealRequestFactory $requestFactory;
    private MockRequestResolver $requestResolver;
    /** @var MockRequestBuilder[] */
    private array $requestBuilders = [];
    /** @var list<array{MockRequestBuilder, RealRequest}> */
    private array $calls = [];

    public function __construct()
    {
        $this->requestFactory = new RealRequestFactory();
        $this->requestResolver = new MockRequestResolver();
    }

    /** @param mixed[] $options */
    public function __invoke(string $method, string $url, array $options): ResponseInterface
    {
        $realRequest = ($this->requestFactory)($method, $url, $options);

        $requestBuilder = ($this->requestResolver)($this, $realRequest);
        $requestBuilder->assert($realRequest);
        $requestBuilder->called($realRequest);
        $this->calls[] = [$requestBuilder, $realRequest];

        if ($requestBuilder->onMatch && is_callable($requestBuilder->onMatch)) {
            ($requestBuilder->onMatch)($realRequest);
        }

        return $requestBuilder->getResponse($realRequest);
    }

    public function addMockRequestBuilder(MockRequestBuilder $mockRequestBuilder): void
    {
        $this->requestBuilders[] = $mockRequestBuilder;
    }

    public function getCallStack(): CallStack
    {
        $callStacks = array_map(
            static fn ($requestBuilder) => $requestBuilder->getCallStack(),
            $this->requestBuilders,
        );

        return CallStack::fromCallStacks(...$callStacks);
    }

    /**
     * All calls of all mock request builders in the order they were made.
     */
    public function getChronologicalCallStack(): CallStack
    {
        return new CallStack(...array_map(static fn (array $call) => $call[1], $this->calls));
    }

    /**
     * The mock request builders in the order they were called, one entry per call.
     *
     * @return list<MockRequestBuilder>
     */
    public function getCalledRequestBuilders(): array
    {
        return array_map(static fn (array $call) => $call[0], $this->calls);
    }

    /** @return Traversable<MockRequestBuilder> */
    public function getIterator(): Traversable
    {
        yield from $this->requestBuilders;
    }

    public function count(): int
    {
        return count($this->requestBuilders);
    }
}
