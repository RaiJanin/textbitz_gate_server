<?php

namespace App\Filament\Resources\Students\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GuardiansRelationManager extends RelationManager
{
    protected static string $relationship = 'guardians';

    protected static ?string $title = 'Linked guardians';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-user-group';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('phone')->tel()->maxLength(255),
            TextInput::make('email')->email()->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('phone')->label('Phone')->icon('heroicon-m-phone')->copyable(),
                TextColumn::make('pivot.relationship')
                    ->label('Relationship')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('user.phone_number')
                    ->label('App account')
                    ->placeholder('no app login yet')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray'),
                TextColumn::make('created_at')->label('Linked')->since()->sortable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Link an existing guardian')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'phone', 'email'])
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        TextInput::make('relationship')
                            ->default('Guardian')
                            ->required()
                            ->maxLength(40),
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
            ->emptyStateHeading('No guardians linked yet')
            ->emptyStateDescription('Issue a link code from the students list, or attach an existing guardian here.');
    }
}
