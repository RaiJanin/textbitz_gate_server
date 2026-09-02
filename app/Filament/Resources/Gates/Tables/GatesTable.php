<?php

namespace App\Filament\Resources\Gates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GatesTable
{
    public static function configure(Table $table): Table
    {
        $isSuperAdmin = (bool) (auth()->user()?->isSuperAdmin());

        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('bold'),

                TextColumn::make('school.name')
                    ->label('School')
                    ->searchable()
                    ->toggleable()
                    ->visible($isSuperAdmin),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => ucfirst($state ?? 'unknown'))
                    ->color(fn (?string $state) => $state === 'online' ? 'success' : 'danger'),

                TextColumn::make('last_seen_at')
                    ->label('Last heartbeat')
                    ->since()
                    ->placeholder('never')
                    ->sortable(),

                TextColumn::make('tap_events_count')
                    ->counts('tapEvents')
                    ->label('Taps (all time)')
                    ->badge()
                    ->alignCenter()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'online' => 'Online',
                    'offline' => 'Offline',
                ]),
                SelectFilter::make('school')
                    ->relationship('school', 'name')
                    ->visible($isSuperAdmin),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name')
            ->poll('30s');
    }
}
