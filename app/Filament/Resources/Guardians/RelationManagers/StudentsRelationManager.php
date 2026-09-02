<?php

namespace App\Filament\Resources\Guardians\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';

    protected static ?string $title = 'Linked children';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-academic-cap';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('relationship')
                ->helperText('How this guardian relates to the child, e.g. Mom, Dad, Guardian.')
                ->default('Guardian')
                ->required()
                ->maxLength(40),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                TextColumn::make('full_name')->label('Student')->searchable()->weight('bold'),
                TextColumn::make('school.name')->label('School')->toggleable(),
                TextColumn::make('grade')->badge()->placeholder('—'),
                TextColumn::make('pivot.relationship')->label('Relationship')->badge(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Link a student')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['full_name', 'rfid_uid'])
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        TextInput::make('relationship')->default('Guardian')->required()->maxLength(40),
                    ]),
            ])
            ->recordActions([
                DetachAction::make()->label('Unlink'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No children linked to this guardian');
    }
}
