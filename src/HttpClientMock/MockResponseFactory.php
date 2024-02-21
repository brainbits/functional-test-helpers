<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Symfony\Component\HttpClient\Response\MockResponse;

interface MockResponseFactory
{
    public function fromRequestBuilder(MockRequestBuilder $requestBuilder): MockResponse;
}
