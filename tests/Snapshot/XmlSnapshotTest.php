<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Snapshot;

use Brainbits\FunctionalTestHelpers\Snapshot\SnapshotTrait;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

use function getenv;
use function Safe\file_put_contents;
use function Safe\putenv;

/** @covers \Brainbits\FunctionalTestHelpers\Snapshot\SnapshotTrait */
final class XmlSnapshotTest extends TestCase
{
    use SnapshotTrait;

    private vfsStreamDirectory $snapshotsDir;

    public function setUp(): void
    {
        $root = vfsStream::setup('root', null, ['__snapshots__' => []]);

        $this->snapshotsDir = $root->getChild('__snapshots__');
    }

    public function testInvalidXml(): void
    {
        $data = 'test';

        $exception = null;

        try {
            $this->assertMatchesXmlSnapshot($data);
        } catch (ExpectationFailedException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception, 'Assertion did not fail');
    }

    public function testXml(): void
    {
        $data = '<foo><bar>1</bar><baz>test</baz></foo>';

        $this->assertMatchesXmlSnapshot($data);
        $this->assertFileExists($this->snapshotsDir->url() . '/xml_snapshot_xml.xml');
    }

    public function testXmlAssertionFails(): void
    {
        $data = '<foo><bar>1</bar><baz>test</baz></foo>';

        file_put_contents(
            $this->snapshotsDir->url() . '/xml_snapshot_xml_assertion_fails.xml',
            '<root/>',
        );

        try {
            $this->assertMatchesXmlSnapshot($data);
        } catch (ExpectationFailedException) {
            return;
        }

        $this->fail('Assertion did not fail');
    }

    public function testNamedXml(): void
    {
        $data = '<foo><bar>1</bar><baz>test</baz></foo>';

        $this->assertMatchesNamedXmlSnapshot($data, 'postfix');
        $this->assertFileExists($this->snapshotsDir->url() . '/xml_snapshot_named_xml_postfix.xml');
    }

    public function testNamedXmlAssertionFails(): void
    {
        $data = '<foo><bar>1</bar><baz>test</baz></foo>';

        file_put_contents(
            $this->snapshotsDir->url() . '/xml_snapshot_named_xml_assertion_fails.xml',
            '<root/>',
        );

        try {
            $this->assertMatchesXmlSnapshot($data);
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
            'Snapshot xml_snapshot_snapshots_are_not_created_for_create_snapshot_false.xml does not exist.',
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
            'Snapshot xml_snapshot_snapshots_are_not_created_for_create_snapshot_zero.xml does not exist.',
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
