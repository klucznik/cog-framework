<?php

namespace Cog\Command;

use Closure;
use Cog\BaseApplication;
use League\Container\Container;
use League\Container\Definition\DefinitionInterface;
use ReflectionProperty;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ContainerDebugCommand extends Command {

	/**
	 * {@inheritdoc}
	 */
	protected function configure(): void {
		$this
			->setName('debug:container')
			->setDescription('lists the services registered in the container');
	}

	/**
	 * League keeps its definitions in a protected aggregate with no public getter,
	 * so they are read through reflection. The aggregate is iterable and each
	 * definition knows its id, concrete, shared flag and tags. League records the
	 * shared flag as a tag of its own, which the Shared column already covers.
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$definitions = (new ReflectionProperty(Container::class, 'definitions'))->getValue(BaseApplication::$container);

		$rows = [];
		/** @var DefinitionInterface $definition */
		foreach ($definitions as $definition) {
			$rows[$definition->getId()] = [
				$definition->getId(),
				$this->describeConcrete($definition->getConcrete()),
				$definition->isShared() ? 'yes' : 'no',
				implode(', ', array_diff($definition->getTags(), ['shared'])),
			];
		}
		ksort($rows);

		$table = new Table($output);
		$table->setHeaders(['Service', 'Class', 'Shared', 'Tags']);
		$table->addRows($rows);
		$table->render();

		return self::SUCCESS;
	}

	private function describeConcrete(mixed $concrete): string {
		return match (true) {
			$concrete instanceof Closure => 'closure',
			is_object($concrete) => $concrete::class,
			is_string($concrete) => $concrete,
			default => get_debug_type($concrete),
		};
	}
}
