<?php

namespace Tui\BugTrackerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('tui_bug_tracker');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('base_url')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('api_key')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('required_role')->defaultValue('ROLE_FEEDBACK')->cannotBeEmpty()->end()
            ->end();

        return $treeBuilder;
    }
}
