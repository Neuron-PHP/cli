<?php

namespace Neuron\Cli\Commands\Secrets;

use Neuron\Cli\Commands\Command;
use Neuron\Data\Settings\SecretManager;
use Symfony\Component\Yaml\Yaml;

/**
 * Show decrypted secrets command
 *
 * Displays the decrypted contents of encrypted credentials files.
 * Requires confirmation in production environments.
 *
 * Usage:
 *   neuron secrets:show                    # Show default secrets
 *   neuron secrets:show --env=production   # Show production secrets
 *   neuron secrets:show --key=database     # Show only database section
 *
 * @package Neuron\Cli\Commands\Secrets
 */
class ShowCommand extends Command
{
	private SecretManager $secretManager;

	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'secrets:show';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Show decrypted secrets';
	}

	/**
	 * @inheritDoc
	 */
	public function configure(): void
	{
		$this->addOption( 'env', 'e', true, 'Environment to show (default: base secrets)' );
		$this->addOption( 'key', 'k', true, 'Show only specific key/section' );
		$this->addOption( 'config', 'c', true, 'Config directory path (default: config)' );
		$this->addOption( 'force', 'f', false, 'Skip confirmation prompt' );
		$this->addOption( 'verbose', 'v', false, 'Verbose output' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute(): int
	{
		$configPath = $this->input->getOption( 'config', 'config' );
		$env = $this->input->getOption( 'env' );
		$specificKey = $this->input->getOption( 'key' );
		$force = $this->input->hasOption( 'force' );

		// Security confirmation for production
		if( !$force && $env === 'production' )
		{
			$this->output->warning( "You are about to display production secrets!" );

			if( !$this->confirm( "Are you sure you want to continue?" ) )
			{
				$this->output->info( "Operation cancelled." );
				return 0;
			}
		}

		// Determine paths based on environment
		if( $env )
		{
			$credentialsPath = $configPath . '/secrets/' . $env . '.yml.enc';
			$keyPath = $configPath . '/secrets/' . $env . '.key';
			$title = ucfirst( $env ) . " Environment Secrets";
		}
		else
		{
			$credentialsPath = $configPath . '/secrets.yml.enc';
			$keyPath = $configPath . '/master.key';
			$title = "Base Secrets";
		}

		// Check if files exist
		if( !file_exists( $credentialsPath ) )
		{
			$this->output->error( "Secrets file not found: {$credentialsPath}" );
			$this->output->info( "Use 'neuron secrets:edit" . ($env ? " --env={$env}" : "") . "' to create it." );
			return 1;
		}

		if( !file_exists( $keyPath ) && !$this->checkEnvironmentKey( $keyPath ) )
		{
			$this->output->error( "Key file not found: {$keyPath}" );
			$this->output->info( "The key might be in an environment variable or you need to obtain it from your team." );
			return 1;
		}

		// Create SecretManager and decrypt
		// Note: SecretManager::show() internally calls readKey() which handles both file and environment variable sources
		$this->secretManager = new SecretManager();

		try
		{
			// The show() method will read the key from either the file or environment variable
			$decrypted = $this->secretManager->show( $credentialsPath, $keyPath );
			$data = Yaml::parse( $decrypted );

			// Filter to specific key if requested
			if( $specificKey )
			{
				if( isset( $data[$specificKey] ) )
				{
					$data = [$specificKey => $data[$specificKey]];
				}
				else
				{
					$this->output->error( "Key '{$specificKey}' not found in secrets" );
					return 1;
				}
			}

			// Display the secrets
			$this->output->section( $title );
			$this->output->newLine();

			// Format and display YAML
			$formatted = Yaml::dump( $data, 4, 2 );
			$this->output->write( $formatted );

			// Security reminder
			$this->output->newLine();
			$this->output->warning( "Remember: Never share or commit decrypted secrets!" );
		}
		catch( \Exception $e )
		{
			$this->output->error( "Error decrypting secrets: " . $e->getMessage() );

			if( $this->input->hasOption( 'verbose' ) )
			{
				$this->output->write( $e->getTraceAsString() );
			}

			return 1;
		}

		return 0;
	}

	/**
	 * Check if key exists in environment variable
	 *
	 * @param string $keyPath
	 * @return bool
	 */
	private function checkEnvironmentKey( string $keyPath ): bool
	{
		$envKey = 'NEURON_' . strtoupper(
			str_replace( ['/', '.', '-'], '_', basename( $keyPath, '.key' ) )
		) . '_KEY';

		return getenv( $envKey ) !== false;
	}
}