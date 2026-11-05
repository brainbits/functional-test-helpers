<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

/**
 * A matcher that may invoke a user callback. Callback matchers are only evaluated
 * once all other matchers of a mock request builder matched, so callbacks (and
 * assertions inside them) never run for requests meant for other mocks.
 */
interface CallbackMatcher extends Matcher
{
    public function isCallback(): bool;
}
