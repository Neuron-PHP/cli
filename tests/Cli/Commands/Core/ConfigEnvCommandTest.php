<?php

namespace Tests\Cli\Commands\Core;

use Neuron\Cli\Commands\Core\ConfigEnvCommand;
use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use PHPUnit\Framework\TestCase;

class ConfigEnvCommandTest extends TestCase
{
    private function runCommand(array $argv): array
    {
        $cmd = new ConfigEnvCommand();
        $cmd->configure();

        $input = new Input($argv);
        $input->parse($cmd);

        $output = new Output(false); // disable colors
        $cmd->setInput($input);
        $cmd->setOutput($output);

        ob_start();
        $exit = $cmd->execute();
        $buf = ob_get_clean();
        return [$exit, $buf];
    }

    private function fixture(string $file): string
    {
        return __DIR__ . '/../../../fixtures/' . $file;
    }

    public function testDotenvOutputFromFixture(): void
    {
        [$code, $out] = $this->runCommand([
            '--config=' . $this->fixture('neuron.yaml'),
            '--quote=never'
        ]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString("SITE_NAME=NeuronPHP\n", $out);
        $this->assertStringContainsString("SITE_URL=https://neuronphp.example\n", $out);
        $this->assertMatchesRegularExpression('/^CACHE_ENABLED=(?:1|true)$/m', $out);
        $this->assertStringContainsString("CACHE_TTL=3600\n", $out);
        $this->assertStringContainsString("SYSTEM_BASE_PATH=/var/www\n", $out);
    }

    public function testShellFormat(): void
    {
        [$code, $out] = $this->runCommand([
            '--config=' . $this->fixture('neuron.yaml'),
            '--format=shell'
        ]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString("export SITE_NAME=NeuronPHP\n", $out);
        $this->assertStringContainsString("export CACHE_TTL=3600\n", $out);
    }

    public function testCategoryFilter(): void
    {
        [$code, $out] = $this->runCommand([
            '--config=' . $this->fixture('neuron.yaml'),
            '--category=site'
        ]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString("SITE_NAME=NeuronPHP\n", $out);
        $this->assertStringNotContainsString("CACHE_TTL=", $out);
    }

    public function testMissingFileReturnsError(): void
    {
        [$code, $out] = $this->runCommand([
            '--config=' . $this->fixture('missing.yaml')
        ]);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('not found', strtolower($out));
    }
}
