<?php

namespace Neuron\Cli\Commands\Core;

use Neuron\Cli\Commands\Command;
use Neuron\Data\Settings\Source\Yaml as YamlSettings;

/**
 * Export configuration as environment-style pairs for production setups.
 */
class ConfigEnvCommand extends Command
{
    public function getName(): string
    {
        return 'config:env';
    }

    public function getDescription(): string
    {
        return 'Export neuron.yaml as KEY=VALUE pairs';
    }

    public function configure(): void
    {
        $this->addOption('config', 'c', true, 'Path to neuron.yaml', 'config/neuron.yaml');
        $this->addOption('category', null, true, 'Comma-separated categories to export (e.g., site,cache)');
        $this->addOption('format', 'f', true, 'Output format: dotenv|shell', 'dotenv');
        $this->addOption('quote', 'q', true, 'Quoting mode: auto|always|never', 'auto');
    }

    public function execute(): int
    {
        $configPath = (string)$this->input->getOption('config', 'config/neuron.yaml');
        $format = strtolower((string)$this->input->getOption('format', 'dotenv'));
        $quoteMode = strtolower((string)$this->input->getOption('quote', 'auto'));
        $categoryOpt = $this->input->getOption('category');

        if (!in_array($format, ['dotenv', 'shell'], true)) {
            $this->output->error("Invalid --format. Use 'dotenv' or 'shell'.");
            return 1;
        }

        if (!in_array($quoteMode, ['auto', 'always', 'never'], true)) {
            $this->output->error("Invalid --quote. Use 'auto', 'always', or 'never'.");
            return 1;
        }

        // Resolve and validate config file
        $file = $this->resolvePath($configPath);
        if (!file_exists($file) || !is_readable($file)) {
            $this->output->error("neuron.yaml not found or unreadable: {$configPath}");
            return 2;
        }

        // Load via framework YAML settings source
        try {
            $settings = new YamlSettings($file);
        } catch (\Exception $e) {
            $this->output->error('Failed to parse neuron.yaml: ' . $e->getMessage());
            return 2;
        }

        // Determine categories filter
        $categories = null;
        if (is_string($categoryOpt) && $categoryOpt !== '') {
            $categories = array_filter(array_map('trim', explode(',', $categoryOpt)), fn($v) => $v !== '');
        }

        $pairs = $this->collectPairs($settings, $categories);

        if ($categories !== null && empty($pairs)) {
            $this->output->warning('No matching categories found.');
            return 2;
        }

        ksort($pairs, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($pairs as $key => $value) {
            $line = $this->formatLine($key, $value, $format, $quoteMode);
            $this->output->write($line);
        }

        return 0;
    }

    private function resolvePath(string $path): string
    {
        if ($path !== '' && ($path[0] === '/' || preg_match('#^[A-Za-z]:\\\\#', $path))) {
            return $path;
        }
        return getcwd() . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * Collect flat KEY=>VALUE from settings.
     *
     * @param YamlSettings $settings
     * @param array<string>|null $onlyCategories
     * @return array<string,string>
     */
    private function collectPairs(YamlSettings $settings, ?array $onlyCategories): array
    {
        $result = [];

        $sections = $settings->getSectionNames();
        foreach ($sections as $section) {
            if ($onlyCategories !== null && !in_array($section, $onlyCategories, true)) {
                continue;
            }

            $keys = $settings->getSectionSettingNames($section);
            foreach ($keys as $name) {
                $val = $settings->get($section, $name);
                $this->flattenValue($result, [$section, $name], $val);
            }
        }

        return $result;
    }

    /**
     * Flatten values into KEY=VALUE pairs (handles arrays and nesting).
     *
     * @param array<string,string> $out
     * @param array<int,string|int> $path
     * @param mixed $value
     * @return void
     */
    private function flattenValue(array &$out, array $path, mixed $value): void
    {
        if (is_array($value)) {
            // Distinguish associative vs numeric
            $isAssoc = array_keys($value) !== range(0, count($value) - 1);
            if ($isAssoc) {
                foreach ($value as $k => $v) {
                    $this->flattenValue($out, array_merge($path, [(string)$k]), $v);
                }
            } else {
                foreach ($value as $idx => $v) {
                    $this->flattenValue($out, array_merge($path, [(string)$idx]), $v);
                }
            }
            return;
        }

        // Scalar cases
        if (is_bool($value)) {
            $str = $value ? 'true' : 'false';
        } elseif ($value === null) {
            $str = '';
        } else {
            $str = (string) $value;
        }

        $key = $this->normalizeKey($path);
        $out[$key] = $str;
    }

    /**
     * Normalize keys to ENV style (UPPERCASE, joined by underscore).
     *
     * @param array<int,string|int> $segments
     */
    private function normalizeKey(array $segments): string
    {
        $parts = array_map(function ($seg) {
            $s = strtoupper((string)$seg);
            // Replace non-alnum with underscore and collapse repeats
            $s = preg_replace('/[^A-Z0-9]+/', '_', $s) ?? '';
            $s = trim($s, '_');
            return $s;
        }, $segments);

        // Remove empties and join
        $parts = array_values(array_filter($parts, fn($p) => $p !== ''));
        return implode('_', $parts);
    }

    private function formatLine(string $key, string $value, string $format, string $quoteMode): string
    {
        $quoted = $this->applyQuoting($value, $quoteMode);

        if ($format === 'shell') {
            return 'export ' . $key . '=' . $quoted;
        }

        // dotenv
        return $key . '=' . $quoted;
    }

    private function applyQuoting(string $value, string $mode): string
    {
        if ($mode === 'never') {
            return $value;
        }

        $mustQuote = $mode === 'always' || $this->shouldQuote($value);

        if (!$mustQuote) {
            return $value;
        }

        // Use double quotes; escape existing double quotes and backslashes
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
        return '"' . $escaped . '"';
    }

    private function shouldQuote(string $value): bool
    {
        // Quote if contains whitespace
        if (preg_match('/\s/', $value) === 1) {
            return true;
        }
        // Or if contains shell/dotenv special characters
        return strpbrk($value, "\"'#&|;()<>*$`!\\") !== false;
    }
}

