<?php

namespace App\Filament\Resources\Schools\Tables;

use App\Models\School;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SchoolsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('bold'),
                TextColumn::make('timezone')->toggleable(),
                TextColumn::make('attendance_cutoff_time')->label('Cutoff')->time('H:i'),
                TextColumn::make('students_count')->counts('students')->label('Students')->badge()->alignCenter(),
                TextColumn::make('gates_count')->counts('gates')->label('Gates')->badge()->alignCenter(),
                TextColumn::make('contact_phone')->label('Contact')->toggleable(),
                TextColumn::make('created_at')->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('copyIngestToken')
                    ->label('Copy ingest token')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('gray')
                    ->action(function (School $record) {
                        Notification::make()
                            ->title('Ingest token')
                            ->body($record->ingest_token)
                            ->persistent()
                            ->send();
                    }),

                Action::make('regenerateIngestToken')
                    ->label('Regenerate token')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Every gate device at this school must be updated with the new token before it can post taps again.')
                    ->action(function (School $record) {
                        $record->update(['ingest_token' => Str::random(48)]);

                        Notification::make()
                            ->success()
                            ->title('New ingest token generated')
                            ->body($record->ingest_token)
                            ->persistent()
                            ->send();
                    }),

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
