<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle;

use Letkode\OrmToolkitBundle\Doctrine\DQL\TranslateFieldValue;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class LetkodeOrmToolkitBundle extends AbstractBundle implements PrependExtensionInterface
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import($this->getPath() . '/config/services.yaml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('doctrine')) {
            return;
        }

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'dql' => [
                    'string_functions' => [
                        'TRANSLATE_FIELD_VALUE' => TranslateFieldValue::class,
                    ],
                ],
            ],
        ]);
    }
}
