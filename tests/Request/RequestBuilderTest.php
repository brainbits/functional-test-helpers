<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Request;

use Brainbits\FunctionalTestHelpers\Request\InvalidRequest;
use Brainbits\FunctionalTestHelpers\Request\RequestBuilder;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\User\InMemoryUser;

use function current;
use function method_exists;
use function Safe\json_encode;

/** @covers \Brainbits\FunctionalTestHelpers\Request\RequestBuilder */
final class RequestBuilderTest extends TestCase
{
    use ProphecyTrait;

    public function testItCanBeCreated(): void
    {
        $builder = RequestBuilder::create(
            static fn (...$params) => $params,
            static fn (...$params) => $params,
            'POST',
            '/test',
        );

        $this->assertInstanceOf(RequestBuilder::class, $builder);
    }

    public function testMethodIsReturned(): void
    {
        $builder = $this->createRequestBuilder('POST');

        $this->assertSame('POST', $builder->getMethod());
    }

    public function testUriIsReturned(): void
    {
        $builder = $this->createRequestBuilder('POST', 'http://127.0.0.1/test/script.php');

        $this->assertSame('http://127.0.0.1/test/script.php', $builder->getUri());
    }

    public function testUriIsSuffixed(): void
    {
        $builder = $this->createRequestBuilder('GET', '/foo')
            ->uriAppend('/bar')
            ->uriAppend('/baz');

        $this->assertSame('/foo/bar/baz', $builder->getUri());
    }

    public function testUriParamIsReplacedInUri(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users/{id}/expand/{expand}')
            ->uriParam('id', 123)
            ->uriParam('expand', 'groups');

        $this->assertSame('/users/123/expand/groups', $builder->getUri());
    }

    public function testUriParamIsEncodedInUri(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users/{expand}')
            ->uriParam('expand', 'with space');

        $this->assertSame('/users/with%20space', $builder->getUri());
    }

    public function testInvalidUriParamThrowsException(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage('foo');

        $this->createRequestBuilder('GET', '/users/{id}/expand/{expand}')
            ->uriParam('foo', 123);
    }

    public function testQueryParamsAreAddedToUri(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users')
            ->queryParam('limit', 100)
            ->queryParam('offset', 10);

        $this->assertSame('/users?limit=100&offset=10', $builder->getUri());
    }

    public function testQueryParamsAreEncodedInUri(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users')
            ->queryParam('with space', 'with space');

        $this->assertSame('/users?with+space=with+space', $builder->getUri());
    }

    public function testParameterValuesAreReturned(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users');

        $this->assertSame([], $builder->getParameters());
    }

    public function testParametersCanBeSet(): void
    {
        $builder = $this->createRequestBuilder('POST', '/users')
            ->parameter('username', 'foo')
            ->parameter('email', 'foo@example.com');

        $this->assertSame(['username' => 'foo', 'email' => 'foo@example.com'], $builder->getParameters());
    }

    public function testServerValuesAreReturned(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users');

        $this->assertSame([], $builder->getServer());
    }

    public function testXmlContentTypeIsSet(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users')
            ->xml();

        $this->assertSame(['CONTENT_TYPE' => 'text/xml'], $builder->getServer());
    }

    public function testJsonContentTypeIsSet(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users')
            ->json();

        $this->assertSame(['CONTENT_TYPE' => 'application/json'], $builder->getServer());
    }

    /** @dataProvider provideJsonData */
    public function testJsonDataIsSet(mixed $data, string|null $encoded): void
    {
        $builder = $this->createRequestBuilder('GET', '/users')
            ->json($data);

        $this->assertSame($encoded, $builder->getContent());
    }

    /** @return array<string, array{mixed, string|null}> */
    public function provideJsonData(): array
    {
        return [
            'null' => [null, null], // data is skipped
            'int' => [1, '1'],
            'float' => [1.1, '1.1'],
            'string' => ['text', '"text"'],
            'nested' => [['string' => 'text', 'int' => 1, 'float' => 1.1], '{"string":"text","int":1,"float":1.1}'],
        ];
    }

    public function testAcceptAllIsSet(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users')
            ->acceptAll();

        $this->assertSame(['HTTP_ACCEPT' => '*/*'], $builder->getServer());
    }

    public function testAcceptImagesIsSet(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users')
            ->acceptImages();

        $this->assertSame(['HTTP_ACCEPT' => 'image/*'], $builder->getServer());
    }

    public function testAcceptXmlIsSet(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users')
            ->acceptXml();

        $this->assertSame(['HTTP_ACCEPT' => 'text/xml'], $builder->getServer());
    }

    public function testAcceptJsonIsSet(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users')
            ->acceptJson();

        $this->assertSame(['HTTP_ACCEPT' => 'application/json'], $builder->getServer());
    }

    public function testAuthorizationHeaderIsSetOnAuthBearerCall(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users')
            ->authBearer('foo');

        $this->assertSame(['HTTP_AUTHORIZATION' => 'Bearer foo'], $builder->getServer());
    }

    public function testAuthorizationHeaderIsSetOnAuthTokenCall(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users')
            ->authToken('foo');

        $this->assertSame(['HTTP_AUTHORIZATION' => 'Bearer ["foo"]'], $builder->getServer());
    }

    public function testAuthorizationHeaderWithAdditionalParametersIsSetOnAuthTokenCall(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users')
            ->authToken('foo', ['bar', 'baz']);

        $this->assertSame(['HTTP_AUTHORIZATION' => 'Bearer ["foo",["bar","baz"]]'], $builder->getServer());
    }

    public function testAuthenticationHeadersAreSetOnAuthBasicCall(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users')
            ->authBasic('user', 'password');

        $this->assertSame(['PHP_AUTH_USER' => 'user', 'PHP_AUTH_PW' => 'password'], $builder->getServer());
    }

    public function testAuthenticationHeadersAreSetOnAuthCall(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users')
            ->auth('foo');

        $this->assertSame(['HTTP_AUTHORIZATION' => 'foo'], $builder->getServer());
    }

    public function testAuthorizationHeaderIsSetOnAuthLoginCall(): void
    {
        $user = new InMemoryUser('foo', 'bar');

        if (!method_exists(KernelBrowser::class, 'loginUser')) {
            $this->markTestSkipped('authLogin() only available for symfony/framework-bundle >= 5.1');
        }

        $browser = $this->prophesize(KernelBrowser::class);
        $browser->loginUser(Argument::any())
            ->willReturn($browser->reveal());

        $this->createRequestBuilder('GET', '/users')
            ->authLogin($user, $browser->reveal());

        $browser->loginUser(Argument::any())
            ->shouldHaveBeenCalled();
    }

    public function testFileValuesAreReturned(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users');

        $this->assertSame([], $builder->getFiles());
    }

    public function testSingleFileCanBeSet(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users')
            ->file('single', new UploadedFile(__FILE__, 'test.jpg'));

        $this->assertArrayHasKey('single', $builder->getFiles());
        $this->assertContainsOnlyInstancesOf(UploadedFile::class, $builder->getFiles());
    }

    public function testMultipleFilesCanBeSet(): void
    {
        $files = [
            new UploadedFile(__FILE__, 'test1.jpg'),
            new UploadedFile(__FILE__, 'test2.jpg'),
        ];
        $builder = $this->createRequestBuilder('GET', '/users')
            ->file('multiple', $files);

        $this->assertArrayHasKey('multiple', $builder->getFiles());
        $this->assertContainsOnlyInstancesOf(UploadedFile::class, $builder->getFiles()['multiple']);
    }

    public function testMultipleNestedFilesCanBeSet(): void
    {
        $files = [
            'foo' => [
                'bar' => [
                    'baz' => [
                        new UploadedFile(__FILE__, 'test1.jpg'),
                        new UploadedFile(__FILE__, 'test2.jpg'),
                    ],
                ],
            ],
        ];
        $builder = $this->createRequestBuilder('GET', '/users')
            ->file('nested', $files);

        $this->assertArrayHasKey('nested', $builder->getFiles());
        $this->assertArrayHasKey('foo', $builder->getFiles()['nested']);
        $this->assertArrayHasKey('bar', $builder->getFiles()['nested']['foo']);
        $this->assertArrayHasKey('baz', $builder->getFiles()['nested']['foo']['bar']);
        $this->assertContainsOnlyInstancesOf(UploadedFile::class, $builder->getFiles()['nested']['foo']['bar']['baz']);
    }

    public function testInvalidFilesThrowsException(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage(
            'Parameter 2 of file() needs to be either an array of UploadedFiles, or a single UploadedFile',
        );

        $files = ['foo' => 'bar'];
        $this->createRequestBuilder('GET', '/users')
            ->file('invalid', $files);
    }

    public function testFilesCanBeSetByPath(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users')
            ->fileByPath('foo', __FILE__);

        $this->assertArrayHasKey('foo', $builder->getFiles());
        $this->assertContainsOnlyInstancesOf(UploadedFile::class, $builder->getFiles());
    }

    public function testContentIsReturned(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users');

        $this->assertNull($builder->getContent());
    }

    public function testChangeHistoryIsReturned(): void
    {
        $builder = $this->createRequestBuilder('GET', '/users');

        $this->assertTrue($builder->getChangeHistory());
    }

    private function createRequestBuilder(string $method = 'GET', string $uri = '/foo'): RequestBuilder
    {
        return RequestBuilder::create(
            static fn (...$params) => current($params),
            static fn (...$params) => json_encode($params),
            $method,
            $uri,
        );
    }
}
