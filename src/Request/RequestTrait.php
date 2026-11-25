<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Request;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

use function array_replace_recursive;
use function assert;
use function bin2hex;
use function method_exists;
use function parse_str;
use function random_bytes;
use function sprintf;
use function str_starts_with;
use function strtolower;
use function urlencode;

/** @mixin TestCase */
trait RequestTrait
{
    /** @var AbstractBrowser<covariant object, covariant object>|null */
    private static AbstractBrowser|null $requestClient = null;

    protected function loginUser(): callable
    {
        return static fn () => null;
    }

    protected function createToken(): callable
    {
        return static fn () => null;
    }

    #[Before(12)]
    protected function setUpRequest(): void
    {
        self::$requestClient = static::createRequestClient();
    }

    /**
     * Creates the client for the requests of a test. Override it to pass kernel options or server defaults,
     * e.g. static::createClient([], ['HTTP_HOST' => 'app.test']).
     *
     * @return AbstractBrowser<covariant object, covariant object>
     */
    protected static function createRequestClient(): AbstractBrowser
    {
        return static::createClient();
    }

    #[After]
    protected function tearDownRequest(): void
    {
        self::$requestClient = null;

        RequestBuilder::removeTemporaryFiles();
    }

    /** @return AbstractBrowser<covariant object, covariant object> */
    protected static function getRequestClient(): AbstractBrowser
    {
        if (self::$requestClient) {
            return self::$requestClient;
        }

        static::fail(sprintf(
            'A client must be set to make assertions on it. Did you forget to call "%s::createClient()"?',
            __CLASS__,
        ));
    }

    /**
     * The firewall used by RequestBuilder::loginAs() if none is given. Override it for another firewall.
     */
    protected function loginFirewall(): string
    {
        return 'main';
    }

    /** @deprecated Use RequestBuilder::csrfToken(), which stores the token in the session. Removed in 8.0. */
    final public function generateCsrfToken(): string
    {
        $client = self::getRequestClient();
        assert($client instanceof KernelBrowser);

        $container = $client->getContainer();
        $tokenGenerator = $container->get('security.csrf.token_generator');

        return $tokenGenerator->generateToken();
    }

    final protected function build(string $method, string $uri): RequestBuilder
    {
        if (method_exists($this, 'findUser')) {
            // deprecated findUser() returns the user, the login on the request client happens here
            $findUser = $this->findUser();
            $callable = static function (mixed $username) use ($findUser): void {
                $client = self::getRequestClient();
                assert($client instanceof KernelBrowser);

                $client->loginUser($findUser($username));
            };
        } else {
            $callable = $this->loginUser();
        }

        return RequestBuilder::create(
            $callable,
            $this->createToken(),
            $method,
            $uri,
            false,
        );
    }

    final protected function request(RequestBuilder $requestBuilder): Response
    {
        $client = self::getRequestClient();

        $followRedirects = $client->isFollowingRedirects();
        if ($requestBuilder->getFollowRedirects() !== null) {
            $client->followRedirects($requestBuilder->getFollowRedirects());
        }

        if ($requestBuilder->isRebootDisabled()) {
            self::requestKernelBrowser('disableReboot()')->disableReboot();
        }

        if ($requestBuilder->isLogout()) {
            $client->getCookieJar()->clear();
        }

        $loginUser = $requestBuilder->getLoginUser();
        if ($loginUser !== null) {
            self::requestKernelBrowser('loginAs()')->loginUser(
                $loginUser,
                $requestBuilder->getLoginFirewall() ?? $this->loginFirewall(),
                $requestBuilder->getLoginTokenAttributes(),
            );
        }

        $sessionValues = $requestBuilder->getSessionValues();
        $parameters = $requestBuilder->getParameters();

        foreach ($requestBuilder->getCsrfTokens() as $tokenId => $field) {
            $token = bin2hex(random_bytes(16));
            $namespace = self::isSecureRequest($client, $requestBuilder) ? 'https-' : '';

            // the session key SessionTokenStorage reads the token from
            $sessionValues['_csrf/' . $namespace . $tokenId] = $token;

            // a field like "profile[_token]" is sent nested, like a browser submits a named form
            $fieldParameters = [];
            parse_str(urlencode($field) . '=' . urlencode($token), $fieldParameters);
            $parameters = array_replace_recursive($parameters, $fieldParameters);
        }

        if ($sessionValues) {
            $this->applySessionValues($sessionValues);
        }

        try {
            $client->request(
                $requestBuilder->getMethod(),
                $requestBuilder->getUri(),
                $parameters,
                $requestBuilder->getFiles(),
                $requestBuilder->getServer(),
                $requestBuilder->getContent(),
                $requestBuilder->getChangeHistory(),
            );
        } finally {
            // followRedirects() applies to this request only
            $client->followRedirects($followRedirects);
        }

        $response = $client->getResponse();
        assert($response instanceof Response);

        return $response;
    }

    private static function requestKernelBrowser(string $feature): KernelBrowser
    {
        $client = self::getRequestClient();
        if (!$client instanceof KernelBrowser) {
            static::fail(sprintf('%s needs a %s as request client.', $feature, KernelBrowser::class));
        }

        return $client;
    }

    /**
     * Same rules as the browser: the HTTPS server parameter wins, otherwise the scheme of the absolute URI.
     * A relative URI is resolved against the current page of the history, or http://localhost.
     *
     * @param AbstractBrowser<covariant object, covariant object> $client
     */
    private static function isSecureRequest(AbstractBrowser $client, RequestBuilder $requestBuilder): bool
    {
        $https = $requestBuilder->getServer()['HTTPS'] ?? $client->getServerParameter('HTTPS', null);
        if ($https !== null) {
            return $https !== false && $https !== '' && $https !== '0' && strtolower((string) $https) !== 'off';
        }

        $uri = $requestBuilder->getUri();
        if (!str_starts_with($uri, 'http://') && !str_starts_with($uri, 'https://')) {
            $uri = $client->getHistory()->isEmpty() ? 'http://localhost' : $client->getHistory()->current()->getUri();
        }

        return str_starts_with($uri, 'https://');
    }

    /** @param array<string, mixed> $sessionValues */
    private function applySessionValues(array $sessionValues): void
    {
        $client = self::getRequestClient();
        assert($client instanceof KernelBrowser);

        // KernelBrowser::getSession() reuses the session cookie of the client (whatever its name and domain is)
        // or creates one that is valid for every host
        $session = $client->getSession();
        if (!$session instanceof SessionInterface) {
            static::fail('Session values need an enabled session, check framework.session in your test configuration.');
        }

        foreach ($sessionValues as $key => $value) {
            $session->set($key, $value);
        }

        $session->save();
    }
}
