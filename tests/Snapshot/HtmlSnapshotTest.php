<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Snapshot;

use Brainbits\FunctionalTestHelpers\Snapshot\SnapshotTrait;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

use function Safe\file_put_contents;

/**
 * @covers \Brainbits\FunctionalTestHelpers\Snapshot\SnapshotTrait
 */
final class HtmlSnapshotTest extends TestCase
{
    use SnapshotTrait;

    private vfsStreamDirectory $snapshotsDir;

    public function setUp(): void
    {
        $root = vfsStream::setup('root', null, ['__snapshots__' => []]);

        $this->snapshotsDir = $root->getChild('__snapshots__');
    }

    public function testHtml(): void
    {
        $data = '<html><meta><title>foo</title></meta><body>bar</body></html>';

        $this->assertMatchesHtmlSnapshot($data);
        $this->assertFileExists($this->snapshotsDir->url() . '/html_snapshot_html.html');
    }

    public function testHtmlAssertionFails(): void
    {
        $data = '<html><meta><title>foo</title></meta><body>bar</body></html>';

        file_put_contents(
            $this->snapshotsDir->url() . '/html_snapshot_html_assertion_fails.html',
            '<html/>',
        );

        try {
            $this->assertMatchesHtmlSnapshot($data);
        } catch (ExpectationFailedException $e) {
            return;
        }

        $this->fail('Assertion did not fail');
    }

    public function testNamedHtml(): void
    {
        $data = '<html><meta><title>foo</title></meta><body>bar</body></html>';

        $this->assertMatchesNamedHtmlSnapshot($data, 'postfix');
        $this->assertFileExists($this->snapshotsDir->url() . '/html_snapshot_named_html_postfix.html');
    }

    public function testNamedHtmlAssertionFails(): void
    {
        $data = '<html><meta><title>foo</title></meta><body>bar</body></html>';

        file_put_contents(
            $this->snapshotsDir->url() . '/html_snapshot_named_html_assertion_fails.html',
            '<html/>',
        );

        try {
            $this->assertMatchesHtmlSnapshot($data);
        } catch (ExpectationFailedException $e) {
            return;
        }

        $this->fail('Assertion did not fail');
    }

    /**
     * Overwrite function in using class to locate __snapshot__ directory correctly.
     */
    private function snapshotPath(): string
    {
        return vfsStream::url('root');
    }
}
