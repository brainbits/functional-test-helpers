<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\AddMockResponseFailed;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\NoResponseMock;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\CatchAllMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\ContentMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\HeaderMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\JsonMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Matcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\MethodMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\MultipartMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\QueryParamMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\RequestParamMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\ThatMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\UriMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\UriParams;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\XmlMatcher;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\File;
use Throwable;

use function base64_encode;
use function count;
use function implode;
use function is_array;
use function sprintf;
use function strtolower;
use function trim;

final class MockRequestBuilder
{
    public string|null $name = null;

    private MockResponseCollection $responses;

    /** @var Matcher[]|array<Matcher[]> */
    private array $matchers = [];

    /** @var array<callable> */
    private array $assertions = [];

    /** @var RealRequest[]  */
    private array $calls = [];

    private UriParams $uriParams;

    /** @var callable|null */
    public mixed $onMatch = null;

    public function __construct()
    {
        $this->responses = new MockResponseCollection();
        $this->uriParams = new UriParams();
    }

    public function getMatcher(): MockRequestMatcher
    {
        $matchers = [];
        foreach ($this->matchers as $matcher) {
            if (is_array($matcher)) {
                foreach ($matcher as $nestedMatcher) {
                    $matchers[] = $nestedMatcher;
                }
            } else {
                $matchers[] = $matcher;
            }
        }

        if (!count($this->matchers)) {
            $matchers[] = new CatchAllMatcher();
        }

        return new MockRequestMatcher($this->name, $matchers);
    }

    public function getResponse(RealRequest $realRequest): MockResponse
    {
        $responseBuilder = $this->nextResponse();

        if ($responseBuilder instanceof Throwable) {
            throw $responseBuilder;
        }

        return $responseBuilder->getResponse($realRequest);
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function method(string|callable $method): self
    {
        $this->matchers['method'] = new MethodMatcher($method);

        return $this;
    }

    public function uri(string|callable $uri): self
    {
        $this->matchers['uri'] = new UriMatcher($uri, $this->uriParams);

        return $this;
    }

    public function header(string $key, string $value): self
    {
        $this->matchers['headers'][strtolower($key)] = new HeaderMatcher($key, $value);

        return $this;
    }

    public function basicAuthentication(string $username, string $password): self
    {
        $token = base64_encode(sprintf('%s:%s', $username, $password));

        return $this->header('Authorization', sprintf('Basic %s', $token));
    }

    public function content(string|callable $content): self
    {
        $this->matchers['content'] = new ContentMatcher($content);

        return $this;
    }

    /** @param callable(string $method, self $requestBuilder): void $assert */
    public function assertMethod(callable $assert): self
    {
        // phpcs:ignore Generic.Files.LineLength.TooLong
        $this->assertions[] = static fn (RealRequest $realRequest, self $requestBuilder) => $assert($realRequest->getMethod(), $requestBuilder);

        return $this;
    }

    /** @param callable(string $uri, self $requestBuilder): void $assert */
    public function assertUri(callable $assert): self
    {
        // phpcs:ignore Generic.Files.LineLength.TooLong
        $this->assertions[] = static fn (RealRequest $realRequest, self $requestBuilder) => $assert($realRequest->getUri(), $requestBuilder);

        return $this;
    }

    /** @param callable(string $content, self $requestBuilder): void $assert */
    public function assertContent(callable $assert): self
    {
        // phpcs:ignore Generic.Files.LineLength.TooLong
        $this->assertions[] = static fn (RealRequest $realRequest, self $requestBuilder) => $assert($realRequest->getContent(), $requestBuilder);

        return $this;
    }

    /** @param callable(RealRequest $realRequest, self $requestBuilder): void $assert */
    public function assertThat(callable $assert): self
    {
        $this->assertions[] = $assert;

        return $this;
    }

    public function assert(RealRequest $realRequest): void
    {
        foreach ($this->assertions as $assertion) {
            $assertion($realRequest, $this);
        }
    }

    /** @param mixed[]|callable $data */
    public function json(array|callable $data): self
    {
        $this->matchers['json'] = new JsonMatcher($data);

        return $this;
    }

    public function xml(string|callable $xml): self
    {
        $this->matchers['xml'] = new XmlMatcher($xml);

        return $this;
    }

    public function queryParam(string $key, string $value, string ...$placeholders): self
    {
        $this->matchers['queryParams'] ??= [];
        $this->matchers['queryParams'][$key] = new QueryParamMatcher($key, $value, $placeholders);

        return $this;
    }

    public function requestParam(string $key, string $value): self
    {
        $this->matchers['requestParams'] ??= [];
        $this->matchers['requestParams'][$key] = new RequestParamMatcher($key, $value);

        /*
        if ((string) $this->content !== '') {
            $this->content .= '&';
        }

        $this->content .= sprintf('%s=%s', urlencode($key), urlencode($value));
        */

        return $this;
    }

    public function multipart(
        string $name,
        string|null $mimetype = null,
        string|null $filename = null,
        string|null $content = null,
    ): self {
        $this->matchers['multiparts'] ??= [];
        $this->matchers['multiparts'][$name] = new MultipartMatcher($name, $mimetype, $filename, $content);

        return $this;
    }

    public function multipartFromFile(string $name, File $file, string|null $mimetype = null): self
    {
        $this->multipart(
            $name,
            mimetype: $mimetype ?? $file->getMimeType(),
            filename: $file->getBasename(),
            content: $file->getContent(),
        );

        return $this;
    }

    /** @param callable(RealRequest $realRequest): ?bool $that */
    public function that(callable $that): self
    {
        $this->matchers['that'] = new ThatMatcher($that);

        return $this;
    }

    public function uriParam(string $key, string $value): self
    {
        $this->uriParams->set($key, $value);

        return $this;
    }

    public function onMatch(callable $fn): self
    {
        $this->onMatch = $fn;

        return $this;
    }

    public function willAlwaysRespond(MockResponseBuilder $responseBuilder): self
    {
        try {
            $this->responses->addAlways($responseBuilder);
        } catch (AddMockResponseFailed $e) {
            throw AddMockResponseFailed::withRequest($e, $this);
        }

        return $this;
    }

    public function willAlwaysThrow(Throwable $exception): self
    {
        try {
            $this->responses->addAlways($exception);
        } catch (AddMockResponseFailed $e) {
            throw AddMockResponseFailed::withRequest($e, $this);
        }

        return $this;
    }

    public function willRespond(MockResponseBuilder $responseBuilder): self
    {
        try {
            $this->responses->add($responseBuilder);
        } catch (AddMockResponseFailed $e) {
            throw AddMockResponseFailed::withRequest($e, $this);
        }

        return $this;
    }

    public function willThrow(Throwable $exception): self
    {
        try {
            $this->responses->add($exception);
        } catch (AddMockResponseFailed $e) {
            throw AddMockResponseFailed::withRequest($e, $this);
        }

        return $this;
    }

    public function hasResponse(): bool
    {
        return !$this->responses->isEmpty();
    }

    public function nextResponse(): MockResponseBuilder|Throwable
    {
        try {
            return $this->responses->next();
        } catch (NoResponseMock $e) {
            throw NoResponseMock::withRequest($e, $this);
        }
    }

    public function hasNextResponse(): bool
    {
        return $this->responses->hasNext();
    }

    public function resetResponses(): self
    {
        $this->responses->reset();

        return $this;
    }

    public function __toString(): string
    {
        $parts = [];

        if ($this->matchers['method'] ?? false) {
            $parts[] = $this->matchers['method'];
        }

        if ($this->matchers['uri'] ?? false) {
            $parts[] = $this->matchers['uri'];
        }

        if ($this->matchers['headers'] ?? false) {
            foreach ($this->matchers['headers'] as $header) {
                $parts[] = (string) $header;
            }
        }

        if ($this->matchers['queryParams'] ?? false) {
            foreach ($this->matchers['queryParams'] as $queryParam) {
                $parts[] = (string) $queryParam;
            }
        }

        if ($this->matchers['requestParams'] ?? false) {
            foreach ($this->matchers['requestParams'] as $requestParam) {
                $parts[] = (string) $requestParam;
            }
        }

        if ($this->matchers['multiparts'] ?? false) {
            foreach ($this->matchers['multiparts'] as $multipart) {
                $parts[] = (string) $multipart;
            }
        }

        if ($this->matchers['json'] ?? false) {
            $parts[] = (string) $this->matchers['json'];
        } elseif ($this->matchers['xml'] ?? false) {
            $parts[] = (string) $this->matchers['xml'];
        } elseif ($this->matchers['content'] ?? false) {
            $parts[] = (string) $this->matchers['content'];
        }

        return trim(implode(' && ', $parts));
    }

    public function called(RealRequest $request): self
    {
        $this->calls[] = $request;

        return $this;
    }

    public function getCallStack(): CallStack
    {
        return new CallStack(...$this->calls);
    }

    public function isEmpty(): bool
    {
        return !$this->matchers;
    }
}
