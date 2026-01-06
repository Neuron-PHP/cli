<?php

namespace Neuron\Cli\Commands\Secrets;

use Neuron\Cli\Commands\Command;
use Neuron\Data\Settings\SecretManager;

/**
 * Edit encrypted secrets command
 *
 * Opens encrypted credentials in an editor for secure editing.
 * Automatically re-encrypts the file when the editor is closed.
 *
 * Usage:
 *   neuron secrets:edit                    # Edit default secrets
 *   neuron secrets:edit --env=production   # Edit production secrets
 *   neuron secrets:edit --editor="code --wait"  # Use VS Code
 *
 * @package Neuron\Cli\Commands\Secrets
 */
class EditCommand extends Command
{
	private SecretManager $secretManager;

	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'secrets:edit';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Edit encrypted secrets file';
	}

	/**
	 * @inheritDoc
	 */
	public function configure(): void
	{
		$this->addOption( 'env', 'e', true, 'Environment to edit (default: base secrets)' );
		$this->addOption( 'editor', null, true, 'Editor to use (default: vi)' );
		$this->addOption( 'config', 'c', true, 'Config directory path (default: config)' );
		$this->addOption( 'verbose', 'v', false, 'Verbose output' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute(): int
	{
		$configPath = $this->input->getOption( 'config', 'config' );
		$env = $this->input->getOption( 'env' );

		// Handle editor option - could be null, true (flag without value), or a string
		$editorOption = $this->input->getOption( 'editor' );
		if( is_string( $editorOption ) && $editorOption !== '' )
		{
			$editor = $editorOption;
		}
		else
		{
			$editor = getenv( 'EDITOR' ) ?: 'vi';
		}

		// Determine paths based on environment
		if( $env )
		{
			$credentialsPath = $configPath . '/secrets/' . $env . '.yml.enc';
			$keyPath = $configPath . '/secrets/' . $env . '.key';
			$this->output->info( "Editing {$env} environment secrets..." );
		}
		else
		{
			$credentialsPath = $configPath . '/secrets.yml.enc';
			$keyPath = $configPath . '/master.key';
			$this->output->info( "Editing base secrets..." );
		}

		// Create SecretManager
		$this->secretManager = new SecretManager();

		try
		{
			// Ensure key exists
			if( !file_exists( $keyPath ) )
			{
				$this->output->warning( "Key file not found at: {$keyPath}" );
				$this->output->info( "Generating new encryption key..." );

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

				$this->secretManager->generateKey( $keyPath );
				$this->output->success( "Generated new key at: {$keyPath}" );
				$this->output->warning( "IMPORTANT: Add {$keyPath} to .gitignore!" );
			}

			// Edit the secrets
			$result = $this->secretManager->edit( $credentialsPath, $keyPath, $editor );

			if( $result )
			{
				$this->output->success( "Secrets saved to: {$credentialsPath}" );

				// First time setup reminder - check for .gitignore in project root
				$projectRoot = dirname( $configPath );
				$gitignorePath = $projectRoot . '/.gitignore';
				if( !$env && !file_exists( $gitignorePath ) )
				{
					$this->output->newLine();
					$this->output->warning( "Remember to:" );
					$this->output->write( "1. Add {$keyPath} to .gitignore" );
					$this->output->write( "2. Commit {$credentialsPath} to version control" );
					$this->output->write( "3. Share {$keyPath} securely with your team" );
				}
			}
			else
			{
				$this->output->error( "Failed to save secrets" );
				return 1;
			}
		}
		catch( \Exception $e )
		{
			$this->output->error( "Error editing secrets: " . $e->getMessage() );

			if( $this->input->hasOption( 'verbose' ) )
			{
				$this->output->write( $e->getTraceAsString() );
			}

			return 1;
		}

		return 0;
	}
}