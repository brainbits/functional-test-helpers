<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Snapshot;

use DOMDocument;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use tidy;

use function basename;
use function class_exists;
use function dirname;
use function fputcsv;
use function getenv;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function mb_strtolower;
use function rtrim;
use function Safe\array_walk_recursive;
use function Safe\fclose;
use function Safe\file_get_contents;
use function Safe\fopen;
use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\preg_match;
use function Safe\preg_replace;
use function Safe\rewind;
use function Safe\stream_get_contents;
use function sha1;
use function sprintf;
use function str_contains;
use function str_replace;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trigger_error;
use function trim;

use const E_USER_WARNING;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_LINE_TERMINATORS;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const PHP_EOL;

/** @mixin TestCase */
trait SnapshotTrait
{
    /** @var array<string,int> */
    private array $filenames;

    /** @var list<callable(string): string> */
    private array $snapshotNormalizers = [];

    #[Before]
    final protected function setUpSnapshot(): void
    {
        $this->filenames = [];
        $this->snapshotNormalizers = [];
    }

    /**
     * Adds a normalizer for the snapshots of the current test, e.g. to blank out timestamps or generated ids.
     * It receives the formatted snapshot content and returns the normalized content.
     *
     * @param callable(string): string $normalizer
     */
    protected function addSnapshotNormalizer(callable $normalizer): void
    {
        $this->snapshotNormalizers[] = $normalizer;
    }

    /**
     * Replaces all matches of the regular expression in the snapshots of the current test.
     */
    protected function addSnapshotPatternNormalizer(string $pattern, string $replacement): void
    {
        $this->addSnapshotNormalizer(
            static fn (string $content): string => preg_replace($pattern, $replacement, $content),
        );
    }

    /**
     * Replaces UUIDs with the nil UUID in the snapshots of the current test.
     */
    protected function addSnapshotUuidNormalizer(): void
    {
        $this->addSnapshotPatternNormalizer(
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
            '00000000-0000-0000-0000-000000000000',
        );
    }

    /**
     * Replaces ISO 8601 date times (e.g. 2026-01-31T12:00:00+00:00) in the snapshots of the current test.
     */
    protected function addSnapshotDateTimeNormalizer(string $replacement = 'snapshot-normalized-datetime'): void
    {
        $this->addSnapshotPatternNormalizer(
            '/\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:?\d{2})?/',
            $replacement,
        );
    }

    /**
     * Override to compare JSON snapshots strictly, including key order and formatting.
     */
    protected function snapshotStrictJson(): bool
    {
        return false;
    }

    /**
     * Override to change the tidy configuration used for HTML snapshots.
     *
     * @return array<string, mixed>
     */
    protected function snapshotTidyConfig(): array
    {
        return ['indent' => true, 'indent-spaces' => 4, 'wrap' => 999999];
    }

    /** @param list<list<scalar|null>> $rows */
    protected function assertMatchesCsvSnapshot(array $rows, string $message = ''): void
    {
        $this->assertMatchesNamedCsvSnapshot($rows, '', $message);
    }

    /** @param list<list<scalar|null>> $rows */
    protected function assertMatchesNamedCsvSnapshot(array $rows, string $name, string $message = ''): void
    {
        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '', "\n");
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        $this->snapshotAssertText('csv', $name, $csv, $message);
    }

    protected function assertMatchesYamlSnapshot(mixed $actual, string $message = ''): void
    {
        $this->assertMatchesNamedYamlSnapshot($actual, '', $message);
    }

    protected function assertMatchesNamedYamlSnapshot(mixed $actual, string $name, string $message = ''): void
    {
        if (self::snapshotEnvFlag('SKIP_SNAPSHOT_TESTS') === true) {
            self::markTestSkipped('Skipping snapshot tests');
        }

        if (!class_exists(Yaml::class)) {
            self::fail('YAML snapshots need symfony/yaml, install it with "composer require --dev symfony/yaml".');
        }

        // objects can't be represented in YAML, an exception is better than a silent null
        $yaml = Yaml::dump($actual, 10, 4, Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE);

        $this->snapshotAssertText('yaml', $name, $yaml, $message);
    }

    /**
     * Compares binary data (e.g. images or PDFs) byte by byte.
     */
    protected function assertMatchesBinarySnapshot(string $actual, string $extension, string $message = ''): void
    {
        $this->assertMatchesNamedBinarySnapshot($actual, $extension, '', $message);
    }

    protected function assertMatchesNamedBinarySnapshot(
        string $actual,
        string $extension,
        string $name,
        string $message = '',
    ): void {
        if (self::snapshotEnvFlag('SKIP_SNAPSHOT_TESTS') === true) {
            self::markTestSkipped('Skipping snapshot tests');
        }

        $fixtureFilename = $this->snapshotFilename($extension, $name);

        $this->snapshotDump($fixtureFilename, $actual);

        $expected = file_get_contents($fixtureFilename);

        self::assertTrue(
            $expected === $actual,
            $this->snapshotAppendFilenameHint($fixtureFilename, sprintf(
                '%sBinary snapshot differs: expected %d bytes (sha1 %s), got %d bytes (sha1 %s).',
                $message !== '' ? $message . PHP_EOL : '',
                strlen($expected),
                sha1($expected),
                strlen($actual),
                sha1($actual),
            )),
        );
    }

    private function snapshotAssertText(string $extension, string $name, string $actual, string $message): void
    {
        if (self::snapshotEnvFlag('SKIP_SNAPSHOT_TESTS') === true) {
            self::markTestSkipped('Skipping snapshot tests');
        }

        $fixtureFilename = $this->snapshotFilename($extension, $name);
        $actual = $this->snapshotApplyNormalizers($actual);

        $this->snapshotDump($fixtureFilename, $actual);

        self::assertStringEqualsFile(
            $fixtureFilename,
            $actual,
            $this->snapshotAppendFilenameHint($fixtureFilename, $message),
        );
    }

    private function snapshotApplyNormalizers(string $content): string
    {
        foreach ($this->snapshotNormalizers as $normalizer) {
            $content = $normalizer($content);
        }

        return $content;
    }

    private function snapshotAssertJsonFile(string $fixtureFilename, string $actual, string $message): void
    {
        if ($this->snapshotStrictJson()) {
            self::assertStringEqualsFile(
                $fixtureFilename,
                $actual,
                $this->snapshotAppendFilenameHint($fixtureFilename, $message),
            );

            return;
        }

        self::assertJsonStringEqualsJsonFile(
            $fixtureFilename,
            $actual,
            $this->snapshotAppendFilenameHint($fixtureFilename, $message),
        );
    }

    /** @param mixed[] $actual */
    final protected function assertMatchesArraySnapshot(array $actual, string $message = ''): void
    {
        $this->assertMatchesNamedArraySnapshot($actual, '', $message);
    }

    /** @param mixed[] $actual */
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
        $this->snapshotAssertText('txt', $name, $actual, $message);
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
        if (self::snapshotEnvFlag('SKIP_SNAPSHOT_TESTS') === true) {
            self::markTestSkipped('Skipping snapshot tests');
        }

        $fixtureFilename = $this->snapshotFilename('json', $name);

        self::assertJson($actual, $this->snapshotAppendFilenameHint($fixtureFilename, $message));
        $actual = $this->snapshotFormatJson($actual);

        $actual = $this->snapshotApplyNormalizers($actual);

        $this->snapshotDump($fixtureFilename, $actual);

        $this->snapshotAssertJsonFile($fixtureFilename, $actual, $message);
    }

    final protected function assertMatchesNamedJsonLdSnapshot(string $actual, string $name, string $message = ''): void
    {
        if (self::snapshotEnvFlag('SKIP_SNAPSHOT_TESTS') === true) {
            self::markTestSkipped('Skipping snapshot tests');
        }

        $fixtureFilename = $this->snapshotFilename('json', $name);

        self::assertJson($actual, $this->snapshotAppendFilenameHint($fixtureFilename, $message));
        $actual = $this->snapshotFormatJsonLd($actual);

        $actual = $this->snapshotApplyNormalizers($actual);

        $this->snapshotDump($fixtureFilename, $actual);

        $this->snapshotAssertJsonFile($fixtureFilename, $actual, $message);
    }

    final protected function assertMatchesXmlSnapshot(string $actual, string $message = ''): void
    {
        $this->assertMatchesNamedXmlSnapshot($actual, '', $message);
    }

    final protected function assertMatchesNamedXmlSnapshot(string $actual, string $name, string $message = ''): void
    {
        if (self::snapshotEnvFlag('SKIP_SNAPSHOT_TESTS') === true) {
            self::markTestSkipped('Skipping snapshot tests');
        }

        $fixtureFilename = $this->snapshotFilename('xml', $name);

        self::assertThat($actual, new IsXml(), $this->snapshotAppendFilenameHint($fixtureFilename, $message));
        $actual = $this->snapshotApplyNormalizers($this->snapshotFormatXml($actual));

        $this->snapshotDump($fixtureFilename, $actual);

        self::assertXmlStringEqualsXmlFile(
            $fixtureFilename,
            $actual,
            $this->snapshotAppendFilenameHint($fixtureFilename, $message),
        );
    }

    final protected function assertMatchesHtmlSnapshot(string $actual, string $message = ''): void
    {
        $this->assertMatchesNamedHtmlSnapshot($actual, '', $message);
    }

    final protected function assertMatchesNamedHtmlSnapshot(string $actual, string $name, string $message = ''): void
    {
        if (self::snapshotEnvFlag('SKIP_SNAPSHOT_TESTS') === true) {
            self::markTestSkipped('Skipping snapshot tests');
        }

        $this->snapshotAssertText('html', $name, $this->snapshotFormatHtml($actual), $message);
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

        // repeated snapshots of the original types are stored as .json, kept for compatibility
        $repeatedExtension = in_array($extension, ['html', 'json', 'txt', 'xml'], true) ? 'json' : $extension;

        return sprintf(
            '%s/__snapshots__/%s_%s.%s',
            $this->snapshotPath(),
            $this->snapshotName($name),
            ++$this->filenames[$filename],
            $repeatedExtension,
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

        $method = $this->name();

        if (strpos($method, 'test') === 0) {
            $method = substr($method, strlen('test'));
        }

        $method = preg_replace('/(.)([[:upper:]])/u', '\1_\2', $method);
        $method = mb_strtolower($method);

        $dataset = mb_strtolower($this->dataSetAsString());

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
            JSON_PRETTY_PRINT,
        );
    }

    private function snapshotFormatJsonLd(string $string): string
    {
        $data = json_decode($string, true);

        if (is_array($data)) {
            array_walk_recursive(
                $data,
                static function (&$item, $key): void {
                    // phpcs:ignore
                    if ($key === '@id' && is_string($item) && str_contains($item, '/.well-known/genid/')) {
                        $item = 'snapshot-normalized-id';
                    }
                },
            );
        }

        return json_encode(
            $data,
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_LINE_TERMINATORS |
            JSON_UNESCAPED_UNICODE |
            JSON_PRETTY_PRINT,
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
        return (new tidy())->repairString($string, $this->snapshotTidyConfig());
    }

    private function snapshotDump(string $fixtureFilename, string $string): void
    {
        $filesystem = new Filesystem();

        if (self::snapshotEnvFlag('UPDATE_SNAPSHOTS') !== true && $filesystem->exists($fixtureFilename)) {
            return;
        }

        $isCreated = !$filesystem->exists($fixtureFilename);

        if ($isCreated && !self::snapshotCreationAllowed()) {
            self::fail(sprintf(
                'Snapshot %s does not exist.' . PHP_EOL .
                'Snapshot: %s' . PHP_EOL .
                'Creating snapshots is disabled by CREATE_SNAPSHOTS. Run the test locally to create the snapshot.',
                basename($fixtureFilename),
                $fixtureFilename,
            ));
        }

        $filesystem->mkdir(dirname($fixtureFilename));
        $filesystem->dumpFile($fixtureFilename, $string);

        if (!$isCreated || self::snapshotEnvFlag('SNAPSHOT_CREATED_WARNING') !== true) {
            return;
        }

        trigger_error(sprintf('Snapshot %s was created.', $fixtureFilename), E_USER_WARNING);
    }

    /**
     * CREATE_SNAPSHOTS=0 disables creating snapshots, CREATE_SNAPSHOTS=auto disables it when the CI variable is set.
     */
    private static function snapshotCreationAllowed(): bool
    {
        $value = getenv('CREATE_SNAPSHOTS');
        if ($value === false || $value === '') {
            $value = $_SERVER['CREATE_SNAPSHOTS'] ?? $_ENV['CREATE_SNAPSHOTS'] ?? null;
        }

        if (is_string($value) && strtolower(trim($value)) === 'auto') {
            return self::snapshotEnvFlag('CI') !== true;
        }

        return self::snapshotEnvFlag('CREATE_SNAPSHOTS') !== false;
    }

    /**
     * Reads a boolean flag from the environment (getenv(), $_SERVER, $_ENV).
     * Returns null if the variable is not set or empty, false for "0", "false", "no" and "off", true otherwise.
     */
    private static function snapshotEnvFlag(string $name): bool|null
    {
        $value = getenv($name);

        if ($value === false || $value === '') {
            $value = $_SERVER[$name] ?? $_ENV[$name] ?? null;
        }

        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return null;
        }

        return !in_array($value, ['0', 'false', 'no', 'off'], true);
    }

    private function snapshotAppendFilenameHint(string $fixtureFilename, string $message): string
    {
        $filenameHint = 'Snapshot: ' . $fixtureFilename;

        return $message
            ? $message . PHP_EOL . $filenameHint
            : $filenameHint;
    }
}
