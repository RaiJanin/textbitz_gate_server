<?php

namespace App\Filament\Resources\LinkCodes;

use App\Filament\Concerns\ScopesToSchool;
use App\Filament\Resources\LinkCodes\Pages\CreateLinkCode;
use App\Filament\Resources\LinkCodes\Pages\ListLinkCodes;
use App\Filament\Resources\LinkCodes\Schemas\LinkCodeForm;
use App\Filament\Resources\LinkCodes\Tables\LinkCodesTable;
use App\Models\LinkCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LinkCodeResource extends Resource
{
    use ScopesToSchool;

    protected static ?string $model = LinkCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Access';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?string $modelLabel = 'link code';

    public static function form(Schema $schema): Schema
    {
        return LinkCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LinkCodesTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $outstanding = static::getEloquentQuery()->usable()->count();

        return $outstanding > 0 ? (string) $outstanding : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Codes issued but not yet redeemed';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLinkCodes::route('/'),
            'create' => CreateLinkCode::route('/create'),
        ];
    }
}
