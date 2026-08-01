<?php

namespace App\Filament\Resources\DeviceResource\RelationManagers;

use App\Enums\DisplayAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PermissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'permissions';

    protected static ?string $title = 'Permissions utilisateurs';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Utilisateur')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\CheckboxList::make('allowed_actions')
                    ->label('Actions autorisées')
                    ->options(collect(DisplayAction::controllableValues())->mapWithKeys(
                        fn (string $v) => [$v => $v]
                    )->all())
                    ->required()
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Nom'),
                Tables\Columns\TextColumn::make('user.email')->label('Email'),
                Tables\Columns\TextColumn::make('allowed_actions')
                    ->badge()
                    ->separator(',')
                    ->label('Actions'),
                Tables\Columns\TextColumn::make('updated_at')->since()->label('Maj'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
