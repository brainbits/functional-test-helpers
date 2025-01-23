<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Hit;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Mismatch;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Missing;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\MultipartMatcher;
use Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\RealRequestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MultipartMatcher::class)]
#[CoversClass(Hit::class)]
#[CoversClass(Mismatch::class)]
#[CoversClass(Missing::class)]
final class MultipartMatcherTest extends TestCase
{
    use MatcherTrait;
    use RealRequestTrait;

    public function testMatchesMultipart(): void
    {
        $matcher = new MultipartMatcher('file', 'application/pdf', 'file.pdf', 'pdf');

        $realRequest = $this->createRealRequest(multiparts: [
            'file' => [
                'name' => 'file',
                'mimetype' => 'application/pdf',
                'filename' => 'file.pdf',
                'content' => 'pdf',
            ],
        ]);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('multipart', $result);
    }

    public function testMissingMultipart(): void
    {
        $matcher = new MultipartMatcher('file', 'application/pdf', 'file.pdf', 'pdf');

        $realRequest = $this->createRealRequest(multiparts: [
            'does-not-match' => [
                'name' => 'does-not-match',
                'mimetype' => 'application/pdf',
                'filename' => 'file.pdf',
                'content' => 'pdf',
            ],
        ]);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Missing::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('multipart', $result);
    }

    public function testMismatchingMultipart(): void
    {
        $matcher = new MultipartMatcher('file', 'application/pdf', 'file.pdf', 'pdf');

        $realRequest = $this->createRealRequest(multiparts: [
            'file' => [
                'name' => 'file',
                'mimetype' => 'does-not-match',
                'filename' => 'file.pdf',
                'content' => 'pdf',
            ],
        ]);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('multipart', $result);
    }

    public function testMatchesWithoutMimetype(): void
    {
        $matcher = new MultipartMatcher('file', null, 'file.pdf', 'pdf');

        $realRequest = $this->createRealRequest(multiparts: [
            'file' => [
                'name' => 'file',
                'mimetype' => 'application/pdf',
                'filename' => 'file.pdf',
                'content' => 'pdf',
            ],
        ]);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('multipart', $result);
    }

    public function testMatchesWithoutFilename(): void
    {
        $matcher = new MultipartMatcher('file', 'application/pdf', null, 'pdf');

        $realRequest = $this->createRealRequest(multiparts: [
            'file' => [
                'name' => 'file',
                'mimetype' => 'application/pdf',
                'filename' => 'file.pdf',
                'content' => 'pdf',
            ],
        ]);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('multipart', $result);
    }

    public function testMatchesWithoutContent(): void
    {
        $matcher = new MultipartMatcher('file', 'application/pdf', 'file.pdf', null);

        $realRequest = $this->createRealRequest(multiparts: [
            'file' => [
                'name' => 'file',
                'mimetype' => 'application/pdf',
                'filename' => 'file.pdf',
                'content' => 'pdf',
            ],
        ]);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('multipart', $result);
    }

    public function testMatchesWithOnlyName(): void
    {
        $matcher = new MultipartMatcher('file', null, null, null);

        $realRequest = $this->createRealRequest(multiparts: [
            'file' => [
                'name' => 'file',
                'mimetype' => 'application/pdf',
                'filename' => 'file.pdf',
                'content' => 'pdf',
            ],
        ]);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('multipart', $result);
    }

    public function testDoesNotMatchWithIncompleteMultiparts(): void
    {
        $matcher = new MultipartMatcher('file', null, null, 'pdf');

        $realRequest = $this->createRealRequest(multiparts: [
            'file' => [
                'name' => 'file',
                'mimetype' => 'application/does-not-match',
                'filename' => 'does-not-match.pdf',
                'content' => 'does-not-match',
            ],
        ]);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('multipart', $result);
    }

    public function testToString(): void
    {
        $matcher = new MultipartMatcher('file', 'application/pdf', 'file.pdf', 'pdf');

        self::assertSame(
            '[filename=file.pdf, mimetype=application/pdf, content=pdf] === request.request[file]',
            (string) $matcher,
        );
    }
}
