<?php

namespace Tests\Cli\Commands\Core;

use Neuron\Cli\Commands\Core\VersionCommand;
use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use PHPUnit\Framework\TestCase;

class VersionCommandTest extends TestCase
{
	private VersionCommand $command;
	private Output $output;

	protected function setUp(): void
	{
		$this->command = new VersionCommand();
		$this->command->configure();
		$this->output = new Output( false );
		$this->command->setOutput( $this->output );
	}

	public function testGetName(): void
	{
		$this->assertEquals( 'version', $this->command->getName() );
	}

	public function testGetDescription(): void
	{
		$this->assertEquals( 'Display version information', $this->command->getDescription() );
	}

	public function testConfigure(): void
	{
		$options = $this->command->getOptions();

		$this->assertArrayHasKey( 'verbose', $options );
		$this->assertEquals( 'v', $options['verbose']['shortcut'] );

		$this->assertArrayHasKey( 'component', $options );
		$this->assertEquals( 'c', $options['component']['shortcut'] );
		$this->assertTrue( $options['component']['hasValue'] );
	}

	public function testExecuteShowsMainVersion(): void
	{
		$input = new Input( [] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$exitCode = $this->command->execute();
		$output = ob_get_clean();

		$this->assertEquals( 0, $exitCode );
		$this->assertStringContainsString( 'Neuron CLI', $output );
		$this->assertStringContainsString( 'Version:', $output );
		$this->assertStringContainsString( 'PHP Version:', $output );
		$this->assertStringContainsString( '--verbose', $output );
	}

	public function testExecuteWithVerboseShowsAllVersions(): void
	{
		$input = new Input( ['--verbose'] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$exitCode = $this->command->execute();
		$output = ob_get_clean();

		$this->assertEquals( 0, $exitCode );
		$this->assertStringContainsString( 'Neuron Component Versions', $output );
		// May show "No Neuron components found" if no vendor dir exists
		$this->assertTrue(
			str_contains( $output, 'Component' ) ||
			str_contains( $output, 'No Neuron components found' )
		);
	}

	public function testExecuteWithComponentOption(): void
	{
		$input = new Input( ['--component=cli'] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$exitCode = $this->command->execute();
		$output = ob_get_clean();

		$this->assertEquals( 0, $exitCode );
		// Will either show component info or "not found" error
		$this->assertTrue(
			str_contains( $output, 'Component:' ) ||
			str_contains( $output, 'not found' )
		);
	}

	public function testShowsPhpVersion(): void
	{
		$input = new Input( [] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$this->command->execute();
		$output = ob_get_clean();

		$this->assertStringContainsString( PHP_VERSION, $output );
	}

	public function testComponentWithPrefix(): void
	{
		// Test component name with neuron-php/ prefix
		$input = new Input( ['--component=neuron-php/cli'] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$exitCode = $this->command->execute();
		$output = ob_get_clean();

		$this->assertEquals( 0, $exitCode );
		// Will show either component info or error
		$this->assertNotEmpty( $output );
	}

	public function testComponentNotFound(): void
	{
		// Test with a component that doesn't exist
		$input = new Input( ['--component=nonexistent-component'] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$exitCode = $this->command->execute();
		$output = ob_get_clean();

		$this->assertEquals( 0, $exitCode );
		$this->assertStringContainsString( 'not found', $output );
		$this->assertStringContainsString( 'component:list', $output );
	}

	public function testMainVersionOutputFormat(): void
	{
		$input = new Input( [] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$this->command->execute();
		$output = ob_get_clean();

		// Check that output contains key elements
		$this->assertStringContainsString( 'Version:', $output );
		$this->assertStringContainsString( 'PHP Version:', $output );
		// Version number should follow the label
		$this->assertMatchesRegularExpression( '/Version:\s+[\d.]+/', $output );
	}

	// Tests with MemoryFileSystem

	public function testGetCliVersionFromComposerJson(): void
	{
		$fs = new \Neuron\Core\System\MemoryFileSystem();
		$composerPath = dirname( __DIR__, 4 ) . '/composer.json';
		$fs->addFile( $composerPath, json_encode( ['version' => '2.5.0'] ) );

		$command = new VersionCommand( $fs );
		$command->configure();
		$command->setInput( new Input( [] ) );
		$command->setOutput( new Output( false ) );

		ob_start();
		$command->execute();
		$output = ob_get_clean();

		$this->assertStringContainsString( '2.5.0', $output );
	}

	public function testGetCliVersionWhenComposerJsonMissing(): void
	{
		$fs = new \Neuron\Core\System\MemoryFileSystem();
		// No composer.json file added

		$command = new VersionCommand( $fs );
		$command->configure();
		$command->setInput( new Input( [] ) );
		$command->setOutput( new Output( false ) );

		ob_start();
		$command->execute();
		$output = ob_get_clean();

		// Should fall back to default version
		$this->assertStringContainsString( '0.1.0', $output );
	}

	public function testShowAllVersionsWithComponents(): void
	{
		$fs = new \Neuron\Core\System\MemoryFileSystem();

		// Set up vendor directory structure
		$vendorDir = dirname( __DIR__, 4 ) . '/vendor';
		$fs->addDirectory( $vendorDir );
		$fs->addDirectory( $vendorDir . '/composer' );

		// Add installed.json with Neuron components
		$installedJson = $vendorDir . '/composer/installed.json';
		$installed = [
			'packages' => [
				[
					'name' => 'neuron-php/cli',
					'version' => '1.0.0',
					'description' => 'CLI component'
				],
				[
					'name' => 'neuron-php/mvc',
					'version' => '0.8.0',
					'description' => 'MVC component'
				],
				[
					'name' => 'some-other/package',
					'version' => '1.0.0',
					'description' => 'Not a Neuron package'
				]
			]
		];
		$fs->addFile( $installedJson, json_encode( $installed ) );

		$command = new VersionCommand( $fs );
		$command->configure();
		$command->setInput( new Input( ['--verbose'] ) );
		$command->setOutput( new Output( false ) );

		ob_start();
		$command->execute();
		$output = ob_get_clean();

		// Should show Neuron packages
		$this->assertStringContainsString( 'neuron-php/cli', $output );
		$this->assertStringContainsString( '1.0.0', $output );
		$this->assertStringContainsString( 'neuron-php/mvc', $output );
		$this->assertStringContainsString( '0.8.0', $output );

		// Should not show non-Neuron packages
		$this->assertStringNotContainsString( 'some-other/package', $output );
	}

	public function testShowAllVersionsWithNoComponents(): void
	{
		$fs = new \Neuron\Core\System\MemoryFileSystem();
		// No vendor directory

		$command = new VersionCommand( $fs );
		$command->configure();
		$command->setInput( new Input( ['--verbose'] ) );
		$command->setOutput( new Output( false ) );

		ob_start();
		$command->execute();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No Neuron components found', $output );
	}

	public function testShowComponentVersionWithValidComponent(): void
	{
		$fs = new \Neuron\Core\System\MemoryFileSystem();

		// Set up vendor directory structure
		$vendorDir = dirname( __DIR__, 4 ) . '/vendor';
		$fs->addDirectory( $vendorDir );
		$fs->addDirectory( $vendorDir . '/composer' );

		// Add installed.json
		$installedJson = $vendorDir . '/composer/installed.json';
		$installed = [
			[
				'name' => 'neuron-php/cli',
				'version' => '1.5.0',
				'description' => 'CLI Framework',
				'authors' => [
					['name' => 'John Doe'],
					['name' => 'Jane Smith']
				],
				'license' => 'MIT'
			]
		];
		$fs->addFile( $installedJson, json_encode( $installed ) );

		$command = new VersionCommand( $fs );
		$command->configure();
		$command->setInput( new Input( ['--component=cli'] ) );
		$command->setOutput( new Output( false ) );

		ob_start();
		$command->execute();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'neuron-php/cli', $output );
		$this->assertStringContainsString( '1.5.0', $output );
		$this->assertStringContainsString( 'CLI Framework', $output );
		$this->assertStringContainsString( 'John Doe', $output );
		$this->assertStringContainsString( 'Jane Smith', $output );
		$this->assertStringContainsString( 'MIT', $output );
	}

	public function testShowComponentVersionWithArrayLicense(): void
	{
		$fs = new \Neuron\Core\System\MemoryFileSystem();

		$vendorDir = dirname( __DIR__, 4 ) . '/vendor';
		$fs->addDirectory( $vendorDir );
		$fs->addDirectory( $vendorDir . '/composer' );

		$installedJson = $vendorDir . '/composer/installed.json';
		$installed = [
			[
				'name' => 'neuron-php/test',
				'version' => '1.0.0',
				'license' => ['MIT', 'Apache-2.0']
			]
		];
		$fs->addFile( $installedJson, json_encode( $installed ) );

		$command = new VersionCommand( $fs );
		$command->configure();
		$command->setInput( new Input( ['--component=test'] ) );
		$command->setOutput( new Output( false ) );

		ob_start();
		$command->execute();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'MIT, Apache-2.0', $output );
	}

	public function testFindVendorDirectoryInMultipleLocations(): void
	{
		$fs = new \Neuron\Core\System\MemoryFileSystem();
		$fs->setCwd( '/project' );

		// Add vendor in cwd location
		$vendorInCwd = '/project/vendor';
		$fs->addDirectory( $vendorInCwd );
		$fs->addDirectory( $vendorInCwd . '/composer' );

		$installedJson = $vendorInCwd . '/composer/installed.json';
		$installed = [
			[
				'name' => 'neuron-php/test',
				'version' => '1.0.0'
			]
		];
		$fs->addFile( $installedJson, json_encode( $installed ) );

		$command = new VersionCommand( $fs );
		$command->configure();
		$command->setInput( new Input( ['--verbose'] ) );
		$command->setOutput( new Output( false ) );

		ob_start();
		$command->execute();
		$output = ob_get_clean();

		// Should find components from cwd vendor directory
		$this->assertStringContainsString( 'neuron-php/test', $output );
	}
}
