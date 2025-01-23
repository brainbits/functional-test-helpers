<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use PHPUnit\Util\Json;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

use function Safe\json_encode;

use const PHP_EOL;

final readonly class Mismatch
{
    public int $score;

    private function __construct(
        public string $matcher,
        public string|null $key,
        public string $expected,
        public string|null $actual,
        public string|null $diff = null,
    ) {
        $this->score = 0;
    }

    public static function mismatchingMethod(string $method, string|null $otherMethod): self
    {
        return new self(
            'method',
            null,
            $method,
            $otherMethod,
        );
    }

    public static function mismatchingUri(string $uri, string|null $otherUri): self
    {
        return new self(
            'uri',
            null,
            $uri,
            $otherUri,
        );
    }

    public static function mismatchingContent(string $content, string|null $otherContent): self
    {
        return new self(
            'content',
            null,
            $content,
            $otherContent,
        );
    }

    public static function mismatchingJsonCallback(string $content, string|null $otherContent): self
    {
        return new self(
            'json',
            null,
            $content,
            $otherContent,
        );
    }

    public static function mismatchingJson(string $content, string|null $otherContent): self
    {
        $diff = null;
        if ($otherContent) {
            $differ = new Differ(
                new UnifiedDiffOutputBuilder(
                    '--- Expected' . PHP_EOL . '+++ Actual' . PHP_EOL,
                ),
            );
            $diff = $differ->diff(Json::prettify($content), Json::prettify($otherContent));
        }

        return new self(
            'json',
            null,
            $content,
            $otherContent,
            $diff,
        );
    }

    public static function mismatchingXml(string $content, string|null $otherContent): self
    {
        return new self(
            'xml',
            null,
            $content,
            $otherContent,
        );
    }

    public static function mismatchingRequestParam(string $key, string $content, string|null $otherContent): self
    {
        return new self(
            'requestParam',
            $key,
            $content,
            $otherContent,
        );
    }

    /**
     * @param mixed[] $multipart
     * @param mixed[] $otherMultipart
     */
    public static function mismatchingMultipart(string $name, array $multipart, array|null $otherMultipart): self
    {
        return new self(
            'multipart',
            $name,
            json_encode($multipart),
            json_encode($otherMultipart),
        );
    }

    public static function mismatchingHeader(string $key, mixed $value, mixed $otherValue): self
    {
        return new self(
            'header',
            $key,
            $value,
            $otherValue,
        );
    }

    public static function mismatchingQueryParam(string $key, string $value, string $otherValue): self
    {
        return new self(
            'queryParam',
            $key,
            $value,
            $otherValue,
        );
    }

    public static function mismatchingThat(string $reason): self
    {
        return new self(
            'that',
            null,
            '<callable>',
            $reason,
        );
    }
}
