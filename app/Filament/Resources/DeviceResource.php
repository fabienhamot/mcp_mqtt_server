<?php

namespace App\Filament\Resources;

use App\Enums\DisplayPriority;
use App\Filament\Resources\DeviceResource\Pages;
use App\Filament\Resources\DeviceResource\RelationManagers;
use App\Models\Device;
use App\Models\User;
use App\Services\DisplayCommandService;
use App\Services\DisplayPayload;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Throwable;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'Domotique';

    protected static ?string $modelLabel = 'dispositif';

    protected static ?string $pluralModelLabel = 'dispositifs';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('type')
                    ->default('led_display')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('mqtt_topic')
                    ->label('Topic MQTT')
                    ->helperText('Ex. display/led ou display/led/cuisine')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\KeyValue::make('status')
                    ->label('Statut (JSON)')
                    ->nullable()
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('last_seen_at')
                    ->label('Dernière activité')
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('mqtt_topic')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('connectivity')
                    ->label('Connexion')
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
                    ->label('Vu')
                    ->since()
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Perms'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type'),
            ])
            ->actions([
                Tables\Actions\Action::make('sendText')
                    ->label('Texte')
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->form([
                        Forms\Components\Textarea::make('text')->required()->rows(2),
                        Forms\Components\TextInput::make('duration')->numeric()->minValue(0)->label('Durée (s)'),
                        Forms\Components\Select::make('priority')
                            ->options([
                                'normal' => 'normal',
                                'high' => 'high',
                            ])
                            ->default('normal'),
                    ])
                    ->action(function (Device $record, array $data): void {
                        self::sendPayload($record, DisplayPayload::text(
                            $data['text'],
                            isset($data['duration']) && $data['duration'] !== '' ? (int) $data['duration'] : null,
                            DisplayPriority::from($data['priority'] ?? 'normal'),
                        ));
                    }),
                Tables\Actions\Action::make('sendColor')
                    ->label('Couleur')
                    ->icon('heroicon-o-swatch')
                    ->form([
                        Forms\Components\TextInput::make('color')
                            ->required()
                            ->placeholder('#ff0000 ou 255,0,0'),
                        Forms\Components\TextInput::make('duration')->numeric()->minValue(0)->label('Durée (s)'),
                    ])
                    ->action(function (Device $record, array $data): void {
                        self::sendPayload($record, DisplayPayload::color(
                            $data['color'],
                            isset($data['duration']) && $data['duration'] !== '' ? (int) $data['duration'] : null,
                        ));
                    }),
                Tables\Actions\Action::make('clear')
                    ->label('Clear')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Device $record) => self::sendPayload($record, DisplayPayload::clear())),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PermissionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevices::route('/'),
            'create' => Pages\CreateDevice::route('/create'),
            'edit' => Pages\EditDevice::route('/{record}/edit'),
        ];
    }

    private static function sendPayload(Device $device, DisplayPayload $payload): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $result = app(DisplayCommandService::class)->send($user, $device->id, $payload);
            Notification::make()
                ->title('Commande publiée sur MQTT')
                ->body("Topic {$result['device']->mqtt_topic} — log #{$result['log_id']}")
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Échec envoi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
