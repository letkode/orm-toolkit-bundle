<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Trait\Entity;

use Doctrine\ORM\Mapping as ORM;

trait HasTranslationsTrait
{
    #[ORM\Column(type: 'jsonb', nullable: true)]
    public array|null $translations = null;

    public function getTranslation(string $locale, string $field): string|null
    {
        return $this->translations[$locale][$field] ?? null;
    }

    public function setTranslation(string $locale, string $field, string|null $value): void
    {
        $translations = $this->translations ?? [];

        if (null === $value) {
            unset($translations[$locale][$field]);
        } else {
            $translations[$locale][$field] = $value;
        }

        $this->translations = $translations;
    }
}
