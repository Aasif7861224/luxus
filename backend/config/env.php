<?php
if (!function_exists('app_load_env')) {
    function app_load_env($filePath)
    {
        static $loadedPaths = [];
        if (isset($loadedPaths[$filePath])) {
            return;
        }
        $loadedPaths[$filePath] = true;

        if (!is_file($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $quote = $value[0];
                if (substr($value, -1) === $quote) {
                    $value = substr($value, 1, -1);
                }
            }

            if ($key === '') {
                continue;
            }

            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

if (!function_exists('app_env')) {
    function app_env($key, $default = null)
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }
}

app_load_env(dirname(__DIR__, 2) . '/.env');
