<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DisplayLogResource\Pages;
use App\Models\DisplayLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DisplayLogResource extends Resource
{
    protected static ?string $model = DisplayLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Observabilité';

    protected static ?string $modelLabel = 'log d\'affichage';

    protected static ?string $pluralModelLabel = 'logs d\'affichage';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('device.name')->label('Device')->searchable(),
                Tables\Columns\TextColumn::make('user.email')->label('User')->searchable(),
                Tables\Columns\TextColumn::make('payload.type')
                    ->label('Type')
                    ->badge(),
                Tables\Columns\TextColumn::make('payload.content')
                    ->label('Content')
                    ->limit(40)
                    ->tooltip(fn (DisplayLog $record): ?string => $record->payload['content'] ?? null),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('device_id')
                    ->relationship('device', 'name')
                    ->label('Device'),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDisplayLogs::route('/'),
        ];
    }
}
