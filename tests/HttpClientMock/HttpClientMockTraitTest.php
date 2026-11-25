<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\NoResponseMock;
use Brainbits\FunctionalTestHelpers\HttpClientMock\HttpClientMockTrait;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\CatchAllMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\MethodMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\QueryParamMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\UriMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\UriParams;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilderCollection;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestMatcher;
use Monolog\Logger;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function array_map;

final class HttpClientMockTraitTest extends TestCase
{
    use HttpClientMockTrait;

    private static MockRequestBuilderCollection|null $collection = null;
    private static EventDispatcherInterface|null $dispatcher = null;
    private static Logger|null $logger = null;

    #[Before(100)]
    public function createContainerServices(): void
    {
        self::$collection = new MockRequestBuilderCollection();
        self::$dispatcher = $this->createStub(EventDispatcherInterface::class);
        self::$logger = $this->createStub(Logger::class);
    }

    public static function getContainer(): Container
    {
        $container = new Container();
        $container->set(MockRequestBuilderCollection::class, self::$collection);
        $container->set('event_dispatcher', self::$dispatcher);
        $container->set('monolog.logger', self::$logger);

        return $container;
    }

    public function testMockRequest(): void
    {
        $this->mockRequest('GET', '/query')
            ->name('test')
            ->queryParam('firstname', 'peter')
            ->queryParam('lastname', '%s', 'peterson')
            ->queryParam('email', '')
            ->header('content-type', 'text/plain')
            ->content('this is text')
            ->willRespond(
                $this->mockResponse()
                    ->header('content-type', 'text/plain')
                    ->content('test'),
            );

        // simulate http request
        (self::$collection)(
            'GET',
            '/query?firstname=peter&lastname=peterson&email=',
            [
                'headers' => ['Content-Type: text/plain'],
                'body' => 'this is text',
            ],
        );

        $this->assertAllRequestMocksAreCalled();
    }

    public function testMockRequestParsesLegacyQueryParamsFromUri(): void
    {
        $builder = $this->mockRequest('GET', '/query?&firstname=peter&lastname={lastname}&email=');

        $this->assertEquals(
            $builder->getMatcher(),
            new MockRequestMatcher(null, [
                new MethodMatcher('GET'),
                new QueryParamMatcher('firstname', 'peter', []),
                new QueryParamMatcher('lastname', '{lastname}', []),
                new QueryParamMatcher('email', '', []),
                new UriMatcher('/query', new UriParams()),
            ]),
        );
    }

    public function testMockRequestParsesLegacyQueryParamsFromUriWithHost(): void
    {
        $builder = $this->mockRequest('GET', 'https://example.com/query?&firstname=peter&lastname={lastname}&email=');

        $this->assertEquals(
            $builder->getMatcher(),
            new MockRequestMatcher(null, [
                new MethodMatcher('GET'),
                new QueryParamMatcher('firstname', 'peter', []),
                new QueryParamMatcher('lastname', '{lastname}', []),
                new QueryParamMatcher('email', '', []),
                new UriMatcher('https://example.com/query', new UriParams()),
            ]),
        );
    }

    public function testMockRequestKeepsThePortOfLegacyUris(): void
    {
        $builder = $this->mockRequest('GET', 'http://localhost:8080/path?x=1');

        $this->assertEquals(
            new MockRequestMatcher(null, [
                new MethodMatcher('GET'),
                new QueryParamMatcher('x', '1', []),
                new UriMatcher('http://localhost:8080/path', new UriParams()),
            ]),
            $builder->getMatcher(),
        );

        $this->createdMockRequestBuilders = [];
    }

    public function testMockRequestWithoutMethodAndUri(): void
    {
        $builder = $this->mockRequest();

        $this->assertEquals(new MockRequestMatcher(null, [new CatchAllMatcher()]), $builder->getMatcher());

        $this->createdMockRequestBuilders = [];
    }

    public function testHeaderContainingAssertionFailsForMissingHeader(): void
    {
        $builder = $this->mockRequest('GET', '/query')
            ->willRespond($this->mockResponse());

        (self::$collection)('GET', '/query', []);

        try {
            self::assertRequestMockIsCalledWithHeaderContaining('X-Missing', 'foo', $builder);
        } catch (ExpectationFailedException) {
            return;
        } finally {
            $this->createdMockRequestBuilders = [];
        }

        $this->fail('Assertion did not fail');
    }

    public function testHeaderNotContainingAssertionPassesForMissingHeader(): void
    {
        $builder = $this->mockRequest('GET', '/query')
            ->willRespond($this->mockResponse());

        (self::$collection)('GET', '/query', []);

        self::assertRequestMockIsCalledWithHeaderNotContaining('X-Missing', 'foo', $builder);
    }

    public function testAllFailedAssertionCallbacksAreReported(): void
    {
        $this->mockRequest('GET', '/query')
            ->assertMethod(static fn () => self::fail('first failure'))
            ->assertUri(static fn () => self::fail('second failure'))
            ->willRespond($this->mockResponse());

        (self::$collection)('GET', '/query', []);

        try {
            $this->assertNoFailedMockRequests();
        } catch (AssertionFailedError $e) {
            $this->assertStringContainsString('2 mock request assertions failed', $e->getMessage());
            $this->assertStringContainsString('first failure', $e->getMessage());
            $this->assertStringContainsString('second failure', $e->getMessage());

            return;
        }

        $this->fail('Failed assertions were not reported');
    }

    public function testCallCountExpectationsAreChecked(): void
    {
        $this->mockRequest('GET', '/once')->name('once')->once()->willAlwaysRespond($this->mockResponse());
        $this->mockRequest('GET', '/never')->name('never')->never()->willAlwaysRespond($this->mockResponse());
        $this->mockRequest('GET', '/twice')->name('twice')->times(2)->willAlwaysRespond($this->mockResponse());

        (self::$collection)('GET', '/once', []);
        (self::$collection)('GET', '/twice', []);

        try {
            (self::$collection)('GET', '/never', []);
        } catch (NoResponseMock) {
            // an extra call fails right away, and the check after the test reports it as well
        }

        try {
            $this->assertNoFailedMockRequests();
        } catch (AssertionFailedError $e) {
            $this->assertStringNotContainsString('"once"', $e->getMessage());
            $this->assertStringContainsString(
                'Mock request "never" was expected to be called 0 times, but was called 1 times.',
                $e->getMessage(),
            );
            $this->assertStringContainsString(
                'Mock request "twice" was expected to be called 2 times, but was called 1 times.',
                $e->getMessage(),
            );

            return;
        }

        $this->fail('Call count expectations were not checked');
    }

    public function testMatchingCallCountExpectationsPass(): void
    {
        $this->mockRequest('GET', '/once')->once()->willRespond($this->mockResponse());
        $this->mockRequest('GET', '/never')->never();

        (self::$collection)('GET', '/once', []);

        $this->assertCount(1, $this->chronologicalCallStack());
        $this->assertNoFailedMockRequests();
    }

    public function testNeverIsNotBypassedByLessSpecificMock(): void
    {
        $this->mockRequest('DELETE', '/orders/1')->name('never')->never();
        $this->mockRequest('DELETE')->willAlwaysRespond($this->mockResponse()->code(204));

        try {
            (self::$collection)('DELETE', '/orders/1', []);
            $this->fail('The request was answered by the less specific mock');
        } catch (NoResponseMock $e) {
            $this->assertSame(
                'Mock request "never" was expected to be called 0 times, but was called 1 times.',
                $e->getMessage(),
            );
        }

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Mock request "never" was expected to be called 0 times');

        $this->assertNoFailedMockRequests();
    }

    public function testOnceIsNotBypassedByLessSpecificMock(): void
    {
        $this->mockRequest('POST', '/orders')->name('once')->once()->willRespond($this->mockResponse());
        $this->mockRequest('POST')->willAlwaysRespond($this->mockResponse());

        (self::$collection)('POST', '/orders', []);

        try {
            (self::$collection)('POST', '/orders', []);
            $this->fail('The request was answered by the less specific mock');
        } catch (NoResponseMock $e) {
            $this->assertSame(
                'Mock request "once" was expected to be called 1 times, but was called 2 times.',
                $e->getMessage(),
            );
        }

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Mock request "once" was expected to be called 1 times, but was called 2 times.');

        $this->assertNoFailedMockRequests();
    }

    public function testNeverIsNotBypassedByEquallySpecificMock(): void
    {
        // e.g. a default mock from setUp()
        $this->mockRequest('GET', '/status')->willAlwaysRespond($this->mockResponse());
        $this->mockRequest('GET', '/status')->name('never')->never();

        $this->expectException(NoResponseMock::class);
        $this->expectExceptionMessage('Mock request "never" was expected to be called 0 times');

        try {
            (self::$collection)('GET', '/status', []);
        } finally {
            $this->createdMockRequestBuilders = [];
        }
    }

    public function testNeverIsNotBypassedByMockWithOtherMatchersOfTheSameScore(): void
    {
        $this->mockRequest('GET')->header('Accept', 'application/json')->willAlwaysRespond($this->mockResponse());
        $this->mockRequest('GET')->name('never')->queryParam('a', '1')->never();

        $this->expectException(NoResponseMock::class);
        $this->expectExceptionMessage('Mock request "never" was expected to be called 0 times');

        try {
            (self::$collection)('GET', '/x?a=1', ['headers' => ['Accept: application/json']]);
        } finally {
            $this->createdMockRequestBuilders = [];
        }
    }

    public function testExtraCallOfOnceWithAlwaysRespondFailsRightAway(): void
    {
        $this->mockRequest('GET', '/status')->name('once')->once()->willAlwaysRespond($this->mockResponse());

        (self::$collection)('GET', '/status', []);

        $this->expectException(NoResponseMock::class);
        $this->expectExceptionMessage('Mock request "once" was expected to be called 1 times, but was called 2 times.');

        try {
            (self::$collection)('GET', '/status', []);
        } finally {
            $this->createdMockRequestBuilders = [];
        }
    }

    public function testEquallySpecificMocksWithCallCountsAreUsedInTurn(): void
    {
        $this->mockRequest('POST', '/orders')->name('first')->once()->willRespond($this->mockResponse()->code(201));
        $this->mockRequest('POST', '/orders')->name('second')->once()->willRespond($this->mockResponse()->code(202));

        $this->assertSame(201, (self::$collection)('POST', '/orders', [])->getStatusCode());
        $this->assertSame(202, (self::$collection)('POST', '/orders', [])->getStatusCode());

        $this->assertNoFailedMockRequests();
    }

    public function testEquallySpecificMocksWithCallCountsAndAlwaysRespondAreUsedInTurn(): void
    {
        $this->mockRequest('GET', '/status')->once()->willAlwaysRespond($this->mockResponse()->code(201));
        $this->mockRequest('GET', '/status')->once()->willAlwaysRespond($this->mockResponse()->code(202));

        $this->assertSame(201, (self::$collection)('GET', '/status', [])->getStatusCode());
        $this->assertSame(202, (self::$collection)('GET', '/status', [])->getStatusCode());

        $this->assertNoFailedMockRequests();
    }

    public function testAssertAllMockResponsesAreConsumed(): void
    {
        $this->mockRequest('GET', '/sequence')
            ->name('sequence')
            ->willRespond($this->mockResponse())
            ->willRespond($this->mockResponse());
        $this->mockRequest('GET', '/always')->willAlwaysRespond($this->mockResponse());

        (self::$collection)('GET', '/sequence', []);

        try {
            $this->assertAllMockResponsesAreConsumed();
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString(
                'Mock request "sequence" has responses that were never returned.',
                $e->getMessage(),
            );
            $this->createdMockRequestBuilders = [];

            return;
        }

        $this->fail('Unused responses were not detected');
    }

    public function testCollectiveAssertionsSurviveAKernelReboot(): void
    {
        $login = $this->mockRequest('POST', '/login')->name('login')->willRespond($this->mockResponse());
        $order = $this->mockRequest('POST', '/order')
            ->name('order')
            ->willRespond($this->mockResponse())
            ->willRespond($this->mockResponse());

        (self::$collection)('POST', '/login', []);
        (self::$collection)('POST', '/order', []);

        // a kernel reboot replaces the container and with it the mock request builder collection
        self::$collection = new MockRequestBuilderCollection();

        $uris = array_map(static fn ($call) => $call->getUri(), [...$this->chronologicalCallStack()]);
        $this->assertSame(['/login', '/order'], $uris);

        $this->assertRequestMocksAreCalledInOrder($login, $order);

        try {
            $this->assertAllMockResponsesAreConsumed();
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString(
                'Mock request "order" has responses that were never returned.',
                $e->getMessage(),
            );
            $this->createdMockRequestBuilders = [];

            return;
        }

        $this->fail('Unused responses were not detected after a reboot');
    }

    public function testChronologicalCallStackAndCallOrder(): void
    {
        $a = $this->mockRequest('GET', '/a')->name('a')->willAlwaysRespond($this->mockResponse());
        $b = $this->mockRequest('GET', '/b')->name('b')->willAlwaysRespond($this->mockResponse());

        (self::$collection)('GET', '/b', []);
        (self::$collection)('GET', '/a', []);
        (self::$collection)('GET', '/b', []);

        $uris = array_map(static fn ($call) => $call->getUri(), [...$this->chronologicalCallStack()]);
        $this->assertSame(['/b', '/a', '/b'], $uris);

        $this->assertRequestMocksAreCalledInOrder($b, $a);

        try {
            $this->assertRequestMocksAreCalledInOrder($a, $b);
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString('Mock request "b" was called before "a".', $e->getMessage());

            return;
        }

        $this->fail('Wrong call order was not detected');
    }
}
