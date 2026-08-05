<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Laravel\Passport\Token;
use Throwable;

class TokensRelationManager extends RelationManager
{
    protected static string $relationship = 'tokens';

    protected static ?string $title = 'Tokens API / MCP';

    protected static ?string $modelLabel = 'token';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('revoked', false)->latest())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable(),
                Tables\Columns\TextColumn::make('scopes')
                    ->badge()
                    ->separator(','),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expire')
                    ->dateTime()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé')
                    ->since(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('createToken')
                    ->label('Créer un token')
                    ->icon('heroicon-o-key')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Nom du token')
                            ->required()
                            ->maxLength(255)
                            ->default('mobile-app')
                            ->helperText('Ex. iPhone Fabien, Claude Agent, Postman'),
                    ])
                    ->action(function (array $data): void {
                        /** @var User $user */
                        $user = $this->getOwnerRecord();

                        try {
                            $result = $user->createToken($data['name'], ['mcp:use']);

                            Notification::make()
                                ->title('Token créé — copiez-le maintenant')
                                ->body($result->accessToken)
                                ->success()
                                ->persistent()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Échec création token')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('revoke')
                    ->label('Révoquer')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Token $record): void {
                        $record->revoke();

                        Notification::make()
                            ->title('Token révoqué')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }
}
