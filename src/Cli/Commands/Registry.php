<?php

namespace Neuron\Cli\Commands;

/**
 * Registry for managing CLI commands.
 * Stores command name to class mappings and provides
 * methods for registration and retrieval.
 */
class Registry
{
	/**
	 * @var array<string, string> Command name to class mappings
	 */
	private array $commands = [];
	
	/**
	 * Register a command
	 * 
	 * @param string $name Command name (e.g., 'cms:init')
	 * @param string $class Fully qualified class name
	 * @return void
	 */
	public function register( string $name, string $class ): void
	{
		$this->commands[$name] = $class;
	}
	
	/**
	 * Check if a command is registered
	 * 
	 * @param string $name
	 * @return bool
	 */
	public function has( string $name ): bool
	{
		return isset( $this->commands[$name] );
	}
	
	/**
	 * Get a command class by name
	 * 
	 * @param string $name
	 * @return string|null Command class name or null if not found
	 */
	public function get( string $name ): ?string
	{
		return $this->commands[$name] ?? null;
	}
	
	/**
	 * Get all registered commands
	 * 
	 * @return array<string, string>
	 */
	public function all(): array
	{
		return $this->commands;
	}
	
	/**
	 * Remove a command from the registry
	 * 
	 * @param string $name
	 * @return bool True if command was removed, false if it didn't exist
	 */
	public function remove( string $name ): bool
	{
		if( $this->has( $name ) )
		{
			unset( $this->commands[$name] );
			return true;
		}
		
		return false;
	}
	
	/**
	 * Clear all registered commands
	 * 
	 * @return void
	 */
	public function clear(): void
	{
		$this->commands = [];
	}
	
	/**
	 * Get the count of registered commands
	 * 
	 * @return int
	 */
	public function count(): int
	{
		return count( $this->commands );
	}
	
	/**
	 * Find commands by pattern
	 * 
	 * @param string $pattern Pattern to match (supports wildcards with *)
	 * @return array<string, string> Matching commands
	 */
	public function find( string $pattern ): array
	{
		// Convert pattern to regex
		$regex = '/^' . str_replace( '\*', '.*', preg_quote( $pattern, '/' ) ) . '$/';
		
		$matches = [];
		foreach( $this->commands as $name => $class )
		{
			if( preg_match( $regex, $name ) )
			{
				$matches[$name] = $class;
			}
		}
		
		return $matches;
	}
	
	/**
	 * Get all command namespaces (parts before the colon)
	 * 
	 * @return array<string>
	 */
	public function getNamespaces(): array
	{
		$namespaces = [];
		
		foreach( array_keys( $this->commands ) as $name )
		{
			if( strpos( $name, ':' ) !== false )
			{
				list( $namespace, ) = explode( ':', $name, 2 );
				$namespaces[$namespace] = true;
			}
			else
			{
				// Commands without namespace are considered 'global'
				$namespaces['global'] = true;
			}
		}
		
		return array_keys( $namespaces );
	}
	
	/**
	 * Get commands in a specific namespace
	 * 
	 * @param string $namespace
	 * @return array<string, string>
	 */
	public function getByNamespace( string $namespace ): array
	{
		return $this->find( $namespace . ':*' );
	}
}