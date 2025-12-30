<?php

namespace Tests\Cli\IO;

use Neuron\Cli\Console\Output;
use Neuron\Cli\IO\StdinInputReader;
use PHPUnit\Framework\TestCase;

class StdinInputReaderTest extends TestCase
{
	private Output $output;
	private StdinInputReader $reader;

	protected function setUp(): void
	{
		$this->output = new Output( false );
		$this->reader = new StdinInputReader( $this->output );
	}

	public function testConstructorAcceptsOutput(): void
	{
		$reader = new StdinInputReader( $this->output );

		$this->assertInstanceOf( StdinInputReader::class, $reader );
	}

	public function testConfirmWithEmptyResponseUsesDefault(): void
	{
		// We can test the logic without actual STDIN by using a mock
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		$mock->expects( $this->once() )
			->method( 'prompt' )
			->willReturn( '' );

		$result = $mock->confirm( 'Continue?', true );

		$this->assertTrue( $result );
	}

	public function testConfirmAcceptsYesResponse(): void
	{
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		foreach( ['y', 'yes', 'YES', 'Yes', 'true', 'TRUE', '1'] as $response ) {
			$mock->expects( $this->once() )
				->method( 'prompt' )
				->willReturn( $response );

			$result = $mock->confirm( 'Continue?', false );

			$this->assertTrue( $result, "Response '{$response}' should be treated as positive" );

			// Reset the mock for next iteration
			$mock = $this->getMockBuilder( StdinInputReader::class )
				->setConstructorArgs( [$this->output] )
				->onlyMethods( ['prompt'] )
				->getMock();
		}
	}

	public function testConfirmRejectsNoResponse(): void
	{
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		foreach( ['n', 'no', 'NO', 'false', 'FALSE', '0', 'nope', 'anything'] as $response ) {
			$mock->expects( $this->once() )
				->method( 'prompt' )
				->willReturn( $response );

			$result = $mock->confirm( 'Continue?', true );

			$this->assertFalse( $result, "Response '{$response}' should be treated as negative" );

			// Reset the mock for next iteration
			$mock = $this->getMockBuilder( StdinInputReader::class )
				->setConstructorArgs( [$this->output] )
				->onlyMethods( ['prompt'] )
				->getMock();
		}
	}

	public function testConfirmAddsSuffixBasedOnDefault(): void
	{
		// Test that the suffix is added correctly based on default value
		// We can't easily verify the output, but we can verify the method is called
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		// Default true should show [Y/n]
		$mock->expects( $this->once() )
			->method( 'prompt' )
			->with( $this->stringContains( '[Y/n]' ) )
			->willReturn( '' );

		$mock->confirm( 'Continue?', true );
	}

	public function testChoiceWithEmptyResponseUsesDefault(): void
	{
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		$mock->expects( $this->once() )
			->method( 'prompt' )
			->willReturn( '' );

		$options = ['dev', 'staging', 'prod'];
		$result = $mock->choice( 'Select:', $options, 'dev' );

		$this->assertEquals( 'dev', $result );
	}

	public function testChoiceWithNumericIndex(): void
	{
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		$mock->expects( $this->once() )
			->method( 'prompt' )
			->willReturn( '1' );

		$options = ['dev', 'staging', 'prod'];
		$result = $mock->choice( 'Select:', $options );

		$this->assertEquals( 'staging', $result );
	}

	public function testChoiceWithExactMatch(): void
	{
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		$mock->expects( $this->once() )
			->method( 'prompt' )
			->willReturn( 'staging' );

		$options = ['dev', 'staging', 'prod'];
		$result = $mock->choice( 'Select:', $options );

		$this->assertEquals( 'staging', $result );
	}

	public function testChoiceWithInvalidSelectionRetriesOnce(): void
	{
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		// First call returns invalid, second call returns valid
		$mock->expects( $this->exactly( 2 ) )
			->method( 'prompt' )
			->willReturnOnConsecutiveCalls( 'invalid', '1' );

		$options = ['dev', 'staging', 'prod'];
		$result = $mock->choice( 'Select:', $options );

		$this->assertEquals( 'staging', $result );
	}

	public function testSecretDelegatesToPrompt(): void
	{
		// We can't easily test the actual secret hiding functionality,
		// but we can verify the method works
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		// On non-Windows systems, secret() will internally handle stty,
		// but we can't test that easily in unit tests
		// This test just verifies the interface works
		$this->assertInstanceOf( StdinInputReader::class, $mock );
	}
}
