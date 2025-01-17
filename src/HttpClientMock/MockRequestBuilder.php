<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\AddMockResponseFailed;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\InvalidMockRequest;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\NoResponseMock;
use DOMDocument;
use Safe\Exceptions\JsonException;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\File\File;
use Throwable;

use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;
use function base64_encode;
use function count;
use function error_reporting;
use function explode;
use function is_array;
use function is_callable;
use function is_string;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\preg_match;
use function Safe\simplexml_load_string;
use function sprintf;
use function str_contains;
use function str_repeat;
use function str_replace;
use function strpos;
use function strtolower;
use function substr;
use function trim;
use function ucwords;
use function urldecode;
use function urlencode;

use const PHP_EOL;

final class MockRequestBuilder
{
    private string|null $method = null;

    /** @var string|callable|null  */
    private mixed $uri = null;

    /** @var array<string,string>  */
    private array $uriParams = [];

    /** @var mixed[]|null */
    private array|null $headers = null;

    /** @var mixed[]|null */
    private array|null $queryParams = null;

    private string|null $content = null;

    /** @var array<string, array{filename?: string|null, mimetype?: string|null, content?: string|null}>|null */
    private array|null $multiparts = null;

    /** @var callable(MockRequestBuilder $expectation, MockRequestBuilder $realRequest): ?string|null */
    private mixed $that = null;

    private MockResponseCollection $responses;

    /** @var self[] */
    private array $calls = [];

    /** @var callable|null */
    public mixed $onMatch = null;

    public function __construct()
    {
        $this->responses = new MockResponseCollection();
    }

    public function method(string|null $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function uri(string|callable|null $uri): self // phpcs:ignore Generic.Files.LineLength.TooLong,SlevomatCodingStandard.TypeHints.ParameterTypeHintSpacing.NoSpaceBetweenTypeHintAndParameter
    {
        if ($uri === null) {
            $this->uri = null;

            return $this;
        }

        if (is_callable($uri)) {
            $this->uri = $uri;

            return $this;
        }

        $queryParamStart = strpos($uri, '?');

        if ($queryParamStart === false) {
            $this->uri = $uri;
        } else {
            $this->uri = substr($uri, 0, $queryParamStart);
            $this->applyEncodedQueryParams(substr($uri, $queryParamStart + 1));
        }

        return $this;
    }

    public function header(string $key, string $value): self
    {
        $this->headers ??= [];
        $this->headers[strtolower($key)] = $value;

        return $this;
    }

    public function hasHeaders(): bool
    {
        return $this->headers !== null;
    }

    /** @return mixed[]|null */
    public function getHeaders(): array|null
    {
        return $this->headers;
    }

    public function hasHeader(string $key): bool
    {
        return array_key_exists($key, $this->headers);
    }

    public function getHeader(string $key): mixed
    {
        return $this->headers[strtolower($key)] ?? null;
    }

    public function basicAuthentication(string $username, string $password): self
    {
        $token = base64_encode(sprintf('%s:%s', $username, $password));

        return $this->header('Authorization', sprintf('Basic %s', $token));
    }

    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getContent(): string|null
    {
        return $this->content;
    }

    public function hasContent(): bool
    {
        return $this->content !== null;
    }

    /** @param mixed[] $data */
    public function json(array $data): self
    {
        $this->content(json_encode($data));

        return $this;
    }

    public function isJson(): bool
    {
        if (!$this->hasContent()) {
            return false;
        }

        try {
            json_decode($this->content, true);
        } catch (JsonException) {
            return false;
        }

        return true;
    }

    /** @return mixed[] */
    public function getJson(): array|null
    {
        if (!$this->isJson()) {
            return null;
        }

        return json_decode($this->content, true);
    }

    public function xml(string $data): self
    {
        if (!$this->isXmlString($data)) {
            throw InvalidMockRequest::notXml($data);
        }

        $this->content($data);

        return $this;
    }

    public function isXml(): bool
    {
        if (!$this->hasContent()) {
            return false;
        }

        return $this->isXmlString($this->getContent());
    }

    /** @param array<string, string> $namespaces */
    public function getXml(array $namespaces = []): SimpleXMLElement|null
    {
        if (!$this->isXml()) {
            return null;
        }

        $xml = simplexml_load_string($this->content);

        foreach ($namespaces as $prefix => $namespace) {
            $xml->registerXPathNamespace($prefix, $namespace);
        }

        return $xml;
    }

    public function queryParam(string $key, string $value, string ...$placeholders): self
    {
        $this->queryParams ??= [];
        $this->queryParams[$key] = sprintf($value, ...$placeholders);

        return $this;
    }

    /** @return string[] */
    public function getQueryParams(): array|null
    {
        return $this->queryParams;
    }

    public function hasQueryParams(): bool
    {
        return $this->queryParams !== null;
    }

    public function requestParam(string $key, string $value): self
    {
        if ((string) $this->content !== '') {
            $this->content .= '&';
        }

        $this->content .= sprintf('%s=%s', urlencode($key), urlencode($value));

        return $this;
    }

    /** @return string[] */
    public function getRequestParams(): array
    {
        return $this->parseEncodedParams((string) $this->content);
    }

    public function hasRequestParams(): bool
    {
        return (bool) preg_match('/[^=]+=[^=]*(&[^=]+=[^=]*)*/', (string) $this->content) && !$this->isJson();
    }

    public function multipart(
        string $name,
        string|null $mimetype = null,
        string|null $filename = null,
        string|null $content = null,
    ): self {
        $this->multiparts ??= [];
        $this->multiparts[$name] = [];

        if ($mimetype !== null) {
            $this->multiparts[$name]['mimetype'] = $mimetype;
        }

        if ($filename !== null) {
            $this->multiparts[$name]['filename'] = $filename;
        }

        if ($content !== null) {
            $this->multiparts[$name]['content'] = $content;
        }

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

    /** @return array<string, array{filename?: string|null, mimetype?: string|null, content?: string|null}>|null */
    public function getMultiparts(): array|null
    {
        return $this->multiparts;
    }

    public function hasMultiparts(): bool
    {
        return is_array($this->multiparts) && count($this->multiparts) > 0;
    }

    public function uriParam(string $key, mixed $value): self
    {
        $this->uriParams[$key] = (string) $value;

        return $this;
    }

    public function getMethod(): string|null
    {
        return $this->method;
    }

    public function hasUri(): bool
    {
        return $this->uri !== null;
    }

    public function getUri(): string|callable|null
    {
        if (is_string($this->uri)) {
            return $this->replaceUriParams($this->uri, $this->uriParams);
        }

        return $this->uri;
    }

    public function hasUriParams(): bool
    {
        return (bool) $this->uriParams;
    }

    /** @return array<string,string> */
    public function getUriParams(): array
    {
        return $this->uriParams;
    }

    /** @param callable(MockRequestBuilder $expectation, MockRequestBuilder $realRequest): ?string $that */
    public function that(callable $that): self
    {
        $this->that = $that;

        return $this;
    }

    public function hasThat(): bool
    {
        return $this->that !== null;
    }

    public function getThat(): callable|null
    {
        return $this->that;
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

    public function nextResponse(): MockResponseBuilder|Throwable|null
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
        $string = '';

        if ($this->method) {
            $string .= $this->method . ' ';
        }

        if ($this->uri) {
            if (is_callable($this->uri)) {
                $string .= '<callable> ';
            } else {
                $string .= $this->uri . ' ';
            }
        }

        if ($this->headers) {
            foreach ($this->headers as $key => $value) {
                $key = str_replace('-', ' ', $key);
                $key = ucwords($key);
                $key = str_replace(' ', '-', $key);

                $string .= sprintf('%s%s: %s', PHP_EOL, $key, $value);
            }
        }

        if ($this->hasContent()) {
            $string .= ($string ? str_repeat(PHP_EOL, 2) : '');
            $string .= $this->content;
        }

        return trim($string);
    }

    public function called(self $request): self
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
        return $this->method === null &&
               $this->uri === null &&
               $this->headers === null &&
               $this->content === null &&
               $this->multiparts === null;
    }

    /** @param array<string,string> $uriParams */
    private static function replaceUriParams(string $uri, array $uriParams): mixed
    {
        $keys = array_keys($uriParams);
        $values = array_values($uriParams);
        $placeholders = array_map(static fn ($key) => sprintf('{%s}', $key), $keys);

        return str_replace($placeholders, $values, $uri);
    }

    private function applyEncodedQueryParams(string $encodedParams): void
    {
        foreach ($this->parseEncodedParams($encodedParams) as $key => $value) {
            $this->queryParam($key, $value);
        }
    }

    /** @return string[] */
    private function parseEncodedParams(string $encodedParams): array
    {
        if ($encodedParams === '') {
            return [];
        }

        $params = [];

        foreach (explode('&', $encodedParams) as $keyValue) {
            if (str_contains($keyValue, '=')) {
                [$key, $value] = explode('=', (string) $keyValue);
            } else {
                $key = $keyValue;
                $value = '';
            }

            $params[urldecode((string) $key)] = urldecode((string) $value);
        }

        return $params;
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
