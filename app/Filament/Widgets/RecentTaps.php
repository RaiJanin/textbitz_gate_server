<?php

namespace App\Filament\Widgets;

use App\Models\TapEvent;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentTaps extends TableWidget
{
    protected static ?string $heading = 'Latest turnstile activity';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $query = TapEvent::query()->with(['student', 'gate'])->latest('tapped_at');

                $user = auth()->user();

                if ($user && ! $user->isSuperAdmin() && $user->school_id) {
                    $query->whereHas('student', fn (Builder $q) => $q->where('school_id', $user->school_id));
                }

                return $query;
            })
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('tapped_at')
                    ->label('When')
                    ->since()
                    ->tooltip(fn (TapEvent $r) => $r->tapped_at?->toDayDateTimeString()),

                TextColumn::make('student.full_name')
                    ->label('Student')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('direction')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'in' ? 'Arrived' : 'Left')
                    ->color(fn (string $state) => $state === 'in' ? 'success' : 'info'),

                TextColumn::make('is_late')
                    ->label('On time?')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Late' : 'On time')
                    ->color(fn ($state) => $state ? 'warning' : 'gray'),

                TextColumn::make('gate.name')->label('Gate'),
            ])
            ->paginated([10, 25]);
    }
}
