<?php

// Loads the plugin's classes without a panel, for the unit tests.
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'Simplify\\Explain\\';
    if (str_starts_with($class, $prefix)) {
        $path = __DIR__.'/../src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

if (! function_exists('__')) {
    // The English text, so a test can check which message is shown.
    function __(string $key, array $replace = []): string
    {
        [$group, $name] = explode('.', explode('::', $key, 2)[1], 2);
        $text = (require __DIR__.'/../lang/en/'.$group.'.php')[$name] ?? $key;
        foreach ($replace as $search => $value) {
            $text = str_replace(':'.$search, (string) $value, $text);
        }

        return $text;
    }
}

$failures = 0;
function check(string $what, bool $passed): void
{
    global $failures;
    if (! $passed) {
        $failures++;
        echo "FAIL $what\n";
    }
}
function finish(string $suite): void
{
    global $failures;
    echo $failures ? "FAIL $suite: $failures\n" : "PASS $suite\n";
    exit($failures ? 1 : 0);
}
