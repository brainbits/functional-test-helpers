<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Snapshot;

use Brainbits\FunctionalTestHelpers\Snapshot\SnapshotTrait;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamContent;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

use function getenv;
use function Safe\file_put_contents;
use function Safe\putenv;

final class JsonSnapshotTest extends TestCase
{
    use SnapshotTrait;

    private vfsStreamContent $snapshotsDir;

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
        } catch (ExpectationFailedException) {
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
        } catch (ExpectationFailedException) {
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
        } catch (ExpectationFailedException) {
            return;
        }

        $this->fail('Assertion did not fail');
    }

    public function testSnapshotsAreNotCreatedForCreateSnapshotFalse(): void
    {
        $data = '<foo><bar>1</bar><baz>test</baz></foo>';

        $prior = getenv('CREATE_SNAPSHOTS');
        putenv('CREATE_SNAPSHOTS=false');

        $thrownException = null;

        try {
            $this->assertMatchesXmlSnapshot($data);
        } catch (AssertionFailedError $e) {
            $thrownException = $e;
        } finally {
            putenv('CREATE_SNAPSHOTS=' . $prior);
        }

        $this->assertInstanceOf(AssertionFailedError::class, $thrownException, 'Snapshot test did not fail.');
        $this->assertSame(
            'Snapshot json_snapshot_snapshots_are_not_created_for_create_snapshot_false.xml does not exist.',
            $thrownException->getMessage(),
        );
    }

    public function testSnapshotsAreNotCreatedForCreateSnapshotZero(): void
    {
        $data = '<foo><bar>1</bar><baz>test</baz></foo>';

        $prior = getenv('CREATE_SNAPSHOTS');
        putenv('CREATE_SNAPSHOTS=0');

        $thrownException = null;

        try {
            $this->assertMatchesXmlSnapshot($data);
        } catch (AssertionFailedError $e) {
            $thrownException = $e;
        } finally {
            putenv('CREATE_SNAPSHOTS=' . $prior);
        }

        $this->assertInstanceOf(AssertionFailedError::class, $thrownException, 'Snapshot test did not fail.');
        $this->assertSame(
            'Snapshot json_snapshot_snapshots_are_not_created_for_create_snapshot_zero.xml does not exist.',
            $thrownException->getMessage(),
        );
    }

    public function testCreateSnapshotEnvIsNotProcessedForOtherValue(): void
    {
        $data = '<foo><bar>1</bar><baz>test</baz></foo>';

        $prior = getenv('CREATE_SNAPSHOTS');
        putenv('CREATE_SNAPSHOTS=true');

        try {
            $this->assertMatchesXmlSnapshot($data);
        } catch (AssertionFailedError) {
            $this->fail('Unexpected fail() was called');
        } finally {
            putenv('CREATE_SNAPSHOTS=' . $prior);
        }
    }

    /**
     * Overwrite function in using class to locate __snapshot__ directory correctly.
     */
    private function snapshotPath(): string
    {
        return vfsStream::url('root');
    }
}
