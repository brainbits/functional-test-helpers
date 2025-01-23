<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\InvalidMockRequest;
use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;
use DOMDocument;

use function count;
use function error_reporting;
use function is_callable;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function sprintf;

final readonly class XmlMatcher implements Matcher
{
    private mixed $xml;

    public function __construct(string|callable $xml)
    {
        if (!is_callable($xml) && !$this->isXmlString($xml)) {
            throw InvalidMockRequest::notXml($xml);
        }

        $this->xml = $xml;
    }

    public function __invoke(RealRequest $realRequest): Hit|Mismatch|Missing
    {
        $expectedXml = $this->xml;
        $realXml = $realRequest->getContent();

        if (is_callable($expectedXml)) {
            if (!$this->isXmlString($realXml)) {
                return Mismatch::mismatchingXml('<callback>', $realXml);
            }

            if ($expectedXml($realXml) === false) {
                return Mismatch::mismatchingXml('<callback>', $realXml);
            }
        } else {
            if (!$realXml || !$this->isXmlString($realXml)) {
                return Mismatch::mismatchingXml($expectedXml, $realXml);
            }

            $expectedDom = new DOMDocument();
            $expectedDom->preserveWhiteSpace = false;
            $expectedDom->formatOutput = true;
            $expectedDom->loadXML($expectedXml);

            $realDom = new DOMDocument();
            $realDom->preserveWhiteSpace = false;
            $realDom->formatOutput = true;
            $realDom->loadXML($realXml);

            if ($expectedDom->saveXML() !== $realDom->saveXML()) {
                return Mismatch::mismatchingXml($expectedXml, $realXml);
            }
        }

        return Hit::matchesXml($realXml);
    }

    public function __toString(): string
    {
        return is_callable($this->xml)
            ? 'callback(request.content) !== false'
            : sprintf('request.content === "%s"', $this->xml);
    }

    private function isXmlString(string $data): bool
    {
        $document = new DOMDocument();
        $internal = libxml_use_internal_errors(true);
        $reporting = error_reporting(0);

        try {
            $document->loadXML($data);

            $errors = libxml_get_errors();
        } finally {
            libxml_use_internal_errors($internal);
            error_reporting($reporting);
        }

        return count($errors) === 0;
    }
}
