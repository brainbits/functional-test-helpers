<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Request;

use Brainbits\FunctionalTestHelpers\Request\RequestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RequestTraitTest extends TestCase
{
    use RequestTrait;

    public function testBuildCreatesBuilder(): void
    {
        $builder = $this->build('POST', 'http://foo');

        $this->assertSame('POST', $builder->getMethod());
        $this->assertSame('http://foo', $builder->getUri());
    }

    public static function createClient(): HttpBrowser
    {
        return new HttpBrowser(new MockHttpClient([new MockResponse('foo')]));
    }
}
