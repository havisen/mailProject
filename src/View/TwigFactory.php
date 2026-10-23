<?php

declare(strict_types=1);

namespace App\View;

use App\Config\Config;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class TwigFactory
{
    public static function create(Config $config): Environment
    {
        $loader = new FilesystemLoader($config->string('app.base_path') . '/templates');

        return new Environment($loader, [
            'autoescape' => 'html',
            'strict_variables' => true,
            'debug' => $config->bool('app.debug'),
            // A private compiled-template cache can be enabled after its
            // deployment permissions and cleanup policy are acceptance-tested.
            'cache' => false,
        ]);
    }
}
