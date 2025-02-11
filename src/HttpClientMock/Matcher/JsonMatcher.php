<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;
use PHPUnit\Util\Json;

use function is_callable;
use function Safe\json_encode;
use function sprintf;

final readonly class JsonMatcher implements Matcher
{
    private mixed $json;

    /** @param mixed[]|callable $json */
    public function __construct(array|callable $json)
    {
        $this->json = $json;
    }

    public function __invoke(RealRequest $realRequest): Hit|Mismatch
    {
        $expectedJson = $this->json;
        $realJson = $realRequest->getJson();

        if (is_callable($expectedJson)) {
            if ($expectedJson($realJson) === false) {
                return Mismatch::mismatchingJsonCallback('<callback>', json_encode($realJson));
            }
        } else {
            $expectedValue = Json::canonicalize(json_encode($expectedJson));
            $realValue = $realJson ? Json::canonicalize(json_encode($realJson)) : null;

            if ($expectedValue !== $realValue) {
                return Mismatch::mismatchingJson(
                    json_encode($expectedJson),
                    $realJson ? json_encode($realJson) : null,
                );
            }
        }

        return Hit::matchesJson(json_encode($realJson));
    }

    public function __toString(): string
    {
        return is_callable($this->json)
            ? 'callback(request.content) !== false'
            : sprintf('request.content === "%s"', json_encode($this->json));
    }
}
