<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;

use function in_array;
use function is_array;
use function Safe\json_encode;
use function sprintf;
use function str_ends_with;
use function substr;
use function vsprintf;

final readonly class QueryParamMatcher implements Matcher
{
    private string $key;

    /** @var string|mixed[] */
    private string|array $value;

    private bool $isArray;

    /**
     * @param string|mixed[] $value
     * @param array<string>  $placeholders
     */
    public function __construct(string $key, string|array $value, array $placeholders)
    {
        $isArray = false;
        if (str_ends_with($key, '[]')) {
            $key = substr($key, 0, -2);
            $isArray = true;
        }

        if (!is_array($value)) {
            $value = vsprintf($value, $placeholders);
        }

        $this->key = $key;
        $this->value = $value;
        $this->isArray = $isArray;
    }

    public function __invoke(RealRequest $realRequest): Hit|Mismatch|Missing
    {
        if (!$realRequest->hasQueryParam($this->key)) {
            return Missing::missingQueryParam($this->key, $this->value);
        }

        $expectedValue = $this->value;
        $realValue = $realRequest->getQueryParam($this->key);

        if (
            (!$this->isArray && $expectedValue !== $realValue) ||
            ($this->isArray && !in_array($expectedValue, $realValue, true))
        ) {
            return Mismatch::mismatchingQueryParam($this->key, $expectedValue, $realValue);
        }

        return Hit::matchesQueryParam($this->key, $realValue);
    }

    public function __toString(): string
    {
        return sprintf(
            'request.queryParams["%s"] === "%s"',
            $this->key,
            is_array($this->value) ? json_encode($this->value) : $this->value,
        );
    }
}
