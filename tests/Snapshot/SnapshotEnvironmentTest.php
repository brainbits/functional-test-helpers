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
use function getenv;
use function restore_error_handler;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\putenv;
use function set_error_handler;

use const E_USER_WARNING;

final class SnapshotEnvironmentTest extends TestCase
{
    use SnapshotTrait;

    private const array VARIABLES = [
        'UPDATE_SNAPSHOTS',
        'CREATE_SNAPSHOTS',
        'SKIP_SNAPSHOT_TESTS',
        'CI',
        'SNAPSHOT_CREATED_WARNING',
    ];

    private vfsStreamDirectory $snapshotsDir;

    /** @var array<string, array{env: string|false, _ENV: mixed, _SERVER: mixed}> */
    private array $backup = [];

    public function setUp(): void
    {
        $this->backupVariables();
        $this->clearVariables();

        $root = vfsStream::setup('root', null, ['__snapshots__' => []]);

        $snapshotsDir = $root->getChild('__snapshots__');
        assert($snapshotsDir instanceof vfsStreamDirectory);

        $this->snapshotsDir = $snapshotsDir;
    }

    #[After]
    public function restoreVariables(): void
    {
        $this->clearVariables();

        foreach ($this->backup as $variable => $values) {
            if ($values['env'] !== false) {
                putenv($variable . '=' . $values['env']);
            }

            if ($values['_ENV'] !== null) {
                $_ENV[$variable] = $values['_ENV'];
            }

            if ($values['_SERVER'] === null) {
                continue;
            }

            $_SERVER[$variable] = $values['_SERVER'];
        }

        $this->backup = [];
    }

    private function backupVariables(): void
    {
        foreach (self::VARIABLES as $variable) {
            $this->backup[$variable] = [
                'env' => getenv($variable),
                '_ENV' => $_ENV[$variable] ?? null,
                '_SERVER' => $_SERVER[$variable] ?? null,
            ];
        }
    }

    private function clearVariables(): void
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

    public function testCreateSnapshotsAutoDisablesCreationOnCi(): void
    {
        putenv('CREATE_SNAPSHOTS=auto');
        putenv('CI=true');

        try {
            $this->assertMatchesTextSnapshot('text');
        } catch (AssertionFailedError) {
            $this->assertCount(0, $this->snapshotsDir->getChildren());

            return;
        }

        $this->fail('Snapshot was created on CI');
    }

    public function testCreateSnapshotsAutoCreatesSnapshotsOutsideCi(): void
    {
        putenv('CREATE_SNAPSHOTS=auto');

        $this->assertMatchesTextSnapshot('text');

        $this->assertSame('text', file_get_contents($this->onlySnapshotFile()));
    }

    public function testSnapshotCreatedWarning(): void
    {
        putenv('SNAPSHOT_CREATED_WARNING=1');

        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;

            return true;
        }, E_USER_WARNING);

        try {
            $this->assertMatchesTextSnapshot('text');
            $this->filenames = [];
            $this->assertMatchesTextSnapshot('text');
        } finally {
            restore_error_handler();
        }

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('was created', $warnings[0]);
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
