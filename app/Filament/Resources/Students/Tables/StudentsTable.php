<?php

namespace App\Filament\Resources\Students\Tables;

use App\Actions\IssueLinkCode;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class StudentsTable
{
    /**
     * The little form shown when issuing a code (single or bulk).
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected static function issueForm(): array
    {
        return [
            \Filament\Forms\Components\Select::make('relationship')
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
                ->required(),
        ];
    }

    protected static function issuedNotification(string $title, string $body, ?string $printUrl): void
    {
        $actions = [];

        if ($printUrl) {
            $actions[] = Action::make('print')
                ->label('Print slip')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url($printUrl, shouldOpenInNewTab: true);
        }

        Notification::make()
            ->success()
            ->title($title)
            ->body($body)
            ->actions($actions)
            ->persistent()
            ->send();
    }

    public static function configure(Table $table): Table
    {
        $isSuperAdmin = (bool) (auth()->user()?->isSuperAdmin());

        return $table
            ->columns([
                ImageColumn::make('avatar_path')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=Student&background=random'),

                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Student $r) => trim(collect([$r->grade, $r->section])->filter()->join(' • ')) ?: null),

                TextColumn::make('school.name')
                    ->label('School')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->visible($isSuperAdmin),

                TextColumn::make('rfid_uid')
                    ->label('RFID')
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->copyMessage('RFID copied')
                    ->toggleable(),

                TextColumn::make('guardians_count')
                    ->label('Guardians')
                    ->counts('guardians')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'warning')
                    ->alignCenter(),

                TextColumn::make('link_code_state')
                    ->label('Link code')
                    ->badge()
                    ->state(function (Student $r): string {
                        if ($code = $r->activeLinkCode()) {
                            return $code->code;
                        }

                        return $r->guardians()->exists() ? 'Linked' : 'None';
                    })
                    ->color(fn (string $state) => match (true) {
                        $state === 'Linked' => 'success',
                        $state === 'None' => 'gray',
                        default => 'warning',
                    })
                    ->copyable(),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('school')
                    ->relationship('school', 'name')
                    ->visible($isSuperAdmin),

                SelectFilter::make('grade')
                    ->options(fn () => Student::query()
                        ->whereNotNull('grade')
                        ->distinct()
                        ->orderBy('grade')
                        ->pluck('grade', 'grade')
                        ->all()),

                Filter::make('needs_guardian')
                    ->label('No guardian linked yet')
                    ->query(fn (Builder $query) => $query->doesntHave('guardians')),

                Filter::make('code_outstanding')
                    ->label('Has an unredeemed code')
                    ->query(fn (Builder $query) => $query->whereHas('linkCodes', fn (Builder $sub) => $sub->usable())),
            ])
            ->recordActions([
                Action::make('issueLinkCode')
                    ->label('Issue code')
                    ->icon(Heroicon::OutlinedTicket)
                    ->color('primary')
                    ->schema(static::issueForm())
                    ->modalHeading(fn (Student $r) => "Issue a link code for {$r->full_name}")
                    ->modalDescription('The guardian enters this code in the app under Settings → Link a student.')
                    ->modalSubmitActionLabel('Issue code')
                    ->action(function (Student $record, array $data): void {
                        $code = IssueLinkCode::run(
                            $record,
                            $data['relationship'] ?? 'Guardian',
                            (int) ($data['valid_for_days'] ?? 30),
                        );

                        static::issuedNotification(
                            "Code {$code->code} issued",
                            "Give it to {$record->full_name}'s guardian. It expires "
                                .($code->expires_at?->toFormattedDayDateString() ?? 'never').'.',
                            route('admin.link-codes.slip', $code),
                        );
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('issueLinkCodes')
                        ->label('Issue link codes')
                        ->icon(Heroicon::OutlinedTicket)
                        ->color('primary')
                        ->schema(static::issueForm())
                        ->modalHeading('Issue a link code for each selected student')
                        ->modalSubmitActionLabel('Issue codes')
                        ->action(function (Collection $records, array $data): void {
                            $ids = $records->map(fn (Student $s) => IssueLinkCode::run(
                                $s,
                                $data['relationship'] ?? 'Guardian',
                                (int) ($data['valid_for_days'] ?? 30),
                            )->getKey());

                            static::issuedNotification(
                                "{$ids->count()} link codes issued",
                                'Open the printable sheet to hand them out.',
                                route('admin.link-codes.slips', ['ids' => $ids->join(',')]),
                            );
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('full_name');
    }
}
