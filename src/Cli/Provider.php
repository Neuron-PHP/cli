<?php

namespace Neuron\Cli;

use Neuron\Cli\Commands\Registry;

/**
 * CLI provider for the CLI component.
 * Registers all CLI-related generator commands.
 */
class Provider
{
	/**
	 * Register CLI commands with the CLI registry
	 *
	 * @param Registry $registry CLI Registry instance
	 * @return void
	 */
	public static function register( Registry $registry ): void
	{
		// Register initializer generator
		$registry->register(
			'initializer:generate',
			'Neuron\\Cli\\Commands\\Generate\\InitializerCommand'
		);
	}
}
