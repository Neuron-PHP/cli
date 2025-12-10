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

    public function testInvalidFormatReturnsError(): void
    {
        [$code, $out] = $this->runCommand([
            '--config=' . $this->fixture('neuron.yaml'),
            '--format=invalid'
        ]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Invalid --format', $out);
    }

    public function testInvalidQuoteModeReturnsError(): void
    {
        [$code, $out] = $this->runCommand([
            '--config=' . $this->fixture('neuron.yaml'),
            '--quote=invalid'
        ]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Invalid --quote', $out);
    }

    public function testEmptyCategoryWarning(): void
    {
        [$code, $out] = $this->runCommand([
            '--config=' . $this->fixture('neuron.yaml'),
            '--category=nonexistent'
        ]);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('No matching categories', $out);
    }

    public function testQuoteAlways(): void
    {
        [$code, $out] = $this->runCommand([
            '--config=' . $this->fixture('neuron.yaml'),
            '--quote=always'
        ]);

        $this->assertSame(0, $code);
        // All values should be quoted
        $this->assertStringContainsString('SITE_NAME="NeuronPHP"', $out);
        $this->assertStringContainsString('CACHE_TTL="3600"', $out);
    }

    public function testQuoteAuto(): void
    {
        [$code, $out] = $this->runCommand([
            '--config=' . $this->fixture('neuron.yaml'),
            '--quote=auto'
        ]);

        $this->assertSame(0, $code);
        // Simple values should not be quoted
        $this->assertStringContainsString("SITE_NAME=NeuronPHP\n", $out);
        // Values with spaces should be quoted
        $this->assertStringContainsString('SITE_TITLE="Example Site"', $out);
    }

    public function testMultipleCategoriesFilter(): void
    {
        [$code, $out] = $this->runCommand([
            '--config=' . $this->fixture('neuron.yaml'),
            '--category=site,cache'
        ]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString("SITE_NAME=NeuronPHP\n", $out);
        $this->assertStringContainsString("CACHE_TTL=3600\n", $out);
        $this->assertStringNotContainsString("SYSTEM_BASE_PATH=", $out);
    }

    public function testAbsolutePathHandling(): void
    {
        // Use absolute path to fixture
        $absolutePath = realpath($this->fixture('neuron.yaml'));

        [$code, $out] = $this->runCommand([
            '--config=' . $absolutePath
        ]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString("SITE_NAME=NeuronPHP\n", $out);
    }

    public function testBooleanValues(): void
    {
        [$code, $out] = $this->runCommand([
            '--config=' . $this->fixture('edge-cases.yaml'),
            '--category=flags'
        ]);

        $this->assertSame(0, $code);
        // Booleans should be converted to "true" and "false" strings
        $this->assertStringContainsString("FLAGS_ENABLED=true\n", $out);
        $this->assertStringContainsString("FLAGS_DISABLED=false\n", $out);
    }

    public function testNullValues(): void
    {
        [$code, $out] = $this->runCommand([
            '--config=' . $this->fixture('edge-cases.yaml'),
            '--category=flags'
        ]);

        $this->assertSame(0, $code);
        // Null should be converted to empty string
        $this->assertStringContainsString("FLAGS_EMPTY=\n", $out);
    }

    public function testSpecialCharactersQuoting(): void
    {
        [$code, $out] = $this->runCommand([
            '--config=' . $this->fixture('edge-cases.yaml'),
            '--category=special',
            '--quote=auto'
        ]);

        $this->assertSame(0, $code);
        // Values with special characters should be quoted
        $this->assertStringContainsString('SPECIAL_WITH_SPACES="value with spaces"', $out);
        $this->assertStringContainsString('SPECIAL_WITH_HASH="value # with hash"', $out);
        $this->assertStringContainsString('SPECIAL_WITH_DOLLAR="$HOME/path"', $out);
    }

    public function testNestedArrays(): void
    {
        [$code, $out] = $this->runCommand([
            '--config=' . $this->fixture('edge-cases.yaml'),
            '--category=nested'
        ]);

        $this->assertSame(0, $code);
        // Nested values should be flattened with underscores
        $this->assertStringContainsString("NESTED_DATABASE_HOST=localhost\n", $out);
        $this->assertStringContainsString("NESTED_DATABASE_PORT=3306\n", $out);
        $this->assertStringContainsString("NESTED_DATABASE_CREDENTIALS_USER=admin\n", $out);
        $this->assertStringContainsString("NESTED_DATABASE_CREDENTIALS_PASS=secret\n", $out);
    }

    public function testNumericArrays(): void
    {
        [$code, $out] = $this->runCommand([
            '--config=' . $this->fixture('edge-cases.yaml'),
            '--category=arrays'
        ]);

        $this->assertSame(0, $code);
        // Numeric arrays should be flattened with index
        $this->assertStringContainsString("ARRAYS_TAGS_0=production\n", $out);
        $this->assertStringContainsString("ARRAYS_TAGS_1=staging\n", $out);
        $this->assertStringContainsString("ARRAYS_TAGS_2=development\n", $out);
        $this->assertStringContainsString("ARRAYS_PORTS_0=80\n", $out);
        $this->assertStringContainsString("ARRAYS_PORTS_1=443\n", $out);
        $this->assertStringContainsString("ARRAYS_PORTS_2=8080\n", $out);
    }

    public function testQuoteEscaping(): void
    {
        [$code, $out] = $this->runCommand([
            '--config=' . $this->fixture('edge-cases.yaml'),
            '--category=special',
            '--quote=always'
        ]);

        $this->assertSame(0, $code);
        // Quotes in values should be escaped
        $this->assertStringContainsString('SPECIAL_WITH_QUOTES="value with \\"quotes\\""', $out);
    }

    public function testMalformedYamlReturnsError(): void
    {
        [$code, $out] = $this->runCommand([
            '--config=' . $this->fixture('malformed.yaml')
        ]);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('Failed to parse', $out);
    }

    public function testEmptyCategoryStringIgnored(): void
    {
        [$code, $out] = $this->runCommand([
            '--config=' . $this->fixture('neuron.yaml'),
            '--category='
        ]);

        $this->assertSame(0, $code);
        // Empty category should be ignored, all categories should be exported
        $this->assertStringContainsString("SITE_NAME=NeuronPHP\n", $out);
        $this->assertStringContainsString("CACHE_TTL=3600\n", $out);
    }
}
