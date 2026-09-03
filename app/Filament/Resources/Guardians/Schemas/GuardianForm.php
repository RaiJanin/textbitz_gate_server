<?php

namespace App\Filament\Resources\Guardians\Schemas;

use App\Rules\PhilippineMobileNumber;
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
                    ->label('Mobile number')
                    ->tel()
                    ->required()
                    ->rule(new PhilippineMobileNumber)
                    // On create the number must not already belong to an app account
                    // (that person would already have a guardian profile).
                    ->rule(fn (string $operation) => $operation === 'create' ? 'unique:users,phone_number' : null)
                    ->validationMessages(['unique' => 'Someone with this number already has an app account.'])
                    ->helperText('Also becomes the guardian\'s login for the mobile app (+639XXXXXXXXX).')
                    ->maxLength(255),

                TextInput::make('email')
                    ->email()
                    ->maxLength(255),

                TextInput::make('password')
                    ->label('App password')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->helperText('Sets the guardian\'s mobile-app password. Leave blank to auto-generate one (shown after saving).')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->visibleOn('create'),

                Placeholder::make('app_account')
                    ->label('App account')
                    ->content(fn ($record) => $record?->user?->phone_number
                        ? 'Signs in as '.$record->user->phone_number
                        : 'No mobile-app login yet.')
                    ->visibleOn('edit'),
            ])
            ->columns(2);
    }
}
