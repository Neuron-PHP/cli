<?php

namespace Tests\Cli\Commands\Secrets;

use Neuron\Cli\Commands\Secrets\ShowCommand;
use Neuron\Data\Settings\SecretManager;
use PHPUnit\Framework\TestCase;
use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use Neuron\Cli\IO\TestInputReader;

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
	 * Test production environment confirmation prompt
	 */
	public function testExecuteProductionConfirmation(): void
	{
		// Create test secrets for production environment
		mkdir( $this->testConfigPath . '/secrets', 0755, true );
		$keyPath = $this->testConfigPath . '/secrets/production.key';
		$credentialsPath = $this->testConfigPath . '/secrets/production.yml.enc';

		$key = $this->secretManager->generateKey( $keyPath );

		$tempPlaintextPath = $this->testConfigPath . '/temp_plaintext.yml';
		$testData = "database:\n  password: production_secret";
		file_put_contents( $tempPlaintextPath, $testData );
		$this->secretManager->encrypt( $tempPlaintextPath, $credentialsPath, $keyPath );
		unlink( $tempPlaintextPath );

		// Test 1: User confirms - secrets should be shown
		$input = new Input( [
			'--config=' . $this->testConfigPath,
			'--env=production'
		] );
		$input->parse( $this->command );

		$output = new Output( false );

		// Set up test input reader to confirm
		$inputReader = new TestInputReader();
		$inputReader->addResponse( 'yes' );

		$this->command->setInput( $input );
		$this->command->setOutput( $output );
		$this->command->setInputReader( $inputReader );

		// Capture output
		ob_start();
		$result = $this->command->execute();
		$outputContent = ob_get_clean();

		// Should succeed and show secrets
		$this->assertEquals( 0, $result );
		$this->assertStringContainsString( 'You are about to display production secrets!', $outputContent );
		$this->assertStringContainsString( 'production_secret', $outputContent );

		// Test 2: User cancels - secrets should NOT be shown
		$input2 = new Input( [
			'--config=' . $this->testConfigPath,
			'--env=production'
		] );
		$input2->parse( $this->command );

		$output2 = new Output( false );

		// Set up test input reader to cancel
		$inputReader2 = new TestInputReader();
		$inputReader2->addResponse( 'no' );

		$this->command->setInput( $input2 );
		$this->command->setOutput( $output2 );
		$this->command->setInputReader( $inputReader2 );

		// Capture output
		ob_start();
		$result2 = $this->command->execute();
		$outputContent2 = ob_get_clean();

		// Should exit gracefully without showing secrets
		$this->assertEquals( 0, $result2 );
		$this->assertStringContainsString( 'Operation cancelled.', $outputContent2 );
		$this->assertStringNotContainsString( 'production_secret', $outputContent2 );

		// Test 3: Force flag should skip confirmation
		$input3 = new Input( [
			'--config=' . $this->testConfigPath,
			'--env=production',
			'--force'
		] );
		$input3->parse( $this->command );

		$output3 = new Output( false );

		$this->command->setInput( $input3 );
		$this->command->setOutput( $output3 );
		// No input reader needed - force skips confirmation

		// Capture output
		ob_start();
		$result3 = $this->command->execute();
		$outputContent3 = ob_get_clean();

		// Should succeed without confirmation prompt
		$this->assertEquals( 0, $result3 );
		$this->assertStringNotContainsString( 'You are about to display production secrets!', $outputContent3 );
		$this->assertStringContainsString( 'production_secret', $outputContent3 );
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