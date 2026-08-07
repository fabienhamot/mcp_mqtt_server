<?php

namespace App\Filament\Resources;

use App\Enums\DisplayPriority;
use App\Filament\Resources\DeviceResource\Pages;
use App\Filament\Resources\DeviceResource\RelationManagers;
use App\Models\Device;
use App\Models\User;
use App\Services\DeviceCommandService;
use App\Services\DisplayCommandService;
use App\Services\DisplayPayload;
use App\Support\DeviceCapabilityCatalog;
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
                Forms\Components\Select::make('type')
                    ->label('Type')
                    ->options([
                        'led_display' => 'Écran LED',
                        'relay' => 'Relais',
                        'generic' => 'Générique',
                    ])
                    ->default('led_display')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Forms\Set $set): void {
                        $caps = match ($state) {
                            'led_display' => DeviceCapabilityCatalog::ledDisplay(),
                            'relay' => DeviceCapabilityCatalog::relayExample(),
                            default => DeviceCapabilityCatalog::empty(),
                        };
                        $set(
                            'capabilities_json',
                            json_encode(
                                ['commands' => $caps['commands']],
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                            )
                        );
                        $set('status_items', $caps['status_items'] ?? []);
                    }),
                Forms\Components\TextInput::make('mqtt_topic')
                    ->label('Topic MQTT (commandes)')
                    ->helperText('Ex. cmnd/tasmota_XXXXXX/POWER ou display/led/cuisine')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('status_topic')
                    ->label('Topic statut (optionnel, legacy)')
                    ->helperText('Un seul topic (Tasmota STATE…). Préférer les éléments custom ci-dessous pour plusieurs états.')
                    ->maxLength(255),
                Forms\Components\Repeater::make('status_items')
                    ->label('Éléments d\'état (custom)')
                    ->helperText('Chaque élément écoute un topic MQTT et stocke la valeur dans status.{key}. Ex. porte Shelly : topic …/status/input:0, path=state, map true→open / false→closed.')
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->label('Clé')
                            ->required()
                            ->maxLength(64)
                            ->helperText('ex. door, relay, online'),
                        Forms\Components\TextInput::make('label')
                            ->label('Libellé')
                            ->maxLength(255)
                            ->helperText('Affiché dans l\'app / admin'),
                        Forms\Components\TextInput::make('topic')
                            ->label('Topic MQTT')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('path')
                            ->label('Chemin JSON')
                            ->maxLength(255)
                            ->helperText('ex. state, output — vide = payload entier'),
                        Forms\Components\KeyValue::make('map')
                            ->label('Mapping valeurs')
                            ->keyLabel('Brut')
                            ->valueLabel('Affiché')
                            ->helperText('ex. true → open, false → closed')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->default([])
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => ($state['label'] ?? null) ?: ($state['key'] ?? null))
                    ->addActionLabel('Ajouter un élément d\'état')
                    ->columnSpanFull(),
                // Champs virtuels string-only : conversion JSON → array hors Livewire (mutateFormDataBeforeSave)
                Forms\Components\Textarea::make('capabilities_json')
                    ->label('Capabilities (JSON — commandes)')
                    ->helperText('Catalogue commands + params + payload. Les status_items se configurent dans le répéteur ci-dessus.')
                    ->rows(16)
                    ->required()
                    ->columnSpanFull()
                    ->default(fn (): string => json_encode(
                        ['commands' => DeviceCapabilityCatalog::ledDisplay()['commands']],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    )),
                Forms\Components\Textarea::make('status_json')
                    ->label('Statut (JSON)')
                    ->helperText('État MQTT / dernières commandes.')
                    ->rows(8)
                    ->columnSpanFull()
                    ->default('{}'),
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
                Tables\Columns\TextColumn::make('commands')
                    ->label('Commandes')
                    ->getStateUsing(fn (Device $record): string => implode(', ', $record->commandNames()) ?: '—')
                    ->wrap()
                    ->toggleable(),
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
                Tables\Actions\Action::make('invoke')
                    ->label('Commande')
                    ->icon('heroicon-o-paper-airplane')
                    ->form(function (Device $record): array {
                        $names = $record->commandNames();

                        return [
                            Forms\Components\Select::make('command')
                                ->label('Commande')
                                ->options(collect($names)->mapWithKeys(fn (string $n) => [$n => $n])->all())
                                ->required()
                                ->live(),
                            Forms\Components\Textarea::make('params_json')
                                ->label('Params (JSON)')
                                ->helperText('Ex. {"text":"Hello","priority":"normal"} ou {"on":true}')
                                ->rows(4)
                                ->default('{}'),
                        ];
                    })
                    ->action(function (Device $record, array $data): void {
                        /** @var User $user */
                        $user = auth()->user();
                        $params = [];
                        if (! blank($data['params_json'] ?? null)) {
                            try {
                                $decoded = json_decode($data['params_json'], true, 512, JSON_THROW_ON_ERROR);
                                $params = is_array($decoded) ? $decoded : [];
                            } catch (Throwable $e) {
                                Notification::make()->title('JSON params invalide')->body($e->getMessage())->danger()->send();

                                return;
                            }
                        }

                        try {
                            $result = app(DeviceCommandService::class)->invoke(
                                $user,
                                $record->id,
                                (string) $data['command'],
                                $params,
                            );
                            Notification::make()
                                ->title('Commande publiée')
                                ->body("{$result['command']} → {$result['topic']} (log #{$result['log_id']})")
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Échec')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('sendText')
                    ->label('Texte')
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->visible(fn (Device $record): bool => in_array('text', $record->commandNames(), true))
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
                    ->visible(fn (Device $record): bool => in_array('color', $record->commandNames(), true))
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
                    ->visible(fn (Device $record): bool => in_array('clear', $record->commandNames(), true))
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
