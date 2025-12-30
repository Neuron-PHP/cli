<?php

namespace Neuron\Cli\IO;

/**
 * Test double for IInputReader that returns pre-programmed responses.
 *
 * This allows CLI commands to be unit tested without requiring actual
 * user input or complex process isolation.
 *
 * Example usage:
 * ```php
 * $reader = new TestInputReader();
 * $reader->addResponse( 'yes' );
 * $reader->addResponse( 'John Doe' );
 * $reader->addResponse( '2' ); // Select option index 2
 *
 * $command->setInputReader( $reader );
 * $command->execute();
 *
 * // Verify prompts were shown
 * $prompts = $reader->getPromptHistory();
 * $this->assertCount( 3, $prompts );
 * ```
 *
 * @package Neuron\Cli\IO
 */
class TestInputReader implements IInputReader
{
	/**
	 * Queue of responses to return.
	 *
	 * @var array<string>
	 */
	private array $responses = [];

	/**
	 * Current position in the responses queue.
	 *
	 * @var int
	 */
	private int $currentIndex = 0;

	/**
	 * History of all prompts that were shown.
	 *
	 * @var array<string>
	 */
	private array $promptHistory = [];

	/**
	 * Add a response to the queue.
	 *
	 * Responses are returned in the order they are added.
	 *
	 * @param string $response The response to return when next prompted
	 * @return self For method chaining
	 */
	public function addResponse( string $response ): self
	{
		$this->responses[] = $response;
		return $this;
	}

	/**
	 * Add multiple responses at once.
	 *
	 * @param array<string> $responses Array of responses
	 * @return self For method chaining
	 */
	public function addResponses( array $responses ): self
	{
		$this->responses = array_merge( $this->responses, $responses );
		return $this;
	}

	/**
	 * Get the history of all prompts that were displayed.
	 *
	 * Useful for asserting that the correct prompts were shown to the user.
	 *
	 * @return array<string>
	 */
	public function getPromptHistory(): array
	{
		return $this->promptHistory;
	}

	/**
	 * Check if there are responses remaining in the queue.
	 *
	 * @return bool True if more responses are available, false otherwise
	 */
	public function hasMoreResponses(): bool
	{
		return isset( $this->responses[$this->currentIndex] );
	}

	/**
	 * Get the number of responses remaining in the queue.
	 *
	 * @return int Number of responses not yet consumed
	 */
	public function getRemainingResponseCount(): int
	{
		return count( $this->responses ) - $this->currentIndex;
	}

	/**
	 * Reset the input reader to initial state.
	 *
	 * Clears all responses and prompt history.
	 *
	 * @return void
	 */
	public function reset(): void
	{
		$this->responses = [];
		$this->currentIndex = 0;
		$this->promptHistory = [];
	}

	/**
	 * @inheritDoc
	 *
	 * @throws \RuntimeException If no response is configured for this prompt
	 */
	public function prompt( string $message ): string
	{
		$this->promptHistory[] = $message;

		if( !isset( $this->responses[$this->currentIndex] ) ) {
			throw new \RuntimeException(
				"No response configured for prompt #{$this->currentIndex}: {$message}\n" .
				"Available responses: " . count( $this->responses ) . "\n" .
				"Use addResponse() to add more responses before executing the command."
			);
		}

		return $this->responses[$this->currentIndex++];
	}

	/**
	 * @inheritDoc
	 */
	public function confirm( string $message, bool $default = false ): bool
	{
		$response = $this->prompt( $message );

		// If response is empty, use default
		if( $response === '' ) {
			return $default;
		}

		// Accept y, yes, true, 1 as positive (case-insensitive)
		return in_array( strtolower( $response ), ['y', 'yes', 'true', '1'], true );
	}

	/**
	 * @inheritDoc
	 */
	public function secret( string $message ): string
	{
		// For testing, secrets work the same as regular prompts
		// (no need to hide input in tests)
		return $this->prompt( $message );
	}

	/**
	 * @inheritDoc
	 */
	public function choice( string $message, array $options, ?string $default = null ): string
	{
		$response = $this->prompt( $message );

		// If response is empty and there's a default, use it
		if( $response === '' && $default !== null ) {
			return $default;
		}

		// Check if response is a numeric index
		if( is_numeric( $response ) ) {
			$index = (int)$response;
			if( isset( $options[$index] ) ) {
				return $options[$index];
			}
		}

		// Check if response matches an option exactly
		if( in_array( $response, $options, true ) ) {
			return $response;
		}

		// In tests, we don't re-prompt on invalid choice
		// Just return the response as-is and let the test verify behavior
		return $response;
	}
}
