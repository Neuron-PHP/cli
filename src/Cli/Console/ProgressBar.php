<?php

namespace Neuron\Cli\Console;

/**
 * Progress bar for displaying task progress in the CLI.
 */
class ProgressBar
{
	private Output $output;
	private int $total;
	private int $current = 0;
	private int $width = 50;
	private float $startTime;
	private string $format = ' [%bar%] %percent%% %current%/%total% %elapsed% / %estimated%';
	
	/**
	 * @param Output $output
	 * @param int $total Total number of steps
	 */
	public function __construct( Output $output, int $total )
	{
		$this->output = $output;
		$this->total = $total;
		$this->startTime = microtime( true );
	}
	
	/**
	 * Start the progress bar
	 * 
	 * @return void
	 */
	public function start(): void
	{
		$this->current = 0;
		$this->startTime = microtime( true );
		$this->display();
	}
	
	/**
	 * Advance the progress bar by a number of steps
	 * 
	 * @param int $steps
	 * @return void
	 */
	public function advance( int $steps = 1 ): void
	{
		$this->setProgress( $this->current + $steps );
	}
	
	/**
	 * Set the current progress
	 * 
	 * @param int $current
	 * @return void
	 */
	public function setProgress( int $current ): void
	{
		$this->current = min( $current, $this->total );
		$this->display();
	}
	
	/**
	 * Finish the progress bar
	 * 
	 * @return void
	 */
	public function finish(): void
	{
		$this->setProgress( $this->total );
		$this->output->write( '' ); // New line after progress bar
	}
	
	/**
	 * Display the progress bar
	 * 
	 * @return void
	 */
	private function display(): void
	{
		$percent = $this->total > 0 ? ($this->current / $this->total) * 100 : 0;
		$filledWidth = (int) round( ($percent / 100) * $this->width );
		
		// Create the bar
		$bar = str_repeat( '█', $filledWidth );
		if( $filledWidth < $this->width )
		{
			$bar .= str_repeat( '░', $this->width - $filledWidth );
		}
		
		// Calculate times
		$elapsed = microtime( true ) - $this->startTime;
		$estimated = $this->current > 0 ? ($elapsed / $this->current) * $this->total : 0;
		$remaining = $estimated - $elapsed;
		
		// Format the output
		$output = strtr( $this->format, [
			'%bar%' => $bar,
			'%percent%' => sprintf( '%3d', (int) $percent ),
			'%current%' => $this->current,
			'%total%' => $this->total,
			'%elapsed%' => $this->formatTime( $elapsed ),
			'%estimated%' => $this->formatTime( $estimated ),
			'%remaining%' => $this->formatTime( $remaining ),
		] );
		
		// Clear line and write progress
		$this->output->clearLine();
		$this->output->write( $output, false );
	}
	
	/**
	 * Format time in seconds to human-readable format
	 * 
	 * @param float $seconds
	 * @return string
	 */
	private function formatTime( float $seconds ): string
	{
		if( $seconds < 1 )
		{
			return '< 1s';
		}
		
		if( $seconds < 60 )
		{
			return round( $seconds ) . 's';
		}
		
		$minutes = floor( $seconds / 60 );
		$seconds = $seconds % 60;
		
		if( $minutes < 60 )
		{
			return sprintf( '%dm %ds', $minutes, $seconds );
		}
		
		$hours = floor( $minutes / 60 );
		$minutes = $minutes % 60;
		
		return sprintf( '%dh %dm', $hours, $minutes );
	}
	
	/**
	 * Set the width of the progress bar
	 * 
	 * @param int $width
	 * @return void
	 */
	public function setWidth( int $width ): void
	{
		$this->width = $width;
	}
	
	/**
	 * Set the format of the progress bar
	 * 
	 * @param string $format
	 * @return void
	 */
	public function setFormat( string $format ): void
	{
		$this->format = $format;
	}
	
	/**
	 * Get the current progress
	 * 
	 * @return int
	 */
	public function getProgress(): int
	{
		return $this->current;
	}
	
	/**
	 * Get the total steps
	 * 
	 * @return int
	 */
	public function getTotal(): int
	{
		return $this->total;
	}
	
	/**
	 * Get the percentage complete
	 * 
	 * @return float
	 */
	public function getPercentage(): float
	{
		return $this->total > 0 ? ($this->current / $this->total) * 100 : 0;
	}
}