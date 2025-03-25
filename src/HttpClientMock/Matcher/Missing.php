<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use function is_array;
use function Safe\json_encode;

final readonly class Missing
{
    public int $score;

    private function __construct(public string $matcher, public string $key, public string $expected)
    {
        $this->score = 0;
    }

    public static function missingHeader(string $key, string $expected): self
    {
        return new self(
            'header',
            $key,
            $expected,
        );
    }

    /** @param string|mixed[] $expected */
    public static function missingQueryParam(string $key, string|array $expected): self
    {
        return new self(
            'queryParam',
            $key,
            is_array($expected) ? json_encode($expected) : $expected,
        );
    }

    public static function missingRequestParam(string $key, string $expected): self
    {
        return new self(
            'requestParam',
            $key,
            $expected,
        );
    }

    public static function missingMultipart(string $name, string $expected): self
    {
        return new self(
            'multipart',
            $name,
            $expected,
        );
    }
}
