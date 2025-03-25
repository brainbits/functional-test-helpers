<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use function is_array;
use function Safe\json_encode;

final readonly class Hit
{
    private function __construct(
        public string $matcher,
        public string|null $key,
        public int $score,
        public string $actual,
    ) {
    }

    public static function catchAll(): self
    {
        return new self('catchAll', null, 1, 'Match everything');
    }

    public static function matchesMethod(string $method): self
    {
        return new self('method', null, 10, $method);
    }

    public static function matchesUri(string $uri): self
    {
        return new self('uri', null, 20, $uri);
    }

    public static function matchesHeader(string $key, string $value): self
    {
        return new self('header', $key, 5, $value);
    }

    /** @param string|mixed[] $value */
    public static function matchesQueryParam(string $key, string|array $value): self
    {
        return new self('queryParam', $key, 5, is_array($value) ? json_encode($value) : $value);
    }

    public static function matchesRequestParam(string $key, string $value): self
    {
        return new self('requestParam', $key, 5, $value);
    }

    public static function matchesContent(string $content): self
    {
        return new self('content', null, 5, $content);
    }

    public static function matchesJson(string $json): self
    {
        return new self('json', null, 5, $json);
    }

    public static function matchesXml(string $xml): self
    {
        return new self('xml', null, 5, $xml);
    }

    /** @param array{name: string, filename?: string, mimetype?: string, content?: string} $multipart */
    public static function matchesMultipart(array $multipart): self
    {
        return new self('multipart', $multipart['name'], 5, $multipart['name']);
    }

    public static function matchesThat(): self
    {
        return new self('that', null, 5, 'Match that-callback');
    }
}
