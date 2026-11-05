<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Request;

use Brainbits\FunctionalTestHelpers\Request\RequestTrait;
use Brainbits\FunctionalTestHelpers\Tests\Request\Kernel\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;

use function Safe\json_decode;

final class RequestTraitKernelTest extends WebTestCase
{
    use RequestTrait;

    public function testSessionValuesWithLocalhost(): void
    {
        $response = $this->request($this->build('GET', 'http://localhost/dump')->sessionValue('foo', 'bar'));

        $this->assertSame(['foo' => 'bar'], json_decode((string) $response->getContent(), true)['session']);
    }

    public function testSessionValuesWithAnotherHost(): void
    {
        $response = $this->request($this->build('GET', 'http://example.com/dump')->sessionValue('foo', 'bar'));

        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame('example.com', $data['host']);
        $this->assertSame(['foo' => 'bar'], $data['session']);
    }

    public function testCsrfTokenIsAcceptedByTheTokenManager(): void
    {
        $response = $this->request($this->build('POST', '/csrf')->csrfToken('form'));

        $this->assertSame(['valid' => true], json_decode((string) $response->getContent(), true));
    }

    public function testCsrfTokenForHttps(): void
    {
        $response = $this->request($this->build('POST', 'https://localhost/csrf')->csrfToken('form'));

        $this->assertSame(['valid' => true], json_decode((string) $response->getContent(), true));
    }

    public function testCsrfTokenForRelativeUriAfterHttpsRequest(): void
    {
        $this->request($this->build('GET', 'https://localhost/dump'));

        $response = $this->request($this->build('POST', '/csrf')->csrfToken('form'));

        $this->assertSame(['valid' => true], json_decode((string) $response->getContent(), true));
    }

    public function testCsrfTokenForHttpsServerParameter(): void
    {
        $response = $this->request($this->build('POST', '/csrf')->server('HTTPS', 'on')->csrfToken('form'));

        $this->assertSame(['valid' => true], json_decode((string) $response->getContent(), true));
    }

    public function testCsrfTokenInNestedField(): void
    {
        $response = $this->request(
            $this->build('POST', '/csrf-nested')
                ->form(['profile' => ['name' => 'peter']])
                ->csrfToken('profile', 'profile[_token]'),
        );

        $this->assertSame(['valid' => true], json_decode((string) $response->getContent(), true));
    }

    public function testFollowRedirectsAppliesToOneRequest(): void
    {
        $this->request($this->build('GET', '/dump')->followRedirects());

        $this->assertFalse(self::getRequestClient()->isFollowingRedirects());
    }

    public function testMissingCsrfTokenIsRejected(): void
    {
        $response = $this->request($this->build('POST', '/csrf'));

        $this->assertSame(['valid' => false], json_decode((string) $response->getContent(), true));
    }

    public function testLogoutRemovesTheSession(): void
    {
        $this->request($this->build('GET', '/dump')->sessionValue('foo', 'bar'));

        $response = $this->request($this->build('GET', '/dump')->logout());

        $this->assertNull(json_decode((string) $response->getContent(), true)['session']);
    }

    public function testCustomClientWithServerDefaults(): void
    {
        $response = $this->request($this->build('GET', '/dump'));

        $this->assertSame('custom.test', json_decode((string) $response->getContent(), true)['host']);
    }

    /** @return AbstractBrowser<covariant object, covariant object> */
    protected static function createRequestClient(): AbstractBrowser
    {
        return static::createClient([], ['HTTP_HOST' => 'custom.test']);
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }
}
