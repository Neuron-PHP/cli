<?php

namespace Neuron\Cli\Console;

/**
 * Handles output formatting and display for CLI commands.
 * Provides methods for colored output, tables, progress bars, etc.
 */
class Output
{
	/**
	 * @var bool Whether to use colored output
	 */
	private bool $useColors;
	
	/**
	 * @var int Verbosity level
	 */
	private int $verbosity = 1;
	
	/**
	 * ANSI color codes
	 */
	private const COLORS = [
		'black' => 30,
		'red' => 31,
		'green' => 32,
		'yellow' => 33,
		'blue' => 34,
		'magenta' => 35,
		'cyan' => 36,
		'white' => 37,
		'default' => 39,
	];
	
	/**
	 * ANSI background color codes
	 */
	private const BG_COLORS = [
		'black' => 40,
		'red' => 41,
		'green' => 42,
		'yellow' => 43,
		'blue' => 44,
		'magenta' => 45,
		'cyan' => 46,
		'white' => 47,
		'default' => 49,
	];
	
	/**
	 * Constructor
	 * 
	 * @param bool|null $useColors Whether to use colors (null = auto-detect)
	 */
	public function __construct( ?bool $useColors = null )
	{
		if( $useColors === null )
		{
			// Auto-detect color support
			$this->useColors = $this->supportsColors();
		}
		else
		{
			$this->useColors = $useColors;
		}
	}
	
	/**
	 * Check if the terminal supports colors
	 * 
	 * @return bool
	 */
	private function supportsColors(): bool
	{
		// Check if output is to a terminal
		if( !posix_isatty( STDOUT ) )
		{
			return false;
		}
		
		// Check TERM environment variable
		$term = getenv( 'TERM' );
		if( $term === false || $term === 'dumb' )
		{
			return false;
		}
		
		// Check for Windows
		if( DIRECTORY_SEPARATOR === '\\' )
		{
			return getenv( 'ANSICON' ) !== false || getenv( 'ConEmuANSI' ) === 'ON';
		}
		
		return true;
	}
	
	/**
	 * Write a line to output
	 * 
	 * @param string $message
	 * @param bool $newline Add newline at the end
	 * @return void
	 */
	public function write( string $message, bool $newline = true ): void
	{
		echo $message;
		
		if( $newline )
		{
			echo PHP_EOL;
		}
	}
	
	/**
	 * Write a line with a specific color
	 * 
	 * @param string $message
	 * @param string $color
	 * @param string|null $bgColor
	 * @return void
	 */
	public function writeln( string $message, string $color = 'default', ?string $bgColor = null ): void
	{
		if( $this->useColors && ($color !== 'default' || $bgColor !== null) )
		{
			$message = $this->colorize( $message, $color, $bgColor );
		}
		
		$this->write( $message );
	}
	
	/**
	 * Apply color to text
	 * 
	 * @param string $text
	 * @param string $color
	 * @param string|null $bgColor
	 * @return string
	 */
	private function colorize( string $text, string $color = 'default', ?string $bgColor = null ): string
	{
		$codes = [];
		
		if( isset( self::COLORS[$color] ) )
		{
			$codes[] = self::COLORS[$color];
		}
		
		if( $bgColor !== null && isset( self::BG_COLORS[$bgColor] ) )
		{
			$codes[] = self::BG_COLORS[$bgColor];
		}
		
		if( empty( $codes ) )
		{
			return $text;
		}
		
		return sprintf( "\033[%sm%s\033[0m", implode( ';', $codes ), $text );
	}
	
	/**
	 * Write an info message
	 * 
	 * @param string $message
	 * @return void
	 */
	public function info( string $message ): void
	{
		$this->writeln( $message, 'cyan' );
	}
	
	/**
	 * Write a success message
	 * 
	 * @param string $message
	 * @return void
	 */
	public function success( string $message ): void
	{
		$this->writeln( "✓ " . $message, 'green' );
	}
	
	/**
	 * Write a warning message
	 * 
	 * @param string $message
	 * @return void
	 */
	public function warning( string $message ): void
	{
		$this->writeln( "⚠ " . $message, 'yellow' );
	}
	
	/**
	 * Write an error message
	 * 
	 * @param string $message
	 * @return void
	 */
	public function error( string $message ): void
	{
		$this->writeln( "✗ " . $message, 'red' );
	}
	
	/**
	 * Write a comment
	 * 
	 * @param string $message
	 * @return void
	 */
	public function comment( string $message ): void
	{
		$this->writeln( $message, 'yellow' );
	}
	
	/**
	 * Write a question
	 * 
	 * @param string $message
	 * @return void
	 */
	public function question( string $message ): void
	{
		$this->writeln( $message, 'black', 'cyan' );
	}
	
	/**
	 * Display a table
	 * 
	 * @param array $headers
	 * @param array $rows
	 * @return void
	 */
	public function table( array $headers, array $rows ): void
	{
		// Calculate column widths
		$widths = [];
		foreach( $headers as $i => $header )
		{
			$widths[$i] = strlen( $header );
		}
		
		foreach( $rows as $row )
		{
			foreach( $row as $i => $cell )
			{
				$widths[$i] = max( $widths[$i] ?? 0, strlen( (string) $cell ) );
			}
		}
		
		// Draw header
		$this->drawTableRow( $headers, $widths, 'cyan' );
		$this->drawTableSeparator( $widths );
		
		// Draw rows
		foreach( $rows as $row )
		{
			$this->drawTableRow( $row, $widths );
		}
	}
	
	/**
	 * Draw a table row
	 * 
	 * @param array $row
	 * @param array $widths
	 * @param string $color
	 * @return void
	 */
	private function drawTableRow( array $row, array $widths, string $color = 'default' ): void
	{
		$line = '';
		foreach( $row as $i => $cell )
		{
			$line .= str_pad( (string) $cell, $widths[$i] + 2 );
		}
		
		$this->writeln( $line, $color );
	}
	
	/**
	 * Draw a table separator
	 * 
	 * @param array $widths
	 * @return void
	 */
	private function drawTableSeparator( array $widths ): void
	{
		$line = '';
		foreach( $widths as $width )
		{
			$line .= str_repeat( '-', $width + 2 );
		}
		
		$this->write( $line );
	}
	
	/**
	 * Create a progress bar
	 * 
	 * @param int $total Total steps
	 * @return ProgressBar
	 */
	public function createProgressBar( int $total ): ProgressBar
	{
		return new ProgressBar( $this, $total );
	}
	
	/**
	 * Clear the current line
	 * 
	 * @return void
	 */
	public function clearLine(): void
	{
		$this->write( "\r\033[K", false );
	}
	
	/**
	 * Set verbosity level
	 * 
	 * @param int $level
	 * @return void
	 */
	public function setVerbosity( int $level ): void
	{
		$this->verbosity = $level;
	}
	
	/**
	 * Get verbosity level
	 * 
	 * @return int
	 */
	public function getVerbosity(): int
	{
		return $this->verbosity;
	}
	
	/**
	 * Write debug output (only if verbosity is high enough)
	 * 
	 * @param string $message
	 * @param int $level Minimum verbosity level required
	 * @return void
	 */
	public function debug( string $message, int $level = 2 ): void
	{
		if( $this->verbosity >= $level )
		{
			$this->writeln( "[DEBUG] " . $message, 'magenta' );
		}
	}
	
	/**
	 * Write a blank line
	 * 
	 * @param int $count Number of blank lines
	 * @return void
	 */
	public function newLine( int $count = 1 ): void
	{
		for( $i = 0; $i < $count; $i++ )
		{
			$this->write( '' );
		}
	}
	
	/**
	 * Write a section header
	 * 
	 * @param string $title
	 * @return void
	 */
	public function section( string $title ): void
	{
		$this->newLine();
		$this->writeln( $title, 'yellow' );
		$this->writeln( str_repeat( '=', strlen( $title ) ), 'yellow' );
		$this->newLine();
	}
	
	/**
	 * Write a title
	 * 
	 * @param string $title
	 * @return void
	 */
	public function title( string $title ): void
	{
		$length = strlen( $title );
		$padding = str_repeat( ' ', $length + 4 );
		$border = str_repeat( '=', $length + 4 );
		
		$this->newLine();
//		$this->writeln( $border, 'cyan' );
		$this->writeln( "  {$title}  ", 'cyan' );
//		$this->writeln( $border, 'cyan' );
		$this->newLine();
	}
}
