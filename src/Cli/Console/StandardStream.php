<?php

namespace Neuron\Cli\Console;

/**
 * Standard stream implementation using real STDIN/STDOUT.
 *
 * This is the production implementation that wraps PHP's standard input/output streams.
 * Uses STDIN for reading and STDOUT for writing, providing real terminal interaction.
 */
class StandardStream implements StreamInterface
{
	/**
	 * @param resource $inputStream Input stream resource (default: STDIN)
	 * @param resource $outputStream Output stream resource (default: STDOUT)
	 */
	public function __construct(
		private mixed $inputStream = STDIN,
		private mixed $outputStream = STDOUT
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function read(): string|false
	{
		return fgets( $this->inputStream );
	}

	/**
	 * @inheritDoc
	 */
	public function write( string $data ): void
	{
		fwrite( $this->outputStream, $data );
	}

	/**
	 * @inheritDoc
	 */
	public function isInteractive(): bool
	{
		return posix_isatty( $this->inputStream );
	}
}
