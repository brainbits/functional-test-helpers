<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Snapshot;

use Brainbits\FunctionalTestHelpers\Snapshot\SnapshotTrait;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

use function Safe\file_put_contents;
use function Safe\json_decode;

/**
 * @covers \Brainbits\FunctionalTestHelpers\Snapshot\SnapshotTrait
 */
final class ArraySnapshotTest extends TestCase
{
    use SnapshotTrait;

    private vfsStreamDirectory $snapshotsDir;

    public function setUp(): void
    {
        $root = vfsStream::setup('root', null, ['__snapshots__' => []]);

        $this->snapshotsDir = $root->getChild('__snapshots__');
    }

    public function testArray(): void
    {
        $data = json_decode('{"a":1,"b":[1,2,3],"c":{"foo":"bar","baz":99}}', true);

        $this->assertMatchesArraySnapshot($data);
        $this->assertFileExists($this->snapshotsDir->url() . '/array_snapshot_array.json');
    }

    public function testArrayAssertionFails(): void
    {
        $data = json_decode('{"a":1,"b":[1,2,3],"c":{"foo":"bar","baz":99}}', true);

        file_put_contents(
            $this->snapshotsDir->url() . '/array_snapshot_array_assertion_fails.json',
            '{}',
        );

        try {
            $this->assertMatchesArraySnapshot($data);
        } catch (ExpectationFailedException $e) {
            return;
        }

        $this->fail('Assertion did not fail');
    }

    public function testNamedArray(): void
    {
        $data = json_decode('{"a":1,"b":[1,2,3],"c":{"foo":"bar","baz":99}}', true);

        $this->assertMatchesNamedArraySnapshot($data, 'postfix');
        $this->assertFileExists($this->snapshotsDir->url() . '/array_snapshot_named_array_postfix.json');
    }

    public function testNamedArrayAssertionFails(): void
    {
        $data = json_decode('{"a":1,"b":[1,2,3],"c":{"foo":"bar","baz":99}}', true);

        file_put_contents(
            $this->snapshotsDir->url() . '/array_snapshot_named_array_assertion_fails.json',
            '{}',
        );

        try {
            $this->assertMatchesArraySnapshot($data);
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
