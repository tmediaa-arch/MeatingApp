<?php

declare(strict_types=1);

namespace App\Filament\Admin\Schemas;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;

class FormLayout
{
    public const COLUMNS = 12;
    public const MAIN_SPAN = 8;
    public const SIDEBAR_SPAN = 4;

    /**
     * Wrap form/schema components in a 12-column grid: main (8) + sidebar (4).
     *
     * @param  array<int, Component>  $main
     * @param  array<int, Component>  $sidebar
     * @return array<int, Component>
     */
    public static function withSidebar(array $main, array $sidebar): array
    {
        return [
            Grid::make(self::COLUMNS)
                ->columnSpanFull()
                ->schema([
                    Group::make($main)->columnSpan([
                        'default' => self::COLUMNS,
                        'lg' => self::MAIN_SPAN,
                    ]),
                    Group::make($sidebar)->columnSpan([
                        'default' => self::COLUMNS,
                        'lg' => self::SIDEBAR_SPAN,
                    ]),
                ]),
        ];
    }
}
