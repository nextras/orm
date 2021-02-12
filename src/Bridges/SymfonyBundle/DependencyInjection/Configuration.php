<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\SymfonyBundle\DependencyInjection;


use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;


class Configuration implements ConfigurationInterface
{
	public function getConfigTreeBuilder()
	{
		$treeBuilder = new TreeBuilder('nextras_orm');

		$root = $treeBuilder->getRootNode();
		assert($root instanceof ArrayNodeDefinition);

		// @formatter:off
		$root
			->children()
				->scalarNode('model')
					->isRequired()
					->end();
		// @formatter:on

		return $treeBuilder;
	}
}
