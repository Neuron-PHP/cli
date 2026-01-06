<?php

namespace Neuron\Cli\Commands\Secrets\Key;

use Neuron\Cli\Commands\Command;
use Neuron\Data\Settings\SecretManager;

/**
 * Generate encryption key command
 *
 * Generates a new encryption key for securing credentials.
 * Keys are cryptographically secure random values.
 *
 * Usage:
 *   neuron secrets:key:generate            # Generate master key
 *   neuron secrets:key:generate --env=production  # Generate production key
 *   neuron secrets:key:generate --force    # Overwrite existing key
 *
 * @package Neuron\Cli\Commands\Secrets\Key
 */
class GenerateCommand extends Command
{
	private SecretManager $secretManager;

	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'secrets:key:generate';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Generate a new encryption key for secrets';
	}

	/**
	 * @inheritDoc
	 */
	public function configure(): void
	{
		$this->addOption( 'env', 'e', true, 'Environment for the key (default: master key)' );
		$this->addOption( 'config', 'c', true, 'Config directory path (default: config)' );
		$this->addOption( 'force', 'f', false, 'Overwrite existing key file' );
		$this->addOption( 'show', 's', false, 'Display the generated key' );
		$this->addOption( 'verbose', 'v', false, 'Verbose output' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute(): int
	{
		$configPath = $this->input->getOption( 'config', 'config' );
		$env = $this->input->getOption( 'env' );
		$force = $this->input->hasOption( 'force' );
		$show = $this->input->hasOption( 'show' );

		// Determine key path based on environment
		if( $env )
		{
			$keyPath = $configPath . '/secrets/' . $env . '.key';
			$keyName = $env . ' environment key';

			// Ensure directory exists
			$dir = dirname( $keyPath );
			if( !is_dir( $dir ) )
			{
				if( !mkdir( $dir, 0755, true ) )
				{
					$this->output->error( "Failed to create directory: {$dir}" );
					return 1;
				}
			}
		}
		else
		{
			$keyPath = $configPath . '/master.key';
			$keyName = 'master key';

			// Ensure directory exists
			$dir = dirname( $keyPath );
			if( !is_dir( $dir ) )
			{
				if( !mkdir( $dir, 0755, true ) )
				{
					$this->output->error( "Failed to create directory: {$dir}" );
					return 1;
				}
			}
		}

		// Check if key already exists
		if( file_exists( $keyPath ) && !$force )
		{
			$this->output->error( "Key file already exists: {$keyPath}" );
			$this->output->info( "Use --force to overwrite the existing key." );
			$this->output->warning( "WARNING: Overwriting will make existing encrypted files unreadable!" );
			return 1;
		}

		// Warn about overwriting
		if( file_exists( $keyPath ) && $force )
		{
			$this->output->warning( "You are about to overwrite an existing key!" );
			$this->output->warning( "This will make any files encrypted with the old key unreadable." );

			if( !$this->confirm( "Are you absolutely sure you want to continue?" ) )
			{
				$this->output->info( "Operation cancelled." );
				return 0;
			}
		}

		// Create SecretManager and generate key
		$this->secretManager = new SecretManager();

		try
		{
			$key = $this->secretManager->generateKey( $keyPath, $force );

			$this->output->success( "Generated {$keyName} at: {$keyPath}" );

			// Show the key if requested
			if( $show )
			{
				$this->output->newLine();
				$this->output->section( "Generated Key" );
				$this->output->write( $key );
				$this->output->newLine();
				$this->output->warning( "This key is shown only once. Store it securely!" );
			}

			// Display instructions
			$this->output->newLine();
			$this->output->info( "Next steps:" );
			$this->output->write( "1. Add {$keyPath} to .gitignore (NEVER commit this file)" );
			$this->output->write( "2. Share this key securely with your team" );
			$this->output->write( "3. Use 'neuron secrets:edit" . ($env ? " --env={$env}" : "") . "' to add secrets" );

			// Environment variable alternative
			$envVar = 'NEURON_' . strtoupper(
				str_replace( ['/', '.', '-'], '_', basename( $keyPath, '.key' ) )
			) . '_KEY';
			$this->output->newLine();
			$this->output->info( "Alternative: Set the key as an environment variable:" );
			if( $show )
			{
				$this->output->write( "export {$envVar}={$key}" );
			}
			else
			{
				$this->output->write( "export {$envVar}=<KEY_FROM_{$keyPath}>" );
			}
		}
		catch( \Exception $e )
		{
			$this->output->error( "Error generating key: " . $e->getMessage() );

			if( $this->input->hasOption( 'verbose' ) )
			{
				$this->output->write( $e->getTraceAsString() );
			}

			return 1;
		}

		return 0;
	}
}