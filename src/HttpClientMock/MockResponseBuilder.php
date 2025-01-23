<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Symfony\Component\HttpClient\Response\MockResponse;

use function count;
use function Safe\json_encode;
use function sprintf;
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
    /** @var callable|null */
    private mixed $callback = null;

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

    /** @param callable(RealRequest $realRequest):MockResponse $callback */
    public function fromCallback(callable $callback): self
    {
        $this->callback = $callback;

        return $this;
    }

    public function getResponse(RealRequest $realRequest): MockResponse
    {
        if ($this->callback) {
            return ($this->callback)($realRequest);
        }

        $info = [];

        if ($this->code) {
            $info['http_code'] = $this->code;
        }

        if (count($this->headers)) {
            $info['response_headers'] = $this->headers;
        }

        $body = (string) $this->content;

        return new MockResponse($body, $info);
    }

    public function __toString(): string
    {
        if ($this->callback) {
            return 'callable(realRequest)';
        }

        $string = '';

        if ($this->code) {
            $string .= sprintf('HTTP Code: %d', $this->code);
        }

        if (count($this->headers)) {
            foreach ($this->headers as $key => $value) {
                $key = str_replace('-', ' ', $key);
                $key = ucwords($key);
                $key = str_replace(' ', '-', $key);

                $string .= sprintf('%s%s: %s', PHP_EOL, $key, $value);
            }
        }

        if ($this->content) {
            $string .= ($string ? str_repeat(PHP_EOL, 2) : '');
            $string .= $this->content;
        }

        return trim($string);
    }
}
