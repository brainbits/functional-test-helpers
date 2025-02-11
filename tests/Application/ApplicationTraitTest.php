<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Application;

use Brainbits\FunctionalTestHelpers\Console\ApplicationTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelInterface;

final class ApplicationTraitTest extends TestCase
{
    use ApplicationTrait;

    private static KernelInterface|null $kernel = null;

    public function testMockRequest(): void
    {
        $container = new Container();
        $container->set('event_dispatcher', new EventDispatcher());

        self::$kernel = $this->createMock(KernelInterface::class);
        self::$kernel->expects($this->atLeastOnce())
            ->method('getContainer')
            ->willReturn($container);

        $tester = $this->runApplication(['help']);

        $this->assertApplicationOutputContains('help', $tester);

        self::$kernel->shutdown();
    }
}
