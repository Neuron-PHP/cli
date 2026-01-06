<?php

namespace Tests\Cli\Commands\Secrets;

use Neuron\Cli\Commands\Secrets\EditCommand;
use Neuron\Data\Settings\SecretManager;
use PHPUnit\Framework\TestCase;
use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;

class EditCommandTest extends TestCase
{
	private string $testConfigPath;
	private EditCommand $command;

	protected function setUp(): void
	{
		parent::setUp();

		// Create a temporary directory for testing
		$this->testConfigPath = sys_get_temp_dir() . '/test_secrets_' . uniqid();
		mkdir( $this->testConfigPath, 0755, true );

		$this->command = new EditCommand();
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
		$this->assertEquals( 'secrets:edit', $this->command->getName() );
	}

	/**
	 * Test that the command has a description
	 */
	public function testGetDescription(): void
	{
		$this->assertEquals( 'Edit encrypted secrets file', $this->command->getDescription() );
	}

	/**
	 * Test that the command configures options correctly
	 */
	public function testConfigure(): void
	{
		// Test that options are configured by checking if we can create input with them
		$input = new Input( [
			'--config=' . $this->testConfigPath,
			'--env=test',
			'--editor=vim'
		] );
		$input->parse( $this->command );

		// Options should be available
		$this->assertEquals( $this->testConfigPath, $input->getOption( 'config', 'config' ) );
		$this->assertEquals( 'test', $input->getOption( 'env' ) );
		$this->assertEquals( 'vim', $input->getOption( 'editor' ) );
	}

	/**
	 * Test editing base secrets when key exists
	 */
	public function testExecuteWithExistingKey(): void
	{
		// Create a test key file
		$keyPath = $this->testConfigPath . '/master.key';
		$secretManager = new SecretManager();
		$key = $secretManager->generateKey( $keyPath );

		// Create a test credentials file
		$credentialsPath = $this->testConfigPath . '/secrets.yml.enc';
		$tempPlaintextPath = $this->testConfigPath . '/temp_plaintext.yml';
		$testData = "test:\n  secret: value";
		file_put_contents( $tempPlaintextPath, $testData );
		$secretManager->encrypt( $tempPlaintextPath, $credentialsPath, $keyPath );
		unlink( $tempPlaintextPath );

		// Create input with options
		$input = new Input( [
			'--config=' . $this->testConfigPath,
			'--editor=echo' // Use echo as a no-op editor for testing
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

		// Key and credentials should still exist
		$this->assertFileExists( $keyPath );
		$this->assertFileExists( $credentialsPath );

		// Check output messages
		$this->assertStringContainsString( 'Editing base secrets...', $outputContent );
		$this->assertStringContainsString( "Secrets saved to: {$credentialsPath}", $outputContent );
	}

	/**
	 * Test editing environment-specific secrets
	 */
	public function testExecuteWithEnvironment(): void
	{
		// Create secrets directory
		mkdir( $this->testConfigPath . '/secrets', 0755, true );

		// Create input with options
		$input = new Input( [
			'--config=' . $this->testConfigPath,
			'--env=production',
			'--editor=echo' // Use echo as a no-op editor
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

		$keyPath = $this->testConfigPath . '/secrets/production.key';
		$credentialsPath = $this->testConfigPath . '/secrets/production.yml.enc';

		// Execute should succeed
		$this->assertEquals( 0, $result );

		// Key should be generated
		$this->assertFileExists( $keyPath );
		$this->assertFileExists( $credentialsPath );

		// Check output messages
		$this->assertStringContainsString( 'Editing production environment secrets...', $outputContent );
		$this->assertStringContainsString( "Key file not found at: {$keyPath}", $outputContent );
		$this->assertStringContainsString( "Secrets saved to: {$credentialsPath}", $outputContent );
	}

	/**
	 * Test that error is handled gracefully
	 */
	public function testExecuteWithError(): void
	{
		// Use a path that will cause an error (non-writable)
		$badPath = '/root/cannot_write_here';

		// Create input with options
		$input = new Input( [
			'--config=' . $badPath
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

		// Execute should fail
		$this->assertEquals( 1, $result );

		// Check error message
		$this->assertStringContainsString( 'Error editing secrets:', $outputContent );
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