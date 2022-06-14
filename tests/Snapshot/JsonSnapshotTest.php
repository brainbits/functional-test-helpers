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
final class JsonSnapshotTest extends TestCase
{
    use SnapshotTrait;

    private vfsStreamDirectory $snapshotsDir;

    public function setUp(): void
    {
        $root = vfsStream::setup('root', null, ['__snapshots__' => []]);

        $this->snapshotsDir = $root->getChild('__snapshots__');
    }

    public function testInvalidJson(): void
    {
        $data = 'test';

        try {
            $this->assertMatchesJsonSnapshot($data);
        } catch (ExpectationFailedException $e) {
            return;
        }

        $this->fail('Assertion did not fail');
    }

    public function testJson(): void
    {
        $data = '{"a":1,"b":[1,2,3],"c":{"foo":"bar","baz":99}}';

        $this->assertMatchesJsonSnapshot($data);
        $this->assertFileExists($this->snapshotsDir->url() . '/json_snapshot_json.json');
    }

    public function testJsonAssertionFails(): void
    {
        $data = '{"a":1,"b":[1,2,3],"c":{"foo":"bar","baz":99}}';

        file_put_contents(
            $this->snapshotsDir->url() . '/json_snapshot_json_assertion_fails.json',
            '{}',
        );

        try {
            $this->assertMatchesJsonSnapshot($data);
        } catch (ExpectationFailedException $e) {
            return;
        }

        $this->fail('Assertion did not fail');
    }

    public function testNamedJson(): void
    {
        $data = '{"a":1,"b":[1,2,3],"c":{"foo":"bar","baz":99}}';

        $this->assertMatchesNamedJsonSnapshot($data, 'postfix');
        $this->assertFileExists($this->snapshotsDir->url() . '/json_snapshot_named_json_postfix.json');
    }

    public function testNamedJsonAssertionFails(): void
    {
        $data = '{"a":1,"b":[1,2,3],"c":{"foo":"bar","baz":99}}';

        file_put_contents(
            $this->snapshotsDir->url() . '/json_snapshot_named_json_assertion_fails.json',
            '{}',
        );

        try {
            $this->assertMatchesJsonSnapshot($data);
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
