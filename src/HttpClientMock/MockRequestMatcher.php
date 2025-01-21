<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use DOMDocument;
use PHPUnit\Framework\AssertionFailedError;

use function is_callable;
use function is_string;

final class MockRequestMatcher
{
    private Compare $compare;

    public function __construct()
    {
        $this->compare = new Compare();
    }

    public function __invoke(MockRequestBuilder $expectation, MockRequestBuilder $realRequest): MockRequestMatch
    {
        if ($expectation->isEmpty()) {
            return MockRequestMatch::empty();
        }

        if ($expectation->getMethod() !== null && $expectation->getMethod() !== $realRequest->getMethod()) {
            return MockRequestMatch::mismatchingMethod($expectation->getMethod(), $realRequest->getMethod());
        }

        if ($expectation->getUri() !== null) {
            $expectedUri = $expectation->getUri();
            $realUri = $realRequest->getUri();

            if (is_callable($expectedUri) && !$expectedUri($realUri, $expectation->getUriParams())) {
                return MockRequestMatch::mismatchingUri(
                    self::uriAsString($expectedUri),
                    self::uriAsString($realUri),
                );
            }

            if (is_string($expectedUri) && $expectedUri !== $realUri) {
                return MockRequestMatch::mismatchingUri(
                    self::uriAsString($expectedUri),
                    self::uriAsString($realUri),
                );
            }
        }

        // phpcs:disable Generic.Files.LineLength.TooLong
        if ($expectation->hasQueryParams() && !($this->compare)($expectation->getQueryParams(), $realRequest->getQueryParams())) {
            return MockRequestMatch::mismatchingQueryParams($expectation->getQueryParams(), $realRequest->getQueryParams());
        }

        // phpcs:disable Generic.Files.LineLength.TooLong
        if ($expectation->hasHeaders()) {
            foreach ($expectation->getHeaders() as $key => $value) {
                if (!$realRequest->hasHeader($key)) {
                    return MockRequestMatch::missingHeader($key, $value);
                }

                if (!($this->compare)($value, $realRequest->getHeader($key))) {
                    return MockRequestMatch::mismatchingHeader($key, $value, $realRequest->getHeader($key));
                }
            }
        }

        // phpcs:enable Generic.Files.LineLength.TooLong

        if ($expectation->isJson()) {
            if (!$this->isJsonContentMatching($expectation, $realRequest)) {
                return MockRequestMatch::mismatchingJsonContent(
                    $expectation->getContent(),
                    $realRequest->getContent(),
                );
            }
        } elseif ($expectation->isXml()) {
            if (!$this->isXmlContentMatching($expectation, $realRequest)) {
                return MockRequestMatch::mismatchingXmlContent(
                    $expectation->getContent(),
                    $realRequest->getContent(),
                );
            }
        } elseif ($expectation->hasRequestParams()) {
            if (!$this->isRequestParamsMatching($expectation, $realRequest)) {
                return MockRequestMatch::mismatchingRequestParameterContent(
                    $expectation->getContent(),
                    $realRequest->getContent(),
                );
            }
        } elseif ($expectation->hasContent()) {
            if (!$this->isPlainContentMatching($expectation, $realRequest)) {
                return MockRequestMatch::mismatchingContent(
                    $expectation->getContent(),
                    $realRequest->getContent(),
                );
            }
        }

        if ($expectation->getMultiparts() !== null) {
            if (!$this->isMultipartMatching($expectation, $realRequest)) {
                return MockRequestMatch::mismatchingMultiparts(
                    $expectation->getMultiparts(),
                    $this->reduceMultiparts($expectation, $realRequest),
                );
            }
        }

        if ($expectation->getThat() !== null) {
            try {
                $reason = ($expectation->getThat())($expectation, $realRequest);
            } catch (AssertionFailedError $e) {
                return MockRequestMatch::mismatchingThat($e->getMessage());
            }

            if ($reason) {
                return MockRequestMatch::mismatchingThat($reason);
            }
        }

        $match = MockRequestMatch::create();

        if ($expectation->getMethod() !== null && $expectation->getMethod() === $realRequest->getMethod()) {
            $match->matchesMethod($expectation->getMethod());
        }

        if ($expectation->getUri() !== null) {
            $expectedUri = $expectation->getUri();
            $realUri = $realRequest->getUri();

            if (is_callable($expectedUri)) {
                if ($expectedUri($realUri, $expectation->getUriParams())) {
                    $match->matchesUri('<callable>');
                }
            } else {
                if ($expectation->getUri() === $realRequest->getUri()) {
                    $match->matchesUri($expectation->getUri());
                }
            }
        }

        if (
            $expectation->hasQueryParams()
            && ($this->compare)($expectation->getQueryParams(), $realRequest->getQueryParams())
        ) {
            $match->matchesQueryParams($expectation->getQueryParams());
        }

        if (
            $this->isPlainContentMatching($expectation, $realRequest)
            || $this->isJsonContentMatching($expectation, $realRequest)
            || $this->isRequestParamsMatching($expectation, $realRequest)
        ) {
            $match->matchesContent($expectation->getContent());
        }

        if ($expectation->getMultiparts() !== null) {
            $match->matchesMultiparts($expectation->getMultiparts());
        }

        return $match;
    }

    private function isPlainContentMatching(MockRequestBuilder $expectation, MockRequestBuilder $realRequest): bool
    {
        if (!$expectation->hasContent() || !$realRequest->hasContent()) {
            return false;
        }

        return $expectation->getContent() === $realRequest->getContent();
    }

    /** @return array<string, array{mimetype?: string|null, filename?: string|null, content?: string|null}> */
    private function reduceMultiparts(MockRequestBuilder $expectation, MockRequestBuilder $realRequest): array
    {
        $realMultiparts = $realRequest->getMultiparts();
        $expectationMultiparts = $expectation->getMultiparts();

        $reducedMultiparts = $realMultiparts;
        foreach ($expectationMultiparts as $key => $data) {
            if (!($realMultiparts[$key] ?? false)) {
                continue;
            }

            $reducedMultiparts[$key] = [];
            foreach ($data as $name => $value) {
                $reducedMultiparts[$key][$name] = $value !== null ? $realMultiparts[$key][$name] : null;
            }
        }

        return $reducedMultiparts;
    }

    private function isMultipartMatching(MockRequestBuilder $expectation, MockRequestBuilder $realRequest): bool
    {
        if (!$expectation->hasMultiparts() && !$realRequest->hasMultiparts()) {
            return true;
        }

        if (!$expectation->hasMultiparts() || !$realRequest->hasMultiparts()) {
            return false;
        }

        $realMultiparts = $this->reduceMultiparts($expectation, $realRequest);

        return ($this->compare)($expectation->getMultiparts(), $realMultiparts);
    }

    private function isJsonContentMatching(MockRequestBuilder $expectation, MockRequestBuilder $realRequest): bool
    {
        if (!$expectation->hasContent() || !$expectation->isJson()) {
            return false;
        }

        if (!$realRequest->hasContent() || !$realRequest->isJson()) {
            return false;
        }

        return ($this->compare)($expectation->getJson(), $realRequest->getJson());
    }

    private function isXmlContentMatching(MockRequestBuilder $expectation, MockRequestBuilder $realRequest): bool
    {
        if (!$expectation->hasContent() || !$expectation->isXml()) {
            return false;
        }

        if (!$realRequest->hasContent() || !$realRequest->isXml()) {
            return false;
        }

        $expectedDom = new DOMDocument();
        $expectedDom->preserveWhiteSpace = false;
        $expectedDom->formatOutput = true;
        $expectedDom->loadXML($expectation->getContent());

        $realDom = new DOMDocument();
        $realDom->preserveWhiteSpace = false;
        $realDom->formatOutput = true;
        $realDom->loadXML($realRequest->getContent());

        return $expectedDom->saveXML() === $realDom->saveXML();
    }

    private function isRequestParamsMatching(MockRequestBuilder $expectation, MockRequestBuilder $realRequest): bool
    {
        if (!$expectation->hasContent() || !$expectation->hasRequestParams()) {
            return false;
        }

        if (!$realRequest->hasContent() || !$realRequest->hasRequestParams()) {
            return false;
        }

        return ($this->compare)($expectation->getRequestParams(), $realRequest->getRequestParams());
    }

    private static function uriAsString(string|callable|null $uri): string // phpcs:ignore Generic.Files.LineLength.TooLong,SlevomatCodingStandard.TypeHints.ParameterTypeHintSpacing.NoSpaceBetweenTypeHintAndParameter
    {
        if (is_callable($uri)) {
            return '<callable>';
        }

        return (string) $uri;
    }
}
