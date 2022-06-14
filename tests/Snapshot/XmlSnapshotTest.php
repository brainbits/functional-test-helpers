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

        try {
            $this->assertMatchesXmlSnapshot($data);
        } catch (ExpectationFailedException $e) {
            return;
        }

        $this->fail('Assertion did not fail');
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
        } catch (ExpectationFailedException $e) {
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
