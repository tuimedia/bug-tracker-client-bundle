<?php

namespace Tui\BugTrackerBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tui\BugTrackerBundle\Controller\BugTrackerProxyController;

class TuiBugTrackerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        // Register a scoped HttpClient with the tracker base URL and API key
        // pre-applied as defaults. The controller gets a full HttpClientInterface
        // — no wrapper class needed.
        $container->register('tui_bug_tracker.http_client', HttpClientInterface::class)
            ->setFactory([new Reference('http_client'), 'withOptions'])
            ->setArguments([[
                'base_uri' => $config['base_url'],
                'auth_bearer' => $config['api_key'],
            ]]);

        $container->getDefinition(BugTrackerProxyController::class)
            ->setArgument('$client', new Reference('tui_bug_tracker.http_client'))
            ->setArgument('$requiredRole', $config['required_role']);
    }
}
