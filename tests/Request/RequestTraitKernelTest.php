<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Request;

use Brainbits\FunctionalTestHelpers\Request\RequestTrait;
use Brainbits\FunctionalTestHelpers\Tests\Request\Kernel\TestKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function Safe\json_decode;

#[CoversNothing]
final class RequestTraitKernelTest extends WebTestCase
{
    use RequestTrait;

    public function testSessionValuesWithLocalhost(): void
    {
        $response = $this->request($this->build('GET', '/dump')->sessionValue('foo', 'bar'));

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

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }
}
