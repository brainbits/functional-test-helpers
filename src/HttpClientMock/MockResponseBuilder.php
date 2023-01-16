<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use function array_key_exists;
use function count;
use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\sprintf;
use function str_repeat;
use function str_replace;
use function strtolower;
use function trim;
use function ucwords;

use const PHP_EOL;

final class MockResponseBuilder
{
    /** @var mixed[] */
    private array $headers = [];
    private string|null $content = null;
    private int|null $code = null;

    public function content(string|null $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function header(string $key, string $value): self
    {
        $this->headers[strtolower($key)] = $value;

        return $this;
    }

    public function contentType(string $contentType): self
    {
        $this->header('Content-Type', $contentType);

        return $this;
    }

    public function contentLength(int $contentLength): self
    {
        $this->header('Content-Length', (string) $contentLength);

        return $this;
    }

    public function etag(string $etag): self
    {
        $this->header('ETag', $etag);

        return $this;
    }

    /** @param mixed[]|null $data */
    public function json(array|null $data = null): self
    {
        $this->contentType('application/json');
        $this->content($data !== null ? json_encode($data) : null);

        return $this;
    }

    public function xml(string|null $data = null): self
    {
        $this->contentType('text/xml');
        $this->content($data ?? null);

        return $this;
    }

    public function code(int|null $code): self
    {
        $this->code = $code;

        return $this;
    }

    /** @return mixed[] */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeaders(): bool
    {
        return count($this->headers) > 0;
    }

    public function getHeader(string $key): string|null
    {
        if ($this->hasHeader($key)) {
            return $this->headers[strtolower($key)];
        }

        return null;
    }

    public function hasHeader(string $key): bool
    {
        return array_key_exists(strtolower($key), $this->headers);
    }

    public function getContent(): string|null
    {
        return $this->content;
    }

    public function hasContent(): bool
    {
        return $this->content !== null;
    }

    /** @return mixed[]|null */
    public function getJson(): array|null
    {
        if ($this->content === null) {
            return null;
        }

        return json_decode($this->content, true);
    }

    public function getCode(): int|null
    {
        return $this->code;
    }

    public function hasCode(): bool
    {
        return $this->code !== null;
    }

    public function __toString(): string
    {
        $string = '';

        if ($this->hasCode()) {
            $string .= sprintf('HTTP Code: %d', $this->getCode());
        }

        if ($this->hasHeaders()) {
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
}
