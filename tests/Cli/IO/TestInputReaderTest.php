<?php

namespace Tests\Cli\IO;

use Neuron\Cli\IO\TestInputReader;
use PHPUnit\Framework\TestCase;

class TestInputReaderTest extends TestCase
{
	private TestInputReader $reader;

	protected function setUp(): void
	{
		$this->reader = new TestInputReader();
	}

	public function testPromptReturnsConfiguredResponse(): void
	{
		$this->reader->addResponse( 'test response' );

		$result = $this->reader->prompt( 'Enter something: ' );

		$this->assertEquals( 'test response', $result );
	}

	public function testPromptTracksHistory(): void
	{
		$this->reader->addResponse( 'response 1' );
		$this->reader->addResponse( 'response 2' );

		$this->reader->prompt( 'First prompt: ' );
		$this->reader->prompt( 'Second prompt: ' );

		$history = $this->reader->getPromptHistory();

		$this->assertCount( 2, $history );
		$this->assertEquals( 'First prompt: ', $history[0] );
		$this->assertEquals( 'Second prompt: ', $history[1] );
	}

	public function testPromptThrowsExceptionWhenNoResponseConfigured(): void
	{
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'No response configured for prompt #0' );

		$this->reader->prompt( 'Enter something: ' );
	}

	public function testAddResponsesAcceptsArray(): void
	{
		$this->reader->addResponses( ['response 1', 'response 2', 'response 3'] );

		$this->assertEquals( 'response 1', $this->reader->prompt( 'First: ' ) );
		$this->assertEquals( 'response 2', $this->reader->prompt( 'Second: ' ) );
		$this->assertEquals( 'response 3', $this->reader->prompt( 'Third: ' ) );
	}

	public function testConfirmReturnsTrue(): void
	{
		$this->reader->addResponse( 'yes' );

		$result = $this->reader->confirm( 'Are you sure?' );

		$this->assertTrue( $result );
	}

	public function testConfirmReturnsFalse(): void
	{
		$this->reader->addResponse( 'no' );

		$result = $this->reader->confirm( 'Are you sure?' );

		$this->assertFalse( $result );
	}

	public function testConfirmAcceptsVariousPositiveResponses(): void
	{
		foreach( ['y', 'yes', 'YES', 'Yes', 'true', 'TRUE', '1'] as $response ) {
			$reader = new TestInputReader();
			$reader->addResponse( $response );

			$this->assertTrue(
				$reader->confirm( 'Continue?' ),
				"Response '{$response}' should be treated as positive"
			);
		}
	}

	public function testConfirmUsesDefault(): void
	{
		$this->reader->addResponse( '' );

		$result = $this->reader->confirm( 'Continue?', true );

		$this->assertTrue( $result );
	}

	public function testSecretReturnsResponse(): void
	{
		$this->reader->addResponse( 'secret-password' );

		$result = $this->reader->secret( 'Enter password: ' );

		$this->assertEquals( 'secret-password', $result );
	}

	public function testChoiceReturnsOptionByIndex(): void
	{
		$options = ['option1', 'option2', 'option3'];
		$this->reader->addResponse( '1' );

		$result = $this->reader->choice( 'Select:', $options );

		$this->assertEquals( 'option2', $result );
	}

	public function testChoiceReturnsOptionByName(): void
	{
		$options = ['development', 'staging', 'production'];
		$this->reader->addResponse( 'staging' );

		$result = $this->reader->choice( 'Select environment:', $options );

		$this->assertEquals( 'staging', $result );
	}

	public function testChoiceUsesDefault(): void
	{
		$options = ['dev', 'staging', 'prod'];
		$this->reader->addResponse( '' );

		$result = $this->reader->choice( 'Select:', $options, 'dev' );

		$this->assertEquals( 'dev', $result );
	}

	public function testHasMoreResponsesReturnsTrueWhenResponsesRemain(): void
	{
		$this->reader->addResponses( ['response 1', 'response 2'] );

		$this->assertTrue( $this->reader->hasMoreResponses() );

		$this->reader->prompt( 'First: ' );

		$this->assertTrue( $this->reader->hasMoreResponses() );

		$this->reader->prompt( 'Second: ' );

		$this->assertFalse( $this->reader->hasMoreResponses() );
	}

	public function testGetRemainingResponseCount(): void
	{
		$this->reader->addResponses( ['r1', 'r2', 'r3'] );

		$this->assertEquals( 3, $this->reader->getRemainingResponseCount() );

		$this->reader->prompt( 'First: ' );

		$this->assertEquals( 2, $this->reader->getRemainingResponseCount() );

		$this->reader->prompt( 'Second: ' );

		$this->assertEquals( 1, $this->reader->getRemainingResponseCount() );
	}

	public function testResetClearsAllData(): void
	{
		$this->reader->addResponses( ['r1', 'r2'] );
		$this->reader->prompt( 'Test: ' );

		$this->reader->reset();

		$this->assertCount( 0, $this->reader->getPromptHistory() );
		$this->assertEquals( 0, $this->reader->getRemainingResponseCount() );
	}

	public function testFluentInterface(): void
	{
		$result = $this->reader
			->addResponse( 'first' )
			->addResponse( 'second' );

		$this->assertInstanceOf( TestInputReader::class, $result );
		$this->assertEquals( 2, $this->reader->getRemainingResponseCount() );
	}
}
