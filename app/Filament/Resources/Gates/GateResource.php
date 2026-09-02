<?php

namespace App\Filament\Resources\Gates;

use App\Filament\Concerns\ScopesToSchool;
use App\Filament\Resources\Gates\Pages\CreateGate;
use App\Filament\Resources\Gates\Pages\EditGate;
use App\Filament\Resources\Gates\Pages\ListGates;
use App\Filament\Resources\Gates\Schemas\GateForm;
use App\Filament\Resources\Gates\Tables\GatesTable;
use App\Models\Gate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class GateResource extends Resource
{
    use ScopesToSchool;

    protected static ?string $model = Gate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return GateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGates::route('/'),
            'create' => CreateGate::route('/create'),
            'edit' => EditGate::route('/{record}/edit'),
        ];
    }
}
