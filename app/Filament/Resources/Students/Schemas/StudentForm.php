<?php

namespace App\Filament\Resources\Students\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StudentForm
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

                TextInput::make('full_name')
                    ->label('Full name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('grade')
                    ->maxLength(50)
                    ->placeholder('e.g. Grade 9'),

                TextInput::make('section')
                    ->maxLength(50)
                    ->placeholder('e.g. Sampaguita'),

                TextInput::make('rfid_uid')
                    ->label('RFID tag UID')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText("The id encoded on the student's RFID card. Scan a card into this field to capture it.")
                    ->columnSpanFull(),

                FileUpload::make('avatar_path')
                    ->label('Photo')
                    ->image()
                    ->avatar()
                    ->directory('student-avatars')
                    ->imageEditor(),
            ])
            ->columns(2);
    }
}
