<?php

namespace App\Filament\Resources\Guardians\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GuardiansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('bold'),

                TextColumn::make('phone')->label('Phone')->searchable()->icon('heroicon-m-phone')->copyable(),

                TextColumn::make('students.full_name')
                    ->label('Linked children')
                    ->badge()
                    ->separator(',')
                    ->limitList(3)
                    ->placeholder('none'),

                TextColumn::make('user.phone_number')
                    ->label('App account')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => $state ? 'active' : 'not signed up'),

                TextColumn::make('created_at')->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('no_children')
                    ->label('No children linked')
                    ->query(fn (Builder $query) => $query->doesntHave('students')),

                Filter::make('no_app_account')
                    ->label('Has not signed up in the app')
                    ->query(fn (Builder $query) => $query->whereNull('user_id')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
