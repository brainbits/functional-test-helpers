<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Snapshot;

use Brainbits\FunctionalTestHelpers\Snapshot\SnapshotTrait;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Yaml\Exception\DumpException;

use function assert;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function str_replace;

final class SnapshotFeaturesTest extends TestCase
{
    use SnapshotTrait;

    private vfsStreamDirectory $snapshotsDir;

    public function setUp(): void
    {
        $root = vfsStream::setup('root', null, ['__snapshots__' => []]);

        $snapshotsDir = $root->getChild('__snapshots__');
        assert($snapshotsDir instanceof vfsStreamDirectory);

        $this->snapshotsDir = $snapshotsDir;
    }

    public function testNormalizers(): void
    {
        $this->addSnapshotUuidNormalizer();
        $this->addSnapshotDateTimeNormalizer();
        $this->addSnapshotNormalizer(static fn (string $content): string => str_replace('secret', '***', $content));

        $this->assertMatchesJsonSnapshot(
            '{"id":"1b4e28ba-2fa1-11d2-883f-0016d3cca427","created":"2026-11-03T12:00:00+00:00","token":"secret"}',
        );

        $this->assertSame(
            <<<'JSON'
                {
                    "id": "00000000-0000-0000-0000-000000000000",
                    "created": "snapshot-normalized-datetime",
                    "token": "***"
                }
                JSON,
            file_get_contents($this->snapshotsDir->url() . '/snapshot_features_normalizers.json'),
        );
    }

    public function testNormalizersAreResetForEachTest(): void
    {
        $this->assertMatchesTextSnapshot('secret');

        $this->assertSame(
            'secret',
            file_get_contents(
                $this->snapshotsDir->url() . '/snapshot_features_normalizers_are_reset_for_each_test.txt',
            ),
        );
    }

    public function testCsvSnapshot(): void
    {
        $this->assertMatchesCsvSnapshot([['id', 'name'], [1, 'Peter "P" Parker'], [2, null]]);

        $this->assertSame(
            "id,name\n1,\"Peter \"\"P\"\" Parker\"\n2,\n",
            file_get_contents($this->snapshotsDir->url() . '/snapshot_features_csv_snapshot.csv'),
        );
    }

    public function testYamlSnapshot(): void
    {
        $this->assertMatchesYamlSnapshot(['user' => ['name' => 'peter', 'roles' => ['admin']]]);

        $this->assertSame(
            "user:\n    name: peter\n    roles:\n        - admin\n",
            file_get_contents($this->snapshotsDir->url() . '/snapshot_features_yaml_snapshot.yaml'),
        );
    }

    public function testBinarySnapshot(): void
    {
        file_put_contents($this->snapshotsDir->url() . '/snapshot_features_binary_snapshot.png', "\x89PNG\x00\x01");

        $this->assertMatchesBinarySnapshot("\x89PNG\x00\x01", 'png');

        $this->filenames = [];

        try {
            $this->assertMatchesBinarySnapshot("\x89PNG\x00\x02", 'png');
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString('Binary snapshot differs: expected 6 bytes', $e->getMessage());

            return;
        }

        $this->fail('Binary difference was not detected');
    }

    public function testYamlSnapshotRejectsObjects(): void
    {
        $this->expectException(DumpException::class);

        $this->assertMatchesYamlSnapshot(['object' => new stdClass()]);
    }

    public function testRepeatedSnapshotsOfNewTypesKeepTheirExtension(): void
    {
        $this->assertMatchesYamlSnapshot(['a' => 1]);
        $this->assertMatchesYamlSnapshot(['b' => 2]);
        $this->assertMatchesCsvSnapshot([['a', 'b']]);
        $this->assertMatchesCsvSnapshot([['c', 'd']]);
        $this->assertMatchesTextSnapshot('first');
        $this->assertMatchesTextSnapshot('second');

        $prefix = $this->snapshotsDir->url()
            . '/snapshot_features_repeated_snapshots_of_new_types_keep_their_extension';
        $this->assertFileExists($prefix . '_2.yaml');
        $this->assertFileExists($prefix . '_2.csv');
        // the original types keep the .json extension for repeated snapshots
        $this->assertFileExists($prefix . '_2.json');
    }

    private function snapshotPath(): string
    {
        return vfsStream::url('root');
    }
}
