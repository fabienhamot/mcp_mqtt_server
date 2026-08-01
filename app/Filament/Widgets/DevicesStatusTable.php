<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DeviceResource;
use App\Models\Device;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class DevicesStatusTable extends BaseWidget
{
    protected static ?string $heading = 'Statut des dispositifs';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Device::query()->latest('updated_at'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('mqtt_topic')->copyable()->limit(30),
                Tables\Columns\TextColumn::make('connectivity')
                    ->label('État')
                    ->badge()
                    ->getStateUsing(fn (Device $record): string => $record->connectivityLabel())
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'online' => 'En ligne',
                        'offline' => 'Hors ligne',
                        default => 'Jamais vu',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'offline' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Dernière vue')
                    ->since()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status.last_command.type')
                    ->label('Dernière cmd')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status.last_command_at')
                    ->label('Cmd à')
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Ouvrir')
                    ->url(fn (Device $record): string => DeviceResource::getUrl('edit', ['record' => $record])),
            ])
            ->paginated([5, 10]);
    }
}
