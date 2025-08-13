<?php

namespace Tests\Cli\Commands;

use Neuron\Cli\Commands\Registry;
use PHPUnit\Framework\TestCase;

class RegistryTest extends TestCase
{
	private Registry $registry;
	
	protected function setUp(): void
	{
		$this->registry = new Registry();
	}
	
	public function testRegisterAndHas(): void
	{
		$this->assertFalse( $this->registry->has( 'test:command' ) );
		
		$this->registry->register( 'test:command', 'TestCommand' );
		
		$this->assertTrue( $this->registry->has( 'test:command' ) );
	}
	
	public function testGet(): void
	{
		$this->registry->register( 'test:command', 'TestCommand' );
		
		$this->assertEquals( 'TestCommand', $this->registry->get( 'test:command' ) );
	}
	
	public function testGetNonExistent(): void
	{
		$this->assertNull( $this->registry->get( 'non:existent' ) );
	}
	
	public function testAll(): void
	{
		$this->registry->register( 'test:one', 'TestOne' );
		$this->registry->register( 'test:two', 'TestTwo' );
		
		$all = $this->registry->all();
		
		$this->assertCount( 2, $all );
		$this->assertArrayHasKey( 'test:one', $all );
		$this->assertArrayHasKey( 'test:two', $all );
		$this->assertEquals( 'TestOne', $all['test:one'] );
		$this->assertEquals( 'TestTwo', $all['test:two'] );
	}
	
	public function testGetByNamespace(): void
	{
		$this->registry->register( 'cms:init', 'CmsInit' );
		$this->registry->register( 'cms:build', 'CmsBuild' );
		$this->registry->register( 'db:migrate', 'DbMigrate' );
		
		$cmsCommands = $this->registry->getByNamespace( 'cms' );
		
		$this->assertCount( 2, $cmsCommands );
		$this->assertArrayHasKey( 'cms:init', $cmsCommands );
		$this->assertArrayHasKey( 'cms:build', $cmsCommands );
		$this->assertArrayNotHasKey( 'db:migrate', $cmsCommands );
	}
	
	public function testGetNamespaces(): void
	{
		$this->registry->register( 'cms:init', 'CmsInit' );
		$this->registry->register( 'cms:build', 'CmsBuild' );
		$this->registry->register( 'db:migrate', 'DbMigrate' );
		$this->registry->register( 'help', 'Help' );
		
		$namespaces = $this->registry->getNamespaces();
		
		$this->assertCount( 3, $namespaces );
		$this->assertContains( 'cms', $namespaces );
		$this->assertContains( 'db', $namespaces );
		$this->assertContains( 'global', $namespaces );
	}
	
	public function testOverwriteExistingCommand(): void
	{
		$this->registry->register( 'test:command', 'OldCommand' );
		$this->registry->register( 'test:command', 'NewCommand' );
		
		$this->assertEquals( 'NewCommand', $this->registry->get( 'test:command' ) );
	}
	
	public function testEmptyRegistry(): void
	{
		$this->assertEmpty( $this->registry->all() );
		$this->assertEmpty( $this->registry->getNamespaces() );
	}
}