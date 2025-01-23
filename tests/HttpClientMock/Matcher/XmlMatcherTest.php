<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\InvalidMockRequest;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Hit;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Mismatch;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\XmlMatcher;
use Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\RealRequestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(XmlMatcher::class)]
#[CoversClass(Hit::class)]
#[CoversClass(Mismatch::class)]
final class XmlMatcherTest extends TestCase
{
    use MatcherTrait;
    use RealRequestTrait;

    public function testInvalidExpectedXmlThrowsException(): void
    {
        $this->expectException(InvalidMockRequest::class);

        new XmlMatcher('abc');
    }

    public function testMatchXml(): void
    {
        $matcher = new XmlMatcher('<?xml version="1.0"?><root><first>abc</first></root>');

        $realRequest = $this->createRealRequest(content: '<?xml version="1.0"?><root><first>abc</first></root>');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('xml', $result);
    }

    public function testMatchXmlWithCallback(): void
    {
        $matcher = new XmlMatcher(static fn ($xml) => $xml === '<?xml version="1.0"?><root><first>abc</first></root>');

        $realRequest = $this->createRealRequest(content: '<?xml version="1.0"?><root><first>abc</first></root>');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('xml', $result);
    }

    public function testMismatchXml(): void
    {
        $matcher = new XmlMatcher('<?xml version="1.0"?><root><first>abc</first></root>');

        $realRequest = $this->createRealRequest(
            content: '<?xml version="1.0"?><root><first>does-not-match</first></root>',
        );

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('xml', $result);
    }

    public function testMismatchXmlWithCallback(): void
    {
        $matcher = new XmlMatcher(static fn ($xml) => $xml === '<?xml version="1.0"?><root><first>abc</first></root>');

        $realRequest = $this->createRealRequest(
            content: '<?xml version="1.0"?><root><first>does-not-match</first></root>',
        );

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('xml', $result);
    }

    public function testMismatchInvalidXml(): void
    {
        $matcher = new XmlMatcher('<?xml version="1.0"?><root><first>abc</first></root>');

        $realRequest = $this->createRealRequest(
            content: 'does-not-match',
        );

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('xml', $result);
    }

    public function testMismatchInvalidXmlWithCallback(): void
    {
        $matcher = new XmlMatcher(static fn ($xml) => $xml === '<?xml version="1.0"?><root><first>abc</first></root>');

        $realRequest = $this->createRealRequest(
            content: 'does-not-match',
        );

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('xml', $result);
    }

    public function testToString(): void
    {
        $matcher = new XmlMatcher('<?xml version="1.0"?><root><first>abc</first></root>');

        self::assertSame(
            'request.content === "<?xml version="1.0"?><root><first>abc</first></root>"',
            (string) $matcher,
        );
    }

    public function testToStringWithCallback(): void
    {
        $matcher = new XmlMatcher(static fn ($xml) => $xml === '<?xml version="1.0"?><root><first>abc</first></root>');

        self::assertSame('callback(request.content) !== false', (string) $matcher);
    }
}
