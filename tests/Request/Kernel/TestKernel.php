<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Request\Kernel;

use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function md5_file;
use function sys_get_temp_dir;

/**
 * Minimal kernel for functional tests of the request trait.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** @return iterable<FrameworkBundle> */
    public function registerBundles(): iterable
    {
        return [new FrameworkBundle()];
    }

    public function getCacheDir(): string
    {
        // a changed kernel (e.g. a new route) must not use the cached container and router of an older version
        return sys_get_temp_dir() . '/functional-test-helpers/request-kernel/cache-' . md5_file(__FILE__);
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/functional-test-helpers/request-kernel/log';
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function dump(Request $request): JsonResponse
    {
        return new JsonResponse([
            'host' => $request->getHost(),
            'session' => $request->hasPreviousSession() ? $request->getSession()->all() : null,
        ]);
    }

    public function csrf(Request $request, CsrfTokenManagerInterface $tokenManager): JsonResponse
    {
        $token = new CsrfToken('form', (string) $request->request->get('_token'));

        return new JsonResponse(['valid' => $tokenManager->isTokenValid($token)]);
    }

    public function csrfNested(Request $request, CsrfTokenManagerInterface $tokenManager): JsonResponse
    {
        $token = new CsrfToken('profile', (string) ($request->request->all('profile')['_token'] ?? ''));

        return new JsonResponse(['valid' => $tokenManager->isTokenValid($token)]);
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
            'secret' => 'test',
            'http_method_override' => false,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'router' => ['utf8' => true],
            'csrf_protection' => ['enabled' => true],
        ]);

        $container->services()
            ->set('logger', NullLogger::class)
            ->set(self::class)
            ->public()
            ->autowire()
            ->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('dump', '/dump')->controller([self::class, 'dump']);
        $routes->add('csrf', '/csrf')->controller([self::class, 'csrf']);
        $routes->add('csrf_nested', '/csrf-nested')->controller([self::class, 'csrfNested']);
    }
}
