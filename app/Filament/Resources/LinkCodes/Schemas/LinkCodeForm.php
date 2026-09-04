<?php

namespace App\Filament\Resources\LinkCodes\Schemas;

use App\Models\Student;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class LinkCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = auth()->user();
        $scoped = $user && ! $user->isSuperAdmin() && $user->school_id;

        return $schema
            ->components([
                Select::make('student_id')
                    ->label('Student')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->relationship(
                        'student',
                        'full_name',
                        fn (Builder $query) => $scoped ? $query->where('school_id', $user->school_id) : $query,
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Student $s) => trim($s->full_name.' — '.collect([$s->grade, $s->section])->filter()->join(' ')),
                    ),

                Select::make('default_relationship')
                    ->label('Relationship shown to the guardian')
                    ->options(array_combine(\App\Support\Relationship::VALUES, \App\Support\Relationship::VALUES))
                    ->default(\App\Support\Relationship::DEFAULT)
                    ->required(),

                TextInput::make('valid_for_days')
                    ->label('Valid for (days)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(365)
                    ->default(30)
                    ->required()
                    ->helperText('Give the guardian enough time to download the app and sign up.'),
            ])
            ->columns(1);
    }
}
