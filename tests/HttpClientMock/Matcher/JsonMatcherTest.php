<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Hit;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\JsonMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Mismatch;
use Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\RealRequestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function Safe\json_encode;

#[CoversClass(JsonMatcher::class)]
#[CoversClass(Hit::class)]
#[CoversClass(Mismatch::class)]
final class JsonMatcherTest extends TestCase
{
    use MatcherTrait;
    use RealRequestTrait;

    public function testMatchJson(): void
    {
        $matcher = new JsonMatcher(['firstname' => 'peter', 'lastname' => 'peterson']);

        $realRequest = $this->createRealRequest(json: ['firstname' => 'peter', 'lastname' => 'peterson']);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('json', $result);
    }

    public function testMatchJsonWithCallback(): void
    {
        $matcher = new JsonMatcher(
            static fn ($content) => json_encode($content) === '{"firstname":"peter","lastname":"peterson"}',
        );

        $realRequest = $this->createRealRequest(json: ['firstname' => 'peter', 'lastname' => 'peterson']);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('json', $result);
    }

    public function testMismatchJson(): void
    {
        $matcher = new JsonMatcher(['firstname' => 'peter', 'lastname' => 'peterson']);

        $realRequest = $this->createRealRequest(json: ['firstname' => 'peter', 'lastname' => 'does-not-match']);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('json', $result);
        self::assertSame('--- Expected
+++ Actual
@@ @@
 {
     "firstname": "peter",
-    "lastname": "peterson"
+    "lastname": "does-not-match"
 }
', $result->diff);
    }

    public function testMismatchJsonWithCallback(): void
    {
        $matcher = new JsonMatcher(
            static fn ($content) => json_encode($content) === '{"firstname":"peter","lastname":"peterson"}',
        );

        $realRequest = $this->createRealRequest(json: ['firstname' => 'peter', 'lastname' => 'does-not-match']);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('json', $result);
        self::assertNull($result->diff);
    }

    public function testMismatchJsonWithoutDiff(): void
    {
        $matcher = new JsonMatcher(['firstname' => 'peter', 'lastname' => 'peterson']);

        $realRequest = $this->createRealRequest(json: null);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('json', $result);
        self::assertNull($result->diff);
    }

    public function testToString(): void
    {
        $matcher = new JsonMatcher(['firstname' => 'peter', 'lastname' => 'peterson']);

        self::assertSame('request.content === "{"firstname":"peter","lastname":"peterson"}"', (string) $matcher);
    }

    public function testToStringWithCallback(): void
    {
        $matcher = new JsonMatcher(static fn ($content) => $content === 'this is text');

        self::assertSame('callback(request.content) !== false', (string) $matcher);
    }
}
