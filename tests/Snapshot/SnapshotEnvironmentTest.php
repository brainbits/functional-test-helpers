<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Snapshot;

use Brainbits\FunctionalTestHelpers\Snapshot\SnapshotTrait;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

use function assert;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\putenv;

final class SnapshotEnvironmentTest extends TestCase
{
    use SnapshotTrait;

    private const array VARIABLES = ['UPDATE_SNAPSHOTS', 'CREATE_SNAPSHOTS', 'SKIP_SNAPSHOT_TESTS'];

    private vfsStreamDirectory $snapshotsDir;

    public function setUp(): void
    {
        $root = vfsStream::setup('root', null, ['__snapshots__' => []]);

        $snapshotsDir = $root->getChild('__snapshots__');
        assert($snapshotsDir instanceof vfsStreamDirectory);

        $this->snapshotsDir = $snapshotsDir;

        $this->clearVariables();
    }

    #[After]
    public function clearVariables(): void
    {
        foreach (self::VARIABLES as $variable) {
            putenv($variable);
            unset($_ENV[$variable], $_SERVER[$variable]);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function provideFalseValues(): iterable
    {
        yield 'false' => ['false'];
        yield 'FALSE' => ['FALSE'];
        yield '0' => ['0'];
        yield 'no' => ['no'];
        yield 'off' => ['off'];
    }

    #[DataProvider('provideFalseValues')]
    public function testUpdateSnapshotsWithFalseValueDoesNotUpdate(string $value): void
    {
        $this->assertMatchesTextSnapshot('old');
        $filename = $this->onlySnapshotFile();

        putenv('UPDATE_SNAPSHOTS=' . $value);
        $this->filenames = [];

        try {
            $this->assertMatchesTextSnapshot('new');
            $this->fail('Assertion did not fail');
        } catch (ExpectationFailedException) {
        }

        $this->assertSame('old', file_get_contents($filename));
    }

    public function testUpdateSnapshotsWithTrueValueUpdates(): void
    {
        $filename = $this->snapshotsDir->url() . '/snapshot_environment_update_snapshots_with_true_value_updates.txt';
        file_put_contents($filename, 'old');

        putenv('UPDATE_SNAPSHOTS=1');

        $this->assertMatchesTextSnapshot('new');

        $this->assertSame('new', file_get_contents($filename));
    }

    #[DataProvider('provideFalseValues')]
    public function testSkipSnapshotTestsWithFalseValueDoesNotSkip(string $value): void
    {
        putenv('SKIP_SNAPSHOT_TESTS=' . $value);

        $this->assertMatchesTextSnapshot('text');

        $this->assertSame('text', file_get_contents($this->onlySnapshotFile()));
    }

    public function testUpdateSnapshotsIsReadFromEnvSuperglobal(): void
    {
        $filename = $this->snapshotsDir->url() .
            '/snapshot_environment_update_snapshots_is_read_from_env_superglobal.txt';
        file_put_contents($filename, 'old');

        $_ENV['UPDATE_SNAPSHOTS'] = '1';

        $this->assertMatchesTextSnapshot('new');

        $this->assertSame('new', file_get_contents($filename));
    }

    public function testCreateSnapshotsIsReadFromServerSuperglobal(): void
    {
        $_SERVER['CREATE_SNAPSHOTS'] = '0';

        try {
            $this->assertMatchesTextSnapshot('text');
        } catch (AssertionFailedError) {
            $this->assertFileDoesNotExist(
                $this->snapshotsDir->url() .
                '/snapshot_environment_create_snapshots_is_read_from_server_superglobal.txt',
            );

            return;
        }

        $this->fail('Assertion did not fail');
    }

    public function testGetenvTakesPrecedenceOverSuperglobals(): void
    {
        $filename = $this->snapshotsDir->url() . '/snapshot_environment_getenv_takes_precedence_over_superglobals.txt';
        file_put_contents($filename, 'old');

        putenv('UPDATE_SNAPSHOTS=0');
        $_ENV['UPDATE_SNAPSHOTS'] = '1';
        $_SERVER['UPDATE_SNAPSHOTS'] = '1';

        try {
            $this->assertMatchesTextSnapshot('new');
            $this->fail('Assertion did not fail');
        } catch (ExpectationFailedException) {
        }

        $this->assertSame('old', file_get_contents($filename));
    }

    private function onlySnapshotFile(): string
    {
        $children = $this->snapshotsDir->getChildren();
        $this->assertCount(1, $children);

        return $children[0]->url();
    }

    private function snapshotPath(): string
    {
        return vfsStream::url('root');
    }
}
