<?php

namespace Tests\Cli\Commands\Secrets\Key;

use Neuron\Cli\Commands\Secrets\Key\GenerateCommand;
use PHPUnit\Framework\TestCase;
use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use Neuron\Cli\Console\TestStream;

class GenerateCommandTest extends TestCase
{
	private string $testConfigPath;
	private GenerateCommand $command;

	protected function setUp(): void
	{
		parent::setUp();

		// Create a temporary directory for testing
		$this->testConfigPath = sys_get_temp_dir() . '/test_secrets_generate_' . uniqid();
		mkdir( $this->testConfigPath, 0755, true );

		$this->command = new GenerateCommand();
		$this->command->configure();
	}

	protected function tearDown(): void
	{
		parent::tearDown();

		// Clean up test files
		$this->removeDirectory( $this->testConfigPath );
	}

	/**
	 * Test that the command has the correct name
	 */
	public function testGetName(): void
	{
		$this->assertEquals( 'secrets:key:generate', $this->command->getName() );
	}

	/**
	 * Test that the command has a description
	 */
	public function testGetDescription(): void
	{
		$this->assertEquals( 'Generate a new encryption key for secrets', $this->command->getDescription() );
	}

	/**
	 * Test generating master key
	 */
	public function testExecuteGeneratesMasterKey(): void
	{
		// Create input with options
		$input = new Input( [ '--config=' . $this->testConfigPath ] );
		$input->parse( $this->command );

		// Create output
		$output = new Output( false );

		$this->command->setInput( $input );
		$this->command->setOutput( $output );

		// Capture output
		ob_start();
		$result = $this->command->execute();
		$outputContent = ob_get_clean();

		// Execute should succeed
		$this->assertEquals( 0, $result );

		// Key file should exist
		$keyPath = $this->testConfigPath . '/master.key';
		$this->assertFileExists( $keyPath );

		// Key should be 64 hex characters
		$key = file_get_contents( $keyPath );
		$this->assertEquals( 64, strlen( $key ) );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/i', $key );

		// Check output contains success message
		$this->assertStringContainsString( "Generated master key at: {$keyPath}", $outputContent );
		$this->assertStringContainsString( "Next steps:", $outputContent );
	}

	/**
	 * Test generating environment-specific key
	 */
	public function testExecuteGeneratesEnvironmentKey(): void
	{
		// Create input with options
		$input = new Input( [
			'--config=' . $this->testConfigPath,
			'--env=production'
		] );
		$input->parse( $this->command );

		// Create output
		$output = new Output( false );

		$this->command->setInput( $input );
		$this->command->setOutput( $output );

		// Capture output
		ob_start();
		$result = $this->command->execute();
		$outputContent = ob_get_clean();

		// Execute should succeed
		$this->assertEquals( 0, $result );

		// Directory and key file should exist
		$this->assertDirectoryExists( $this->testConfigPath . '/environments' );
		$keyPath = $this->testConfigPath . '/environments/production.key';
		$this->assertFileExists( $keyPath );

		// Check output contains success message
		$this->assertStringContainsString( "Generated production environment key at: {$keyPath}", $outputContent );
	}

	/**
	 * Test that config directory is created for master key when missing
	 */
	public function testExecuteCreatesMasterKeyDirectory(): void
	{
		// Use a non-existent config path
		$nonExistentPath = sys_get_temp_dir() . '/test_config_' . uniqid() . '/config';

		// Create input with options pointing to non-existent path
		$input = new Input( [ '--config=' . $nonExistentPath ] );
		$input->parse( $this->command );

		// Create output
		$output = new Output( false );

		$this->command->setInput( $input );
		$this->command->setOutput( $output );

		// Capture output
		ob_start();
		$result = $this->command->execute();
		$outputContent = ob_get_clean();

		// Execute should succeed
		$this->assertEquals( 0, $result );

		// Directory should be created
		$this->assertDirectoryExists( $nonExistentPath );

		// Key file should exist
		$keyPath = $nonExistentPath . '/master.key';
		$this->assertFileExists( $keyPath );

		// Check output contains success message
		$this->assertStringContainsString( "Generated master key at: {$keyPath}", $outputContent );

		// Clean up
		unlink( $keyPath );
		rmdir( $nonExistentPath );
		rmdir( dirname( $nonExistentPath ) );
	}

	/**
	 * Test that key is only shown when --show flag is used
	 */
	public function testKeyOnlyShownWithShowFlag(): void
	{
		// Test WITHOUT --show flag (default)
		$input = new Input( [ '--config=' . $this->testConfigPath ] );
		$input->parse( $this->command );

		$output = new Output( false );

		$this->command->setInput( $input );
		$this->command->setOutput( $output );

		ob_start();
		$result = $this->command->execute();
		$outputContentNoShow = ob_get_clean();

		$this->assertEquals( 0, $result );

		$keyPath = $this->testConfigPath . '/master.key';
		$this->assertFileExists( $keyPath );

		// Read the actual key
		$actualKey = file_get_contents( $keyPath );

		// Key should NOT be in the output (except in the placeholder)
		$this->assertStringNotContainsString( "export NEURON_MASTER_KEY={$actualKey}", $outputContentNoShow );
		$this->assertStringContainsString( "export NEURON_MASTER_KEY=<KEY_FROM_{$keyPath}>", $outputContentNoShow );
		$this->assertStringNotContainsString( "Generated Key", $outputContentNoShow );

		// Clean up before second test
		unlink( $keyPath );

		// Test WITH --show flag
		$input2 = new Input( [
			'--config=' . $this->testConfigPath,
			'--show'
		] );
		$input2->parse( $this->command );

		$output2 = new Output( false );

		$this->command->setInput( $input2 );
		$this->command->setOutput( $output2 );

		ob_start();
		$result2 = $this->command->execute();
		$outputContentWithShow = ob_get_clean();

		$this->assertEquals( 0, $result2 );

		// Read the new key
		$actualKey2 = file_get_contents( $keyPath );

		// Key SHOULD be in the output
		$this->assertStringContainsString( "export NEURON_MASTER_KEY={$actualKey2}", $outputContentWithShow );
		$this->assertStringNotContainsString( "<KEY_FROM_", $outputContentWithShow );
		$this->assertStringContainsString( "Generated Key", $outputContentWithShow );
		$this->assertStringContainsString( $actualKey2, $outputContentWithShow );
		$this->assertStringContainsString( "This key is shown only once", $outputContentWithShow );
	}

	/**
	 * Test error when key already exists without force
	 */
	public function testExecuteErrorWhenKeyExistsWithoutForce(): void
	{
		// Create an existing key file
		$keyPath = $this->testConfigPath . '/master.key';
		file_put_contents( $keyPath, 'existing_key' );

		// Create input with options
		$input = new Input( [ '--config=' . $this->testConfigPath ] );
		$input->parse( $this->command );

		// Create output
		$output = new Output( false );

		$this->command->setInput( $input );
		$this->command->setOutput( $output );

		// Capture output
		ob_start();
		$result = $this->command->execute();
		$outputContent = ob_get_clean();

		// Execute should fail
		$this->assertEquals( 1, $result );

		// Original key should still exist
		$this->assertEquals( 'existing_key', file_get_contents( $keyPath ) );

		// Check error messages
		$this->assertStringContainsString( "Key file already exists: {$keyPath}", $outputContent );
		$this->assertStringContainsString( 'Use --force to overwrite the existing key.', $outputContent );
		$this->assertStringContainsString( 'WARNING: Overwriting will make existing encrypted files unreadable!', $outputContent );
	}

	/**
	 * Helper to remove directory recursively
	 */
	private function removeDirectory( string $dir ): void
	{
		if( !is_dir( $dir ) )
		{
			return;
		}

		$files = array_diff( scandir( $dir ), ['.', '..'] );
		foreach( $files as $file )
		{
			$path = $dir . '/' . $file;
			is_dir( $path ) ? $this->removeDirectory( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}
}