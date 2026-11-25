<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;

use function is_callable;
use function sprintf;

final readonly class ContentMatcher implements CallbackMatcher
{
    private mixed $content;

    public function __construct(string|callable $content)
    {
        $this->content = $content;
    }

    public function __invoke(RealRequest $realRequest): Hit|Mismatch
    {
        $expectedContent = $this->content;
        $realContent = $realRequest->getContent();

        if ($realContent === null) {
            return Mismatch::mismatchingContent(
                is_callable($expectedContent) ? '<callback>' : $expectedContent,
                $realContent,
            );
        }

        if (is_callable($expectedContent)) {
            if ($expectedContent($realContent) === false) {
                return Mismatch::mismatchingContent('<callback>', $realContent);
            }
        } elseif ($expectedContent !== $realContent) {
            return Mismatch::mismatchingContent($expectedContent, $realContent);
        }

        return Hit::matchesContent($realContent);
    }

    public function isCallback(): bool
    {
        return is_callable($this->content);
    }

    public function __toString(): string
    {
        return is_callable($this->content)
            ? 'callback(request.content) !== false'
            : sprintf('request.content === "%s"', $this->content);
    }
}
