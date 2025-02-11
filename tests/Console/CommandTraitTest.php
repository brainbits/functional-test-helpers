<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Console;

use Brainbits\FunctionalTestHelpers\Console\CommandTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommandTraitTest extends TestCase
{
    use CommandTrait;

    private static KernelInterface|null $kernel = null;

    public function testMockRequest(): void
    {
        $container = new Container();

        self::$kernel = $this->createMock(KernelInterface::class);
        self::$kernel->expects($this->atLeastOnce())
            ->method('getContainer')
            ->willReturn($container);

        $tester = $this->runCommand('help');

        $this->assertCommandOutputContains('help', $tester);
    }
}
