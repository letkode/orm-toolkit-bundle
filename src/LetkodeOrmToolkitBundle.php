<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle;

use Letkode\OrmToolkitBundle\Doctrine\DQL\TranslateFieldValue;
use Letkode\OrmToolkitBundle\Naming\PropertyCase;
use Letkode\OrmToolkitBundle\Naming\PropertyCaseRegistry;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
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

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('property_case')
                    ->values(array_column(PropertyCase::cases(), 'value'))
                    ->defaultValue(PropertyCase::None->value)
                    ->info('Spelling of entity properties; field names without an explicit path are converted to it.')
                ->end()
            ->end();
    }

    public function boot(): void
    {
        $case = $this->container?->getParameter('letkode_orm_toolkit.property_case');

        PropertyCaseRegistry::set(\is_string($case) ? PropertyCase::from($case) : PropertyCase::None);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()->set('letkode_orm_toolkit.property_case', $config['property_case']);
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
