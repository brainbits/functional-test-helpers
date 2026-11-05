<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;

use function sprintf;
use function strtolower;

final readonly class HeaderAbsentMatcher implements Matcher
{
    private string $header;

    public function __construct(string $header)
    {
        $this->header = strtolower($header);
    }

    public function __invoke(RealRequest $realRequest): Hit|Mismatch
    {
        if ($realRequest->hasHeader($this->header)) {
            return Mismatch::mismatchingHeader($this->header, '<absent>', $realRequest->getHeader($this->header));
        }

        return Hit::matchesHeader($this->header, '<absent>');
    }

    public function __toString(): string
    {
        return sprintf('request.header["%s"] is absent', $this->header);
    }
}
