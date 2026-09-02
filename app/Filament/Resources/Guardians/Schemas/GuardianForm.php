<?php

namespace App\Filament\Resources\Guardians\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GuardianForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),

                TextInput::make('email')
                    ->email()
                    ->maxLength(255),

                Placeholder::make('app_account')
                    ->label('App account')
                    ->content(fn ($record) => $record?->user?->phone_number
                        ? 'Signed in as '.$record->user->phone_number
                        : 'This guardian has not signed up in the app yet.'),
            ])
            ->columns(2);
    }
}
