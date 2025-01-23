<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;

use function array_diff;
use function count;
use function implode;
use function Safe\json_encode;
use function sprintf;

final readonly class MultipartMatcher implements Matcher
{
    public function __construct(
        private string $name,
        private string|null $mimetype,
        private string|null $filename,
        private string|null $content,
    ) {
    }

    public function __invoke(RealRequest $realRequest): Hit|Mismatch|Missing
    {
        if (!$realRequest->hasMultipart($this->name)) {
            return Missing::missingMultipart($this->name, json_encode($this->createExpectedMultipartArray()));
        }

        $expectedMultipart = $this->createExpectedMultipartArray();
        $reducedMultipart = $this->reduceMultipart($expectedMultipart, $realRequest->getMultipart($this->name));

        if (count(array_diff($expectedMultipart, $reducedMultipart))) {
            return Mismatch::mismatchingMultipart($this->name, $expectedMultipart, $reducedMultipart);
        }

        return Hit::matchesMultipart($reducedMultipart);
    }

    /** @return array{name: string, mimetype?: string, filename?: string, content?: string} */
    private function createExpectedMultipartArray(): array
    {
        $expectedMultiparts = ['name' => $this->name];

        if ($this->mimetype) {
            $expectedMultiparts['mimetype'] = $this->mimetype;
        }

        if ($this->filename) {
            $expectedMultiparts['filename'] = $this->filename;
        }

        if ($this->content) {
            $expectedMultiparts['content'] = $this->content;
        }

        return $expectedMultiparts;
    }

    /**
     * phpcs:disable Generic.Files.LineLength.TooLong
     *
     * @param array{name: string, mimetype?: string|null, filename?: string|null, content?: string|null} $expectedMultipart
     * @param array{name: string, mimetype?: string|null, filename?: string|null, content?: string|null} $realMultipart
     *
     * @return array{name: string, mimetype?: string|null, filename?: string|null, content?: string|null}
     *
     * phpcs:enable Generic.Files.LineLength.TooLong
     */
    private function reduceMultipart(array $expectedMultipart, array $realMultipart): array
    {
        $reducedMultipart = [];
        foreach ($expectedMultipart as $key => $value) {
            $reducedMultipart[$key] = $value !== null && ($realMultipart[$key] ?? false) ? $realMultipart[$key] : null;
        }

        return $reducedMultipart;
    }

    public function __toString(): string
    {
        $parts = [];

        if ($this->filename) {
            $parts[] = 'filename=' . $this->filename;
        }

        if ($this->mimetype) {
            $parts[] = 'mimetype=' . $this->mimetype;
        }

        if ($this->content) {
            $parts[] = 'content=' . $this->content;
        }

        if ($parts) {
            return sprintf(
                '[%s] === request.request[%s]',
                implode(', ', $parts),
                $this->name,
            );
        }

        return sprintf('request.request[%s] is set', $this->name);
    }
}
