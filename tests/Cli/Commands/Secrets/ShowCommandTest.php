<?php

namespace Tests\Cli\Commands\Secrets;

use Neuron\Cli\Commands\Secrets\ShowCommand;
use Neuron\Data\Settings\SecretManager;
use PHPUnit\Framework\TestCase;
use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;

class ShowCommandTest extends TestCase
{
	private string $testConfigPath;
	private ShowCommand $command;
	private SecretManager $secretManager;

	protected function setUp(): void
	{
		parent::setUp();

		// Create a temporary directory for testing
		$this->testConfigPath = sys_get_temp_dir() . '/test_secrets_show_' . uniqid();
		mkdir( $this->testConfigPath, 0755, true );

		$this->command = new ShowCommand();
		$this->command->configure();
		$this->secretManager = new SecretManager();
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
		$this->assertEquals( 'secrets:show', $this->command->getName() );
	}

	/**
	 * Test that the command has a description
	 */
	public function testGetDescription(): void
	{
		$this->assertEquals( 'Show decrypted secrets', $this->command->getDescription() );
	}

	/**
	 * Test showing base secrets
	 */
	public function testExecuteShowsBaseSecrets(): void
	{
		// Create test secrets
		$keyPath = $this->testConfigPath . '/master.key';
		$credentialsPath = $this->testConfigPath . '/secrets.yml.enc';

		$key = $this->secretManager->generateKey( $keyPath );

		$tempPlaintextPath = $this->testConfigPath . '/temp_plaintext.yml';
		$testData = "database:\n  password: secret123\napi:\n  key: abc123";
		file_put_contents( $tempPlaintextPath, $testData );
		$this->secretManager->encrypt( $tempPlaintextPath, $credentialsPath, $keyPath );
		unlink( $tempPlaintextPath );

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

		// Check output contains secrets
		$this->assertStringContainsString( 'Base Secrets', $outputContent );
		$this->assertStringContainsString( 'database:', $outputContent );
		$this->assertStringContainsString( 'Remember: Never share or commit decrypted secrets!', $outputContent );
	}

	/**
	 * Test showing specific key
	 */
	public function testExecuteShowsSpecificKey(): void
	{
		// Create test secrets
		$keyPath = $this->testConfigPath . '/master.key';
		$credentialsPath = $this->testConfigPath . '/secrets.yml.enc';

		$key = $this->secretManager->generateKey( $keyPath );

		$tempPlaintextPath = $this->testConfigPath . '/temp_plaintext.yml';
		$testData = "database:\n  password: secret123\napi:\n  key: abc123";
		file_put_contents( $tempPlaintextPath, $testData );
		$this->secretManager->encrypt( $tempPlaintextPath, $credentialsPath, $keyPath );
		unlink( $tempPlaintextPath );

		// Create input with options
		$input = new Input( [
			'--config=' . $this->testConfigPath,
			'--key=database'
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

		// Check output contains only the database key
		$this->assertStringContainsString( 'database:', $outputContent );
		$this->assertStringNotContainsString( 'api:', $outputContent );
	}

	/**
	 * Test error when secrets file not found
	 */
	public function testExecuteErrorWhenSecretsFileNotFound(): void
	{
		// Create input with options (no secrets file exists)
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

		$credentialsPath = $this->testConfigPath . '/secrets.yml.enc';

		// Check error messages
		$this->assertStringContainsString( "Secrets file not found: {$credentialsPath}", $outputContent );
		$this->assertStringContainsString( "Use 'neuron secrets:edit' to create it.", $outputContent );
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