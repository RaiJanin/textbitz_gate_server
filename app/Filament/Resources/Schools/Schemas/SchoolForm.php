<?php

namespace App\Filament\Resources\Schools\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SchoolForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('School details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('timezone')
                            ->options(fn () => collect(timezone_identifiers_list())
                                ->mapWithKeys(fn (string $tz) => [$tz => $tz])
                                ->all())
                            ->searchable()
                            ->required()
                            ->default('Asia/Manila'),

                        TimePicker::make('attendance_cutoff_time')
                            ->label('Attendance cutoff')
                            ->seconds(false)
                            ->required()
                            ->helperText('Arrivals after this local time count as late.'),

                        TextInput::make('contact_phone')
                            ->tel()
                            ->maxLength(255),

                        TextInput::make('contact_email')
                            ->email()
                            ->maxLength(255),
                    ]),

                Section::make('Turnstile ingest')
                    ->description('The bearer token the gate hardware uses to post taps. Keep it secret; regenerate it from the header if it leaks.')
                    ->schema([
                        TextInput::make('ingest_token')
                            ->label('Ingest token')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                    ]),
            ]);
    }
}
