<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Snapshot;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function dirname;
use function getenv;
use function mb_strtolower;
use function rtrim;
use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\preg_match;
use function Safe\preg_replace;
use function Safe\sprintf;
use function Safe\substr;
use function str_replace;
use function strlen;
use function strpos;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_LINE_TERMINATORS;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const PHP_EOL;

/**
 * @mixin TestCase
 */
trait SnapshotTrait
{
    /** @var array<string,int> */
    private array $filenames = [];

    /**
     * @param mixed[] $actual
     */
    final protected function assertMatchesArraySnapshot(array $actual, string $message = ''): void
    {
        $this->assertMatchesJsonSnapshot(json_encode($actual), $message);
    }

    final protected function assertMatchesJsonSnapshot(string $actual, string $message = ''): void
    {
        self::assertJson($actual, $message);

        $fixtureFilename = $this->snapshotFilename('json');

        $this->snapshotDumpJson($fixtureFilename, $actual);

        self::assertJsonStringEqualsJsonFile(
            $fixtureFilename,
            $actual,
            $this->snapshotAppendFilenameHint($fixtureFilename, $message)
        );
    }

    final protected function assertMatchesXmlSnapshot(string $actual, string $message = ''): void
    {
        $fixtureFilename = $this->snapshotFilename('xml');

        $this->snapshotDumpXml($fixtureFilename, $actual);

        self::assertXmlStringEqualsXmlFile(
            $fixtureFilename,
            $actual,
            $this->snapshotAppendFilenameHint($fixtureFilename, $message)
        );
    }

    private function snapshotFilename(string $extension): string
    {
        $filename = sprintf(
            '%s/__snapshots__/%s.%s',
            $this->snapshotPath(),
            $this->snapshotName(),
            $extension,
        );

        if (!($this->filenames[$filename] ?? false)) {
            $this->filenames[$filename] = 1;

            return $filename;
        }

        return sprintf(
            '%s/__snapshots__/%s_%s.json',
            $this->snapshotPath(),
            $this->snapshotName(),
            ++$this->filenames[$filename],
        );
    }

    /**
     * Overwrite function in using class to locate __snapshot__ directory correctly.
     */
    private function snapshotPath(): string
    {
        $path = preg_replace('/\\\\[^\\\\]+$/', '', static::class);
        $path = str_replace('\\', '/', $path);

        return $path;
    }

    private function snapshotName(): string
    {
        $class = preg_replace('/.*\\\\/', '', static::class);

        if (substr($class, -4) === 'Test') {
            $class = substr($class, 0, -4);
        }

        $class = preg_replace('/(.)([[:upper:]])/u', '\1_\2', $class);
        $class = mb_strtolower($class);

        $method = $this->getName(false);
        if (strpos($method, 'test') === 0) {
            $method = substr($method, strlen('test'));
        }

        $method = preg_replace('/(.)([[:upper:]])/u', '\1_\2', $method);
        $method = mb_strtolower($method);

        $dataset = mb_strtolower($this->getDataSetAsString(false));
        $matches = [];
        preg_match('/"([^"]+)"/', $dataset, $matches);

        $dataset = $matches[1] ?? $dataset;

        $lowercase = mb_strtolower($class . ':' . $method . ':' . $dataset);
        $noSpecialCharacters = preg_replace('/[^\pL0-9]+/u', '_', $lowercase);

        return rtrim($noSpecialCharacters, '_');
    }

    private function snapshotDumpJson(string $fixtureFilename, string $string): void
    {
        $filesystem = new Filesystem();

        if (!getenv('UPDATE_SNAPSHOTS') && $filesystem->exists($fixtureFilename)) {
            return;
        }

        $filesystem->mkdir(dirname($fixtureFilename));
        $filesystem->dumpFile(
            $fixtureFilename,
            json_encode(
                json_decode($string),
                JSON_UNESCAPED_SLASHES |
                JSON_UNESCAPED_LINE_TERMINATORS |
                JSON_UNESCAPED_UNICODE |
                JSON_PRETTY_PRINT
            )
        );
    }

    private function snapshotDumpXml(string $fixtureFilename, string $string): void
    {
        $filesystem = new Filesystem();

        if (!getenv('UPDATE_SNAPSHOTS') && $filesystem->exists($fixtureFilename)) {
            return;
        }

        $filesystem->mkdir(dirname($fixtureFilename));

        $dom = new DOMDocument();
        $dom->loadXML($string);
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->save($fixtureFilename);
    }

    private function snapshotAppendFilenameHint(string $fixtureFilename, string $message): string
    {
        $filenameHint = 'Snapshot: ' . $fixtureFilename;

        return $message
            ? $message . PHP_EOL . $filenameHint
            : $filenameHint;
    }
}
