<?php

namespace App\Filament\Resources\LinkCodes\Tables;

use App\Actions\IssueLinkCode;
use App\Models\LinkCode;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LinkCodesTable
{
    public static function configure(Table $table): Table
    {
        $isSuperAdmin = (bool) (auth()->user()?->isSuperAdmin());

        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->weight('bold')
                    ->badge()
                    ->color('primary')
                    ->copyable()
                    ->copyMessage('Code copied'),

                TextColumn::make('student.full_name')
                    ->label('Student')
                    ->searchable()
                    ->sortable()
                    ->description(fn (LinkCode $r) => $r->default_relationship ? "as “{$r->default_relationship}”" : null),

                TextColumn::make('school.name')
                    ->label('School')
                    ->searchable()
                    ->toggleable()
                    ->visible($isSuperAdmin),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => match ($state) {
                        'usable' => 'warning',
                        'consumed' => 'success',
                        'expired' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('consumedByGuardian.name')
                    ->label('Redeemed by')
                    ->placeholder('—')
                    ->description(fn (LinkCode $r) => $r->consumedByGuardian?->user?->phone_number
                        ?? $r->consumedByGuardian?->phone)
                    ->toggleable(),

                TextColumn::make('consumed_at')
                    ->label('Redeemed at')
                    ->dateTime('M j, Y g:i A')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M j, Y')
                    ->placeholder('never')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Issued')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'usable' => 'Usable',
                        'consumed' => 'Redeemed',
                        'expired' => 'Expired',
                    ])
                    ->query(function ($query, array $data) {
                        return match ($data['value'] ?? null) {
                            'usable' => $query->usable(),
                            'consumed' => $query->whereNotNull('consumed_at'),
                            'expired' => $query->whereNull('consumed_at')->whereNotNull('expires_at')->where('expires_at', '<=', now()),
                            default => $query,
                        };
                    }),

                SelectFilter::make('school')
                    ->relationship('school', 'name')
                    ->visible($isSuperAdmin),
            ])
            ->recordActions([
                Action::make('print')
                    ->label('Print slip')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (LinkCode $record) => route('admin.link-codes.slip', $record))
                    ->openUrlInNewTab(),

                Action::make('revoke')
                    ->label('Revoke')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('The code will stop working immediately. The guardian will need a new one.')
                    ->visible(fn (LinkCode $record) => $record->isUsable())
                    ->action(function (LinkCode $record) {
                        IssueLinkCode::revoke($record);

                        Notification::make()->success()->title("Code {$record->code} revoked")->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
