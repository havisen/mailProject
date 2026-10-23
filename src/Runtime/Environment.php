<?php

declare(strict_types=1);

namespace App\Runtime;

final class Environment
{
    /** @return array<string, mixed> */
    public static function variables(): array
    {
        $variables = array_merge($_SERVER, $_ENV);
        foreach (getenv() ?: [] as $key => $value) {
            if (is_string($key) && !array_key_exists($key, $variables)) {
                $variables[$key] = $value;
            }
        }

        return $variables;
    }
}
