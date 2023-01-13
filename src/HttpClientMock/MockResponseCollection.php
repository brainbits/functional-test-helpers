<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\AddMockResponseFailed;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\NoResponseMock;
use Throwable;

use function current;
use function next;

final class MockResponseCollection
{
    /** @var array<MockResponseBuilder|Throwable> */
    private array $responses = [];

    private bool $isSingleResponse = false;

    public function addAlways(MockResponseBuilder|Throwable $response): void
    {
        if (!$this->isEmpty()) {
            throw AddMockResponseFailed::responseAlreadyAdded();
        }

        $this->responses[] = $response;
        $this->isSingleResponse = true;
    }

    public function add(MockResponseBuilder|Throwable $response): void
    {
        if ($this->isSingleResponse) {
            throw AddMockResponseFailed::singleResponseAlreadyAdded();
        }

        $this->responses[] = $response;
    }

    public function isEmpty(): bool
    {
        return !$this->responses;
    }

    public function next(): MockResponseBuilder|Throwable
    {
        if ($this->isEmpty()) {
            throw NoResponseMock::noResponseAdded();
        }

        if ($this->isSingleResponse) {
            return $this->responses[0];
        }

        $responseBuilder = current($this->responses);

        if (!$responseBuilder) {
            throw NoResponseMock::allResponsesProcessed();
        }

        next($this->responses);

        return $responseBuilder;
    }

    public function hasNext(): bool
    {
        return current($this->responses) !== false;
    }

    public function reset(): void
    {
        $this->responses = [];
        $this->isSingleResponse = false;
    }
}
