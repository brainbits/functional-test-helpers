<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Exception;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Hit;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\MatchResult;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Mismatch;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Missing;
use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;
use RuntimeException;

use function array_map;
use function explode;
use function implode;
use function sprintf;

use const PHP_EOL;

final class NoMatchingMockRequest extends RuntimeException implements HttpClientMockException
{
    public static function noBuilders(RealRequest $request): self
    {
        $message = sprintf('No mock request builders given for:%s%s%s', PHP_EOL, $request, PHP_EOL);

        return new self($message);
    }

    /** @param MatchResult[] $matchResults */
    public static function fromResults(RealRequest $request, array $matchResults): self
    {
        $message = sprintf('No matching mock request builder found for:%s%s%s', PHP_EOL, $request, PHP_EOL);
        $message .= sprintf('%sMock request builders:%s', PHP_EOL, PHP_EOL);

        $tick = '✔';
        $cross = '✘';

        foreach ($matchResults as $key => $matchResult) {
            $no = $key + 1;
            $name = $matchResult->getName();

            $message .= sprintf(
                '#%s %s%s',
                $no,
                $name ?? '(unnamed)',
                PHP_EOL,
            );

            foreach ($matchResult->getResults() as $result) {
                if ($result instanceof Hit) {
                    if ($result->key) {
                        $line = sprintf(
                            '%s %s %s matches "%s"',
                            $tick,
                            $result->matcher,
                            $result->key,
                            $result->actual,
                        );
                    } else {
                        $line = sprintf(
                            '%s %s matches "%s"',
                            $tick,
                            $result->matcher,
                            $result->actual,
                        );
                    }
                } elseif ($result instanceof Mismatch) {
                    if ($result->key) {
                        $line = sprintf(
                            '%s %s %s "%s" does not match "%s"',
                            $cross,
                            $result->matcher,
                            $result->key,
                            $result->actual ?? 'NULL',
                            $result->expected,
                        );
                    } else {
                        $line = sprintf(
                            '%s %s "%s" does not match "%s"',
                            $cross,
                            $result->matcher,
                            $result->actual ?? 'NULL',
                            $result->expected,
                        );
                    }
                } elseif ($result instanceof Missing) {
                    $line = sprintf(
                        '%s %s %s missing',
                        $cross,
                        $result->matcher,
                        $result->key,
                    );
                } else {
                    continue;
                }

                $message .= sprintf(
                    '  %s (%s)%s',
                    $line,
                    $result->score,
                    PHP_EOL,
                );

                if ($result instanceof Mismatch && $result->diff) { // phpcs:ignore
                    $diff = implode(
                        PHP_EOL,
                        array_map(
                            static fn ($line) => '    ' . $line,
                            explode(PHP_EOL, $result->diff),
                        ),
                    );
                    $message .= $diff . PHP_EOL;
                }
            }
        }

        return new self($message);
    }
}
