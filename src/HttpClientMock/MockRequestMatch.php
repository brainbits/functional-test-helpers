<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Stringable;

use function Safe\json_encode;
use function Safe\sprintf;

final class MockRequestMatch
{
    /** @var mixed[] */
    private array $matches = [];

    private function __construct(private int $score = 0, private string|null $reason = null)
    {
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function isMismatch(): bool
    {
        return $this->score === 0;
    }

    public function getReason(): string|null
    {
        return $this->reason;
    }

    /** @return mixed[] */
    public function getMatches(): array
    {
        return $this->matches;
    }

    public function matchesMethod(string $method): void
    {
        $this->score += 10;
        $this->matches['method'] = $method;
    }

    public function matchesUri(string $uri): void
    {
        $this->score += 20;
        $this->matches['uri'] = $uri;
    }

    /** @param mixed[] $queryParams */
    public function matchesQueryParams(array $queryParams): void
    {
        $this->score += 5;
        $this->matches['queryParams'] = $queryParams;
    }

    public function matchesContent(string $content): void
    {
        $this->score += 5;
        $this->matches['content'] = $content;
    }

    /** @param mixed[] $multiparts */
    public function matchesMultiparts(array $multiparts): void
    {
        $this->score += 5;
        $this->matches['multiparts'] = $multiparts;
    }

    public static function create(): self
    {
        $match = new self();
        $match->score = 0;

        return $match;
    }

    public static function empty(): self
    {
        return new self(1);
    }

    public static function mismatchingMethod(string $method, string|null $otherMethod): self
    {
        return new self(0, sprintf('Mismatching method, expected %s, got %s', $method, $otherMethod ?? 'NULL'));
    }

    public static function mismatchingUri(string $uri, string|null $otherUri): self
    {
        return new self(0, sprintf('Mismatching uri, expected %s, got %s', $uri, $otherUri ?? 'NULL'));
    }

    public static function mismatchingContent(string $content, string|null $otherContent): self
    {
        return new self(0, sprintf('Mismatching content, expected %s, got %s', $content, $otherContent ?? 'NULL'));
    }

    public static function mismatchingJsonContent(string $content, string|null $otherContent): self
    {
        return new self(0, sprintf('Mismatching json content, expected %s, got %s', $content, $otherContent ?? 'NULL'));
    }

    public static function mismatchingXmlContent(string $content, string|null $otherContent): self
    {
        return new self(0, sprintf('Mismatching xml content, expected %s, got %s', $content, $otherContent ?? 'NULL'));
    }

    public static function mismatchingRequestParameterContent(string $content, string|null $otherContent): self
    {
        return new self(0, sprintf('Mismatching request parameters, expected %s, got %s', $content, $otherContent ?? 'NULL')); // phpcs:ignore Generic.Files.LineLength.TooLong
    }

    /**
     * @param mixed[] $multiparts
     * @param mixed[] $otherMultiparts
     */
    public static function mismatchingMultiparts(array $multiparts, array|null $otherMultiparts): self
    {
        return new self(
            0,
            sprintf(
                'Mismatching multiparts, expected %s, got %s',
                json_encode($multiparts),
                json_encode($otherMultiparts),
            ),
        );
    }

    /** @param mixed $value */
    public static function missingHeader(string $key, string $value): self
    {
        return new self(
            0,
            sprintf(
                'Missing header, expected %s: %s',
                $key,
                json_encode($value),
            ),
        );
    }

    public static function mismatchingHeader(string $key, mixed $value, mixed $otherValue): self
    {
        return new self(
            0,
            sprintf(
                'Mismatching header %s, expected %s, got %s',
                $key,
                json_encode($value),
                json_encode($otherValue),
            ),
        );
    }

    /**
     * @param mixed[] $queryParams
     * @param mixed[] $otherQueryParams
     */
    public static function mismatchingQueryParams(array $queryParams, array|null $otherQueryParams): self
    {
        return new self(
            0,
            sprintf(
                'Mismatching query params, expected %s, got %s',
                json_encode($queryParams),
                json_encode($otherQueryParams),
            ),
        );
    }

    public static function mismatchingThat(string|Stringable $reason): self
    {
        return new self(
            0,
            sprintf('Mismatching that, reason: %s', (string) $reason),
        );
    }
}
