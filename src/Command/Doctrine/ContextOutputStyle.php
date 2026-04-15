<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Command\Doctrine;

use function array_column;

enum ContextOutputStyle: string
{
    case Section = 'section';
    case Line    = 'line';
    case None    = 'none';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
