<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Snapshot;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use tidy;

use function dirname;
use function getenv;
use function is_string;
use function mb_strtolower;
use function rtrim;
use function Safe\array_walk_recursive;
use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\preg_match;
use function Safe\preg_replace;
use function Safe\sprintf;
use function Safe\substr;
use function str_contains;
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
        $this->assertMatchesNamedArraySnapshot($actual, '', $message);
    }

    /**
     * @param mixed[] $actual
     */
    final protected function assertMatchesNamedArraySnapshot(array $actual, string $name, string $message = ''): void
    {
        $this->assertMatchesNamedJsonSnapshot(json_encode($actual), $name, $message);
    }

    final protected function assertMatchesTextSnapshot(string $actual, string $message = ''): void
    {
        $this->assertMatchesNamedTextSnapshot($actual, '', $message);
    }

    final protected function assertMatchesNamedTextSnapshot(string $actual, string $name, string $message = ''): void
    {
        if (getenv('SKIP_SNAPSHOT_TESTS')) {
            self::markTestSkipped('Skipping snapshot tests');
        }

        $fixtureFilename = $this->snapshotFilename('txt', $name);

        $this->snapshotDump($fixtureFilename, $actual);

        self::assertStringEqualsFile(
            $fixtureFilename,
            $actual,
            $this->snapshotAppendFilenameHint($fixtureFilename, $message)
        );
    }

    final protected function assertMatchesJsonSnapshot(string $actual, string $message = ''): void
    {
        $this->assertMatchesNamedJsonSnapshot($actual, '', $message);
    }

    final protected function assertMatchesJsonLdSnapshot(string $actual, string $message = ''): void
    {
        $this->assertMatchesNamedJsonLdSnapshot($actual, '', $message);
    }

    final protected function assertMatchesNamedJsonSnapshot(string $actual, string $name, string $message = ''): void
    {
        if (getenv('SKIP_SNAPSHOT_TESTS')) {
            self::markTestSkipped('Skipping snapshot tests');
        }

        self::assertJson($actual, $message);

        $fixtureFilename = $this->snapshotFilename('json', $name);
        $actual = $this->snapshotFormatJson($actual);

        $this->snapshotDump($fixtureFilename, $actual);

        self::assertJsonStringEqualsJsonFile(
            $fixtureFilename,
            $actual,
            $this->snapshotAppendFilenameHint($fixtureFilename, $message)
        );
    }

    final protected function assertMatchesNamedJsonLdSnapshot(string $actual, string $name, string $message = ''): void
    {
        if (getenv('SKIP_SNAPSHOT_TESTS')) {
            self::markTestSkipped('Skipping snapshot tests');
        }

        self::assertJson($actual, $message);

        $fixtureFilename = $this->snapshotFilename('json', $name);
        $actual = $this->snapshotFormatJsonLd($actual);

        $this->snapshotDump($fixtureFilename, $actual);

        self::assertJsonStringEqualsJsonFile(
            $fixtureFilename,
            $actual,
            $this->snapshotAppendFilenameHint($fixtureFilename, $message)
        );
    }

    final protected function assertMatchesXmlSnapshot(string $actual, string $message = ''): void
    {
        $this->assertMatchesNamedXmlSnapshot($actual, '', $message);
    }

    final protected function assertMatchesNamedXmlSnapshot(string $actual, string $name, string $message = ''): void
    {
        if (getenv('SKIP_SNAPSHOT_TESTS')) {
            self::markTestSkipped('Skipping snapshot tests');
        }

        self::assertThat($actual, new IsXml(), $message);

        $fixtureFilename = $this->snapshotFilename('xml', $name);
        $actual = $this->snapshotFormatXml($actual);

        $this->snapshotDump($fixtureFilename, $actual);

        self::assertXmlStringEqualsXmlFile(
            $fixtureFilename,
            $actual,
            $this->snapshotAppendFilenameHint($fixtureFilename, $message)
        );
    }

    final protected function assertMatchesHtmlSnapshot(string $actual, string $message = ''): void
    {
        $this->assertMatchesNamedHtmlSnapshot($actual, '', $message);
    }

    final protected function assertMatchesNamedHtmlSnapshot(string $actual, string $name, string $message = ''): void
    {
        if (getenv('SKIP_SNAPSHOT_TESTS')) {
            self::markTestSkipped('Skipping snapshot tests');
        }

        $fixtureFilename = $this->snapshotFilename('html', $name);
        $actual = $this->snapshotFormatHtml($actual);

        $this->snapshotDump($fixtureFilename, $actual);

        self::assertStringEqualsFile(
            $fixtureFilename,
            $actual,
            $this->snapshotAppendFilenameHint($fixtureFilename, $message)
        );
    }

    private function snapshotFilename(string $extension, string $name): string
    {
        $filename = sprintf(
            '%s/__snapshots__/%s.%s',
            $this->snapshotPath(),
            $this->snapshotName($name),
            $extension,
        );

        if (!($this->filenames[$filename] ?? false)) {
            $this->filenames[$filename] = 1;

            return $filename;
        }

        return sprintf(
            '%s/__snapshots__/%s_%s.json',
            $this->snapshotPath(),
            $this->snapshotName($name),
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

    private function snapshotName(string $postfix): string
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

        $lowercase = mb_strtolower($class . ':' . $method . ':' . ($postfix ? $postfix . ':' : '') . $dataset);
        $noSpecialCharacters = preg_replace('/[^\pL0-9]+/u', '_', $lowercase);

        return rtrim($noSpecialCharacters, '_');
    }

    private function snapshotFormatJson(string $string): string
    {
        return json_encode(
            json_decode($string),
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_LINE_TERMINATORS |
            JSON_UNESCAPED_UNICODE |
            JSON_PRETTY_PRINT
        );
    }

    private function snapshotFormatJsonLd(string $string): string
    {
        $data = json_decode($string, true);

        array_walk_recursive(
            $data,
            static function (&$item, $key): void {
                // phpcs:ignore
                if ($key === '@id' && is_string($item) && str_contains($item, '/.well-known/genid/')) {
                    $item = 'snapshot-normalized-id';
                }
            }
        );

        return json_encode(
            $data,
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_LINE_TERMINATORS |
            JSON_UNESCAPED_UNICODE |
            JSON_PRETTY_PRINT
        );
    }

    private function snapshotFormatXml(string $string): string
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($string);
        $dom->formatOutput = true;

        return (string) $dom->saveXML();
    }

    private function snapshotFormatHtml(string $string): string
    {
        return (new tidy())->repairString($string, ['indent' => true, 'indent-spaces' => 4, 'wrap' => 999999]);
    }

    private function snapshotDump(string $fixtureFilename, string $string): void
    {
        $filesystem = new Filesystem();

        if (!getenv('UPDATE_SNAPSHOTS') && $filesystem->exists($fixtureFilename)) {
            return;
        }

        $filesystem->mkdir(dirname($fixtureFilename));
        $filesystem->dumpFile($fixtureFilename, $string);
    }

    private function snapshotAppendFilenameHint(string $fixtureFilename, string $message): string
    {
        $filenameHint = 'Snapshot: ' . $fixtureFilename;

        return $message
            ? $message . PHP_EOL . $filenameHint
            : $filenameHint;
    }
}
