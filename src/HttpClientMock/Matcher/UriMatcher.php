<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\UriContainsQueryParameters;
use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;

use function is_callable;
use function sprintf;
use function str_contains;

final readonly class UriMatcher implements Matcher
{
    private mixed $uri;

    public function __construct(string|callable $uri, private UriParams $uriParams)
    {
        if (!is_callable($uri) && str_contains($uri, '?')) {
            throw UriContainsQueryParameters::fromUri($uri);
        }

        $this->uri = $uri;
    }

    public function __invoke(RealRequest $realRequest): Hit|Mismatch
    {
        $realUri = $realRequest->getUri();

        if (is_callable($this->uri)) {
            if (($this->uri)($realUri, $this->uriParams->toArray()) === false) {
                $params = $this->uriParams->toJson();

                return Mismatch::mismatchingUri('<callback(' . $params . ')>', $realUri);
            }

            return Hit::matchesUri($realUri);
        }

        $expectedUri = $this->uriParams->replace($this->uri);

        if ($expectedUri !== $realUri) {
            return Mismatch::mismatchingUri($expectedUri, $realUri);
        }

        return Hit::matchesUri($realUri);
    }

    public function __toString(): string
    {
        return is_callable($this->uri)
            ? 'callback(request.uri, ' . $this->uriParams->toJson() . ') !== false'
            : sprintf('request.uri === "%s"', $this->uriParams->replace($this->uri));
    }
}
