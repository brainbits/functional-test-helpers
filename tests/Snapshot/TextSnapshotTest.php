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
final class TextSnapshotTest extends TestCase
{
    use SnapshotTrait;

    private vfsStreamDirectory $snapshotsDir;

    public function setUp(): void
    {
        $root = vfsStream::setup('root', null, ['__snapshots__' => []]);

        $this->snapshotsDir = $root->getChild('__snapshots__');
    }

    public function testText(): void
    {
        $data = 'Lorem ipsum dolor sit amet';

        $this->assertMatchesTextSnapshot($data);
        $this->assertFileExists($this->snapshotsDir->url() . '/text_snapshot_text.txt');
    }

    public function testTextAssertionFails(): void
    {
        $data = 'Lorem ipsum dolor sit amet';

        file_put_contents(
            $this->snapshotsDir->url() . '/text_snapshot_text_assertion_fails.txt',
            'foo bar',
        );

        try {
            $this->assertMatchesTextSnapshot($data);
        } catch (ExpectationFailedException $e) {
            return;
        }

        $this->fail('Assertion did not fail');
    }

    public function testNamedText(): void
    {
        $data = 'Lorem ipsum dolor sit amet';

        $this->assertMatchesNamedTextSnapshot($data, 'postfix');
        $this->assertFileExists($this->snapshotsDir->url() . '/text_snapshot_named_text_postfix.txt');
    }

    public function testNamedTextAssertionFails(): void
    {
        $data = 'Lorem ipsum dolor sit amet';

        file_put_contents(
            $this->snapshotsDir->url() . '/text_snapshot_named_text_assertion_fails.txt',
            'foo bar',
        );

        try {
            $this->assertMatchesTextSnapshot($data);
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
