<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use function array_merge;
use function count;

final readonly class MatchResult
{
    /** @param list<Hit|Mismatch|Missing> $results */
    private function __construct(private string|null $name, private array $results)
    {
    }

    public static function create(string|null $name): self
    {
        return new self($name, []);
    }

    public function getName(): string|null
    {
        return $this->name;
    }

    /** @return list<Hit|Mismatch|Missing> */
    public function getResults(): array
    {
        return $this->results;
    }

    public function getScore(): int
    {
        if (!count($this->results)) {
            return 0;
        }

        $score = 0;
        foreach ($this->results as $result) {
            if ($result instanceof Mismatch || $result instanceof Missing) {
                return 0;
            }

            $score += $result->score;
        }

        return $score;
    }

    public function isEmpty(): bool
    {
        return !count($this->results);
    }

    public function isMismatch(): bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        foreach ($this->results as $result) {
            if ($result instanceof Mismatch || $result instanceof Missing) {
                return true;
            }
        }

        return false;
    }

    public function withResult(Hit|Mismatch|Missing $hit): static
    {
        return new self($this->name, array_merge($this->results, [$hit]));
    }
}
