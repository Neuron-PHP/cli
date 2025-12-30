<?php

namespace Tests\Cli\Console;

use Neuron\Cli\Console\Output;
use PHPUnit\Framework\TestCase;

class OutputTest extends TestCase
{
	private Output $output;
	
	protected function setUp(): void
	{
		// Create output without colors for testing
		$this->output = new Output( false );
	}
	
	public function testVerbosityLevels(): void
	{
		$this->assertEquals( 1, $this->output->getVerbosity() );
		
		$this->output->setVerbosity( 3 );
		
		$this->assertEquals( 3, $this->output->getVerbosity() );
	}
	
	public function testWriteCapture(): void
	{
		// Capture output
		ob_start();
		$this->output->write( 'Test message' );
		$captured = ob_get_clean();
		
		$this->assertEquals( "Test message" . PHP_EOL, $captured );
	}
	
	public function testWriteWithoutNewline(): void
	{
		ob_start();
		$this->output->write( 'Test', false );
		$captured = ob_get_clean();
		
		$this->assertEquals( 'Test', $captured );
	}
	
	public function testInfoMessage(): void
	{
		ob_start();
		$this->output->info( 'Info message' );
		$captured = ob_get_clean();
		
		// Without colors, should just be the message
		$this->assertEquals( "Info message" . PHP_EOL, $captured );
	}
	
	public function testSuccessMessage(): void
	{
		ob_start();
		$this->output->success( 'Success' );
		$captured = ob_get_clean();
		
		$this->assertStringContainsString( '✓ Success', $captured );
	}
	
	public function testWarningMessage(): void
	{
		ob_start();
		$this->output->warning( 'Warning' );
		$captured = ob_get_clean();
		
		$this->assertStringContainsString( '⚠ Warning', $captured );
	}
	
	public function testErrorMessage(): void
	{
		ob_start();
		$this->output->error( 'Error' );
		$captured = ob_get_clean();
		
		$this->assertStringContainsString( '✗ Error', $captured );
	}
	
	public function testNewLine(): void
	{
		ob_start();
		$this->output->newLine( 3 );
		$captured = ob_get_clean();
		
		$this->assertEquals( PHP_EOL . PHP_EOL . PHP_EOL, $captured );
	}
	
	public function testDebugWithVerbosity(): void
	{
		// Default verbosity is 1, debug requires 2
		ob_start();
		$this->output->debug( 'Debug message' );
		$captured = ob_get_clean();
		
		$this->assertEquals( '', $captured );
		
		// Increase verbosity
		$this->output->setVerbosity( 2 );
		
		ob_start();
		$this->output->debug( 'Debug message' );
		$captured = ob_get_clean();
		
		$this->assertStringContainsString( '[DEBUG] Debug message', $captured );
	}
	
	public function testTableOutput(): void
	{
		ob_start();
		$this->output->table( 
			['Name', 'Version'],
			[
				['cli', '1.0.0'],
				['core', '0.7.0']
			]
		);
		$captured = ob_get_clean();
		
		// Check that headers are present
		$this->assertStringContainsString( 'Name', $captured );
		$this->assertStringContainsString( 'Version', $captured );
		
		// Check that data is present
		$this->assertStringContainsString( 'cli', $captured );
		$this->assertStringContainsString( '1.0.0', $captured );
		$this->assertStringContainsString( 'core', $captured );
		$this->assertStringContainsString( '0.7.0', $captured );
	}
	
	public function testSection(): void
	{
		ob_start();
		$this->output->section( 'Test Section' );
		$captured = ob_get_clean();
		
		$lines = explode( PHP_EOL, $captured );
		
		// Should have blank line, title, separator, blank line
		$this->assertCount( 5, $lines ); // 4 lines + empty string at end
		$this->assertEquals( '', $lines[0] );
		$this->assertEquals( 'Test Section', $lines[1] );
		$this->assertEquals( '============', $lines[2] );
		$this->assertEquals( '', $lines[3] );
	}
	
	public function testTitle(): void
	{
		ob_start();
		$this->output->title( 'Test' );
		$captured = ob_get_clean();

		// Should have the title with spaces
		$this->assertStringContainsString( '  Test  ', $captured );
		// Should have newlines before and after
		$this->assertStringStartsWith( PHP_EOL, $captured );
		$this->assertStringEndsWith( PHP_EOL . PHP_EOL, $captured );
	}

	public function testComment(): void
	{
		ob_start();
		$this->output->comment( 'Comment message' );
		$captured = ob_get_clean();

		$this->assertStringContainsString( 'Comment message', $captured );
	}

	public function testQuestion(): void
	{
		ob_start();
		$this->output->question( 'Question message' );
		$captured = ob_get_clean();

		$this->assertStringContainsString( 'Question message', $captured );
	}

	public function testCreateProgressBar(): void
	{
		$progressBar = $this->output->createProgressBar( 100 );

		$this->assertInstanceOf( \Neuron\Cli\Console\ProgressBar::class, $progressBar );
	}

	public function testClearLine(): void
	{
		ob_start();
		$this->output->clearLine();
		$captured = ob_get_clean();

		$this->assertEquals( "\r\033[K", $captured );
	}

	public function testConstructorWithColorsEnabled(): void
	{
		$output = new Output( true );

		ob_start();
		$output->info( 'Colored info' );
		$captured = ob_get_clean();

		// With colors enabled, should contain ANSI color codes
		$this->assertStringContainsString( "\033[", $captured );
	}

	public function testConstructorWithAutoDetect(): void
	{
		// Constructor with null should auto-detect color support
		$output = new Output( null );

		// Just verify it doesn't throw an exception
		$this->assertInstanceOf( Output::class, $output );
	}

	public function testWritelnWithColors(): void
	{
		$output = new Output( true );

		ob_start();
		$output->writeln( 'Red text', 'red' );
		$captured = ob_get_clean();

		// Should contain ANSI color codes
		$this->assertStringContainsString( "\033[", $captured );
		$this->assertStringContainsString( 'Red text', $captured );
	}

	public function testWritelnWithBackgroundColor(): void
	{
		$output = new Output( true );

		ob_start();
		$output->writeln( 'Text with background', 'white', 'blue' );
		$captured = ob_get_clean();

		// Should contain ANSI color codes for both foreground and background
		$this->assertStringContainsString( "\033[", $captured );
		$this->assertStringContainsString( 'Text with background', $captured );
	}

	public function testWritelnWithDefaultColorNoColorize(): void
	{
		$output = new Output( true );

		ob_start();
		$output->writeln( 'Default color text' );
		$captured = ob_get_clean();

		// With default color and no background, should not colorize
		$this->assertEquals( 'Default color text' . PHP_EOL, $captured );
	}

	public function testWritelnWithInvalidColor(): void
	{
		$output = new Output( true );

		ob_start();
		$output->writeln( 'Text', 'invalid_color' );
		$captured = ob_get_clean();

		// Invalid color should just output plain text
		$this->assertEquals( 'Text' . PHP_EOL, $captured );
	}
}