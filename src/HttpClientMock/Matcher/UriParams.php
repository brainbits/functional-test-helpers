<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use Countable;

use function array_keys;
use function array_map;
use function array_values;
use function count;
use function Safe\json_encode;
use function sprintf;
use function str_replace;

final class UriParams implements Countable
{
    /** @param array<string, string> $params */
    public function __construct(private array $params = [])
    {
    }

    public function count(): int
    {
        return count($this->params);
    }

    public function set(string $key, string $value): void
    {
        $this->params[$key] = $value;
    }

    public function has(string $key): bool
    {
        return (bool) ($this->params[$key] ?? false);
    }

    public function get(string $key): string|null
    {
        return $this->params[$key] ?? null;
    }

    public function replace(string $uri): string
    {
        $keys = array_keys($this->params);
        $values = array_values($this->params);
        $placeholders = array_map(static fn ($key) => sprintf('{%s}', $key), $keys);

        return str_replace($placeholders, $values, $uri);
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return $this->params;
    }

    public function toJson(): string
    {
        return $this->params ? json_encode($this->params) : '{}';
    }
}
