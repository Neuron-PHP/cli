<?php

namespace Neuron\Cli\Console;

/**
 * Test stream implementation for unit testing.
 *
 * Provides an in-memory stream implementation that can be pre-programmed with
 * inputs and captures all outputs. Useful for testing interactive CLI operations
 * without requiring actual terminal interaction.
 */
class TestStream implements StreamInterface
{
	/**
	 * @var array<int, string> Pre-programmed inputs to return
	 */
	private array $inputs = [];

	/**
	 * @var array<int, string> Captured outputs
	 */
	private array $outputs = [];

	/**
	 * @var int Current position in inputs array
	 */
	private int $position = 0;

	/**
	 * @var bool Whether this stream is interactive
	 */
	private bool $interactive = true;

	/**
	 * Set pre-programmed inputs for testing
	 *
	 * @param array<int, string> $inputs Array of input strings to return
	 * @return void
	 */
	public function setInputs( array $inputs ): void
	{
		$this->inputs = $inputs;
		$this->position = 0;
	}

	/**
	 * Set whether this stream should report as interactive
	 *
	 * @param bool $interactive
	 * @return void
	 */
	public function setInteractive( bool $interactive ): void
	{
		$this->interactive = $interactive;
	}

	/**
	 * @inheritDoc
	 */
	public function read(): string|false
	{
		if( $this->position >= count( $this->inputs ) )
		{
			return false;
		}

		return $this->inputs[$this->position++];
	}

	/**
	 * @inheritDoc
	 */
	public function write( string $data ): void
	{
		$this->outputs[] = $data;
	}

	/**
	 * @inheritDoc
	 */
	public function isInteractive(): bool
	{
		return $this->interactive;
	}

	/**
	 * Get all captured outputs
	 *
	 * @return array<int, string>
	 */
	public function getOutputs(): array
	{
		return $this->outputs;
	}

	/**
	 * Get all outputs as a single string
	 *
	 * @return string
	 */
	public function getOutput(): string
	{
		return implode( '', $this->outputs );
	}

	/**
	 * Clear captured outputs
	 *
	 * @return void
	 */
	public function clearOutputs(): void
	{
		$this->outputs = [];
	}

	/**
	 * Reset the stream to initial state
	 *
	 * @return void
	 */
	public function reset(): void
	{
		$this->inputs = [];
		$this->outputs = [];
		$this->position = 0;
		$this->interactive = true;
	}
}
