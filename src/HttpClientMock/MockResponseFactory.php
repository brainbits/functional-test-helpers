<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Symfony\Component\HttpClient\Response\MockResponse;

interface MockResponseFactory
{
    /** @return mixed */
    public function fromRequestBuilder(MockRequestBuilder $requestBuilder): MockResponse;
}
