<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Symfony\Component\HttpClient\Response\MockResponse;

use function array_values;
use function count;
use function is_array;
use function is_string;
use function iterator_to_array;
use function Safe\json_encode;
use function sprintf;
use function str_repeat;
use function str_replace;
use function strtolower;
use function trim;
use function ucwords;
use function usleep;

use const PHP_EOL;

final class MockResponseBuilder
{
    /** @var mixed[] */
    private array $headers = [];
    private string|null $content = null;
    private int|null $code = null;
    /** @var callable|null */
    private mixed $callback = null;
    /** @var list<string>|null */
    private array|null $chunks = null;
    private string|null $transportError = null;
    private bool $timeout = false;
    private float $delay = 0.0;

    public function content(string|null $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Streams the content in the given chunks. A generator is read here, so the response can be returned repeatedly.
     *
     * @param iterable<string> $chunks
     */
    public function chunks(iterable $chunks): self
    {
        $this->chunks = is_array($chunks) ? array_values($chunks) : iterator_to_array($chunks, false);

        return $this;
    }

    /**
     * Fails with a TransportException when the response is read (getStatusCode(), getContent(), ...),
     * like a real network error does, not when the request is sent.
     */
    public function transportError(string $message = 'Transport error'): self
    {
        $this->transportError = $message;

        return $this;
    }

    /**
     * Fails with a TimeoutException when the response is read.
     */
    public function timeout(): self
    {
        $this->timeout = true;

        return $this;
    }

    /**
     * Waits the given number of seconds before the first chunk of the response is returned.
     */
    public function delay(float $seconds): self
    {
        $this->delay = $seconds;

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

        if ($this->transportError !== null) {
            $info['error'] = $this->transportError;

            return new MockResponse('', $info);
        }

        $body = $this->chunks ?? (string) $this->content;

        if ($this->timeout) {
            // an empty chunk is a timeout for Symfony's MockResponse
            $body = ['', ...(is_string($body) ? ($body !== '' ? [$body] : []) : $body)];
        }

        if ($this->delay > 0) {
            $body = self::delayed($body, $this->delay);
        }

        return new MockResponse($body, $info);
    }

    /**
     * @param string|iterable<string> $body
     *
     * @return iterable<string>
     */
    private static function delayed(string|iterable $body, float $seconds): iterable
    {
        usleep((int) ($seconds * 1_000_000));

        yield from is_string($body) ? [$body] : $body;
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

        if ($this->transportError !== null) {
            $string .= ($string ? PHP_EOL : '') . sprintf('Transport error: %s', $this->transportError);
        }

        if ($this->timeout) {
            $string .= ($string ? PHP_EOL : '') . 'Timeout';
        }

        if ($this->content) {
            $string .= ($string ? str_repeat(PHP_EOL, 2) : '');
            $string .= $this->content;
        }

        return trim($string);
    }
}
