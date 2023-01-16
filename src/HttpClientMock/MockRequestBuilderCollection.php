<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use IteratorAggregate;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Traversable;

use function array_map;
use function is_callable;

/** @implements IteratorAggregate<MockRequestBuilder> */
final class MockRequestBuilderCollection implements IteratorAggregate
{
    private MockRequestBuilderFactory $requestFactory;
    private MockRequestResolver $requestResolver;
    /** @var MockRequestBuilder[] */
    private array $requestBuilders = [];

    public function __construct(private MockResponseFactory $responseFactory)
    {
        $this->requestFactory = new MockRequestBuilderFactory();
        $this->requestResolver = new MockRequestResolver();
    }

    /** @param mixed[] $options */
    public function __invoke(string $method, string $url, array $options): ResponseInterface
    {
        $realRequest = ($this->requestFactory)($method, $url, $options);

        $requestBuilder = ($this->requestResolver)($this, $realRequest);
        $requestBuilder->called($realRequest);

        if ($requestBuilder->onMatch && is_callable($requestBuilder->onMatch)) {
            ($requestBuilder->onMatch)($realRequest);
        }

        return $this->responseFactory->fromRequestBuilder($requestBuilder);
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

    /** @return Traversable<MockRequestBuilder>|MockRequestBuilder[] */
    public function getIterator(): Traversable
    {
        yield from $this->requestBuilders;
    }
}
