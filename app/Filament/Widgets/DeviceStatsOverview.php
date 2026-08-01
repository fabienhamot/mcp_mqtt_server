<?php

namespace App\Filament\Widgets;

use App\Models\Device;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DeviceStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $devices = Device::query()->get(['id', 'last_seen_at']);

        $online = $devices->filter(fn (Device $d) => $d->isOnline())->count();
        $offline = $devices->filter(fn (Device $d) => $d->last_seen_at !== null && ! $d->isOnline())->count();
        $never = $devices->filter(fn (Device $d) => $d->last_seen_at === null)->count();

        return [
            Stat::make('Devices', $devices->count())
                ->description('Total enregistrés')
                ->icon('heroicon-o-cpu-chip'),
            Stat::make('En ligne', $online)
                ->description('Vu < '.Device::ONLINE_THRESHOLD_SECONDS.'s')
                ->color('success')
                ->icon('heroicon-o-signal'),
            Stat::make('Hors ligne', $offline)
                ->color('warning')
                ->icon('heroicon-o-exclamation-triangle'),
            Stat::make('Jamais vu', $never)
                ->color('gray')
                ->icon('heroicon-o-question-mark-circle'),
        ];
    }
}
