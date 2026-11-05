<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Snapshot;

use Brainbits\FunctionalTestHelpers\Snapshot\SnapshotTrait;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

use function Safe\file_put_contents;

final class StrictJsonSnapshotTest extends TestCase
{
    use SnapshotTrait;

    public function setUp(): void
    {
        vfsStream::setup('root', null, ['__snapshots__' => []]);
    }

    public function testKeyOrderMattersInStrictMode(): void
    {
        file_put_contents(
            vfsStream::url('root/__snapshots__/strict_json_snapshot_key_order_matters_in_strict_mode.json'),
            "{\n    \"a\": 1,\n    \"b\": 2\n}",
        );

        try {
            $this->assertMatchesJsonSnapshot('{"b":2,"a":1}');
        } catch (ExpectationFailedException) {
            return;
        }

        $this->fail('Key order was ignored in strict mode');
    }

    protected function snapshotStrictJson(): bool
    {
        return true;
    }

    private function snapshotPath(): string
    {
        return vfsStream::url('root');
    }
}
