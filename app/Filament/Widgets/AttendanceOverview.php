<?php

namespace App\Filament\Widgets;

use App\Models\Gate;
use App\Models\LinkCode;
use App\Models\School;
use App\Models\Student;
use App\Models\TapEvent;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AttendanceOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    /** Constrain a query to the signed-in staff member's school. */
    protected function scope(Builder $query, string $column = 'school_id'): Builder
    {
        $user = auth()->user();

        if ($user && ! $user->isSuperAdmin() && $user->school_id) {
            $query->where($column, $user->school_id);
        }

        return $query;
    }

    protected function today(): Carbon
    {
        $user = auth()->user();

        $tz = $user && $user->school_id
            ? optional(School::find($user->school_id))->timezone
            : School::query()->value('timezone');

        return Carbon::today($tz ?: config('app.timezone'));
    }

    protected function getStats(): array
    {
        $today = $this->today();
        $dayRange = [$today->copy()->startOfDay(), $today->copy()->endOfDay()];

        $students = $this->scope(Student::query())->count();

        $onCampus = $this->scope(Student::query())
            ->whereHas('tapEvents', fn (Builder $q) => $q->whereBetween('tapped_at', $dayRange))
            ->count();

        $lateToday = $this->scope(TapEvent::query(), 'student_id')
            ->whereBetween('tapped_at', $dayRange)
            ->where('direction', 'in')
            ->where('is_late', true)
            ->distinct('student_id')
            ->count('student_id');

        $gatesOnline = $this->scope(Gate::query())->where('status', 'online')->count();
        $gatesTotal = $this->scope(Gate::query())->count();

        $codesOutstanding = $this->scope(LinkCode::query())->usable()->count();

        return [
            Stat::make('Students', $students)
                ->description('Enrolled')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary'),

            Stat::make('On campus today', $onCampus)
                ->description(max($students - $onCampus, 0).' not tapped in')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Late arrivals today', $lateToday)
                ->description('Tapped in after the cutoff')
                ->descriptionIcon('heroicon-m-clock')
                ->color($lateToday > 0 ? 'warning' : 'gray'),

            Stat::make('Gates online', "{$gatesOnline} / {$gatesTotal}")
                ->description($gatesTotal > 0 && $gatesOnline === $gatesTotal ? 'All reporting' : 'Some offline')
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->color($gatesTotal > 0 && $gatesOnline === $gatesTotal ? 'success' : 'danger'),

            Stat::make('Codes outstanding', $codesOutstanding)
                ->description('Issued, not yet redeemed')
                ->descriptionIcon('heroicon-m-ticket')
                ->color($codesOutstanding > 0 ? 'warning' : 'gray'),
        ];
    }
}
