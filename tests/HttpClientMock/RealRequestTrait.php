<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;

trait RealRequestTrait
{
    /**
     * @param array<string, string>                                                                      $headers
     * @param mixed[]                                                                                    $json
     * @param array<string, string>                                                                      $queryParams
     * @param array<string, string>                                                                      $requestParams
     * @param array<string, array{name: string, filename?: string, mimetype?: string, content?: string}> $multiparts
     */
    private function createRealRequest(
        string $method = 'DELETE',
        string $uri = '/bar',
        array|null $headers = [],
        string|null $content = null,
        array|null $json = null,
        array|null $queryParams = [],
        array|null $requestParams = [],
        array|null $multiparts = [],
    ): RealRequest {
        return new RealRequest($method, $uri, $headers, $content, $json, $queryParams, $requestParams, $multiparts);
    }
}
