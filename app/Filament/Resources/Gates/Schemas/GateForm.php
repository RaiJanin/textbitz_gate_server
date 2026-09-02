<?php

namespace App\Filament\Resources\Gates\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GateForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = auth()->user();
        $scoped = $user && ! $user->isSuperAdmin() && $user->school_id;

        return $schema
            ->components([
                Select::make('school_id')
                    ->label('School')
                    ->relationship('school', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->default($scoped ? $user->school_id : null)
                    ->hidden($scoped)
                    ->dehydrated(),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Main Gate'),

                TextInput::make('status')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit')
                    ->helperText('Set automatically from turnstile heartbeats.'),
            ])
            ->columns(2);
    }
}
