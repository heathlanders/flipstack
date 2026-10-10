<?php

namespace App\Services;

class SkuParserService
{
    /**
     * Parse a raw SKU string using the given config.
     *
     * @return array{owner: ?string, sku: ?string, location: ?string}|null
     */
    public function parse(string $rawSku, array $config): ?array
    {
        if (empty($config['enabled'])) {
            return null;
        }

        $rawSku = trim($rawSku);
        if ($rawSku === '') {
            return null;
        }

        $mode = $config['mode'] ?? 'template';

        try {
            if ($mode === 'regex') {
                return $this->parseRegex($rawSku, $config['regex']['pattern'] ?? '');
            }

            return $this->parseTemplate($rawSku, $config['template'] ?? []);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Try multiple parser configs in order, returning the first match.
     * Each config can have a 'prefix' key for quick prefix matching before parsing.
     *
     * @param  array[]  $parsers  Array of parser configs
     * @return array{owner: ?string, sku: ?string, location: ?string}|null
     */
    public function parseMulti(string $rawSku, array $parsers): ?array
    {
        $rawSku = trim($rawSku);
        if ($rawSku === '') {
            return null;
        }

        foreach ($parsers as $parserConfig) {
            if (empty($parserConfig['enabled'])) {
                continue;
            }

            // If parser has a prefix, do a quick check before running the full parse
            if (! empty($parserConfig['prefix'])) {
                $prefix = $parserConfig['prefix'];
                if (! str_starts_with($rawSku, $prefix)) {
                    continue;
                }
            }

            $result = $this->parse($rawSku, $parserConfig);
            if ($result !== null && ($result['owner'] !== null || $result['sku'] !== null)) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Normalize parser config: handles both old singular (sku_parser) and new array (sku_parsers) format.
     *
     * @return array[] Array of parser configs
     */
    public static function normalizeParsers(array $credentialConfig): array
    {
        // New format: array of parsers
        if (! empty($credentialConfig['sku_parsers']) && is_array($credentialConfig['sku_parsers'])) {
            return $credentialConfig['sku_parsers'];
        }

        // Old format: single parser config
        if (! empty($credentialConfig['sku_parser']) && is_array($credentialConfig['sku_parser'])) {
            return [$credentialConfig['sku_parser']];
        }

        return [];
    }

    /**
     * Parse using template mode (delimiter or fixed-width).
     *
     * @return array{owner: ?string, sku: ?string, location: ?string}
     */
    public function parseTemplate(string $rawSku, array $template): array
    {
        $type = $template['type'] ?? 'delimiter';

        if ($type === 'fixed') {
            return $this->parseFixedWidth($rawSku, $template['ranges'] ?? []);
        }

        return $this->parseDelimiter($rawSku, $template);
    }

    protected function parseDelimiter(string $rawSku, array $template): array
    {
        $delimiter = $template['delimiter'] ?? ' ';
        $segments = $template['segments'] ?? [];
        $parts = explode($delimiter, $rawSku);

        $result = ['owner' => null, 'sku' => null, 'location' => null];

        foreach ($segments as $segment) {
            $field = $segment['field'] ?? null;
            $position = $segment['position'] ?? null;

            if ($field === null || $position === null) {
                continue;
            }

            if (! array_key_exists($field, $result)) {
                continue;
            }

            if (isset($parts[$position])) {
                $value = trim($parts[$position]);
                $result[$field] = $value !== '' ? $value : null;
            }
        }

        return $result;
    }

    protected function parseFixedWidth(string $rawSku, array $ranges): array
    {
        $result = ['owner' => null, 'sku' => null, 'location' => null];

        foreach ($ranges as $range) {
            $field = $range['field'] ?? null;
            $start = $range['start'] ?? null;
            $length = $range['length'] ?? null;

            if ($field === null || $start === null || $length === null) {
                continue;
            }

            if (! array_key_exists($field, $result)) {
                continue;
            }

            if ($start < strlen($rawSku)) {
                $value = trim(substr($rawSku, $start, $length));
                $result[$field] = $value !== '' ? $value : null;
            }
        }

        return $result;
    }

    /**
     * Parse using regex mode with named groups.
     *
     * @return array{owner: ?string, sku: ?string, location: ?string}
     */
    public function parseRegex(string $rawSku, string $pattern): array
    {
        $result = ['owner' => null, 'sku' => null, 'location' => null];

        if (empty($pattern)) {
            return $result;
        }

        if (@preg_match($pattern, $rawSku, $matches) !== 1) {
            return $result;
        }

        foreach (['owner', 'sku', 'location'] as $field) {
            if (! empty($matches[$field])) {
                $result[$field] = trim($matches[$field]);
            }
        }

        return $result;
    }

    /**
     * Validate a parser configuration.
     *
     * @return string[] Validation errors (empty if valid)
     */
    public function validate(array $config): array
    {
        $errors = [];

        if (! isset($config['mode'])) {
            $errors[] = 'Parser mode is required.';

            return $errors;
        }

        if ($config['mode'] === 'regex') {
            $pattern = $config['regex']['pattern'] ?? '';
            if (empty($pattern)) {
                $errors[] = 'Regex pattern is required.';
            } elseif (@preg_match($pattern, '') === false) {
                $errors[] = 'Invalid regex pattern: '.preg_last_error_msg();
            }
        } elseif ($config['mode'] === 'template') {
            $template = $config['template'] ?? [];
            $type = $template['type'] ?? 'delimiter';

            if ($type === 'delimiter') {
                if (! isset($template['delimiter']) || $template['delimiter'] === '') {
                    $errors[] = 'Delimiter is required for delimiter mode.';
                }
                if (empty($template['segments'])) {
                    $errors[] = 'At least one segment mapping is required.';
                }
            } elseif ($type === 'fixed') {
                if (empty($template['ranges'])) {
                    $errors[] = 'At least one range mapping is required.';
                }
            } else {
                $errors[] = "Unknown template type: {$type}";
            }
        } else {
            $errors[] = "Unknown mode: {$config['mode']}";
        }

        return $errors;
    }

    /**
     * Test the parser with a sample SKU.
     */
    public function test(string $rawSku, array $config): array
    {
        $errors = $this->validate($config);
        if (! empty($errors)) {
            return ['success' => false, 'result' => null, 'error' => implode(' ', $errors)];
        }

        $testConfig = array_merge($config, ['enabled' => true]);
        $result = $this->parse($rawSku, $testConfig);

        if ($result === null) {
            return ['success' => false, 'result' => null, 'error' => 'Parser returned no result for the given SKU.'];
        }

        return ['success' => true, 'result' => $result, 'error' => null];
    }
}
