<?php

namespace Tui\BugTrackerBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Tui\BugTrackerBundle\Client\BugTrackerClient;
use Tui\BugTrackerBundle\Controller\FeedbackController;

class TuiBugTrackerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $container->getDefinition(BugTrackerClient::class)
            ->setArgument('$baseUrl', $config['base_url'])
            ->setArgument('$apiKey', $config['api_key']);

        $container->getDefinition(FeedbackController::class)
            ->setArgument('$requiredRole', $config['required_role']);
    }
}
