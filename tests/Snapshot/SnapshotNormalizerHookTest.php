<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Snapshot;

use Brainbits\FunctionalTestHelpers\Snapshot\SnapshotTrait;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;

use function Safe\file_get_contents;
use function str_replace;

final class SnapshotNormalizerHookTest extends TestCase
{
    use SnapshotTrait;

    /**
     * Runs before setUpSnapshot(), like a hook of a project trait.
     */
    #[Before(1)]
    protected function addNormalizerInHook(): void
    {
        vfsStream::setup('root', null, ['__snapshots__' => []]);

        $this->addSnapshotNormalizer(static fn (string $content): string => str_replace('secret', '***', $content));
    }

    public function testNormalizersOfEarlierHooksAreKept(): void
    {
        $this->assertMatchesTextSnapshot('secret');

        $this->assertSame(
            '***',
            file_get_contents(
                vfsStream::url('root')
                . '/__snapshots__/snapshot_normalizer_hook_normalizers_of_earlier_hooks_are_kept.txt',
            ),
        );
    }

    private function snapshotPath(): string
    {
        return vfsStream::url('root');
    }
}
