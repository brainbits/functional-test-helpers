<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Console;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\ApplicationTester;

/** @mixin WebTestCase */
trait ApplicationTrait
{
    /**
     * @param mixed[] $input
     * @param mixed[] $options
     */
    final protected function runApplication(array $input = [], array $options = []): ApplicationTester
    {
        $application = new Application(self::$kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $applicationTester = new ApplicationTester($application);
        $applicationTester->run($input, $options);

        return $applicationTester;
    }

    final protected static function assertApplicationOutputContains(
        string $needle,
        ApplicationTester $applicationTester,
    ): void {
        self::assertStringContainsString($needle, $applicationTester->getDisplay());
    }

    final protected static function assertApplicationOutputNotContains(
        string $needle,
        ApplicationTester $applicationTester,
    ): void {
        self::assertStringNotContainsString($needle, $applicationTester->getDisplay());
    }

    final protected static function assertApplicationStatusCodeSame(
        int $expectedCode,
        ApplicationTester $applicationTester,
    ): void {
        self::assertSame($expectedCode, $applicationTester->getStatusCode());
    }
}
