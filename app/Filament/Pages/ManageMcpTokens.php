<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Laravel\Passport\Token;
use Throwable;

class ManageMcpTokens extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Accès';

    protected static ?string $navigationLabel = 'Tokens MCP';

    protected static ?string $title = 'Tokens MCP (Passport)';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.manage-mcp-tokens';

    public ?array $data = [];

    public ?string $plainTextToken = null;

    public function mount(): void
    {
        $this->form->fill([
            'name' => 'mcp-agent',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Créer un token')
                    ->description('Le token en clair n\'est affiché qu\'une seule fois. Endpoint MCP : '.url('/mcp/led-display'))
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Utilisateur (agent)')
                            ->options(User::query()->orderBy('email')->pluck('email', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->label('Nom du token')
                            ->required()
                            ->maxLength(255)
                            ->default('mcp-agent'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function createToken(): void
    {
        $state = $this->form->getState();

        try {
            /** @var User $user */
            $user = User::query()->findOrFail($state['user_id']);
            $result = $user->createToken($state['name'], ['mcp:use']);

            $this->plainTextToken = $result->accessToken;
            $this->form->fill(['name' => 'mcp-agent', 'user_id' => $state['user_id']]);
            $this->resetTable();

            Notification::make()
                ->title('Token créé')
                ->body('Copiez-le maintenant — il ne sera plus visible.')
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Échec création token')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Token::query()
                    ->where('revoked', false)
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')->limit(12)->tooltip(fn (Token $record) => $record->id),
                Tables\Columns\TextColumn::make('name')->label('Nom')->searchable(),
                Tables\Columns\TextColumn::make('user_id')
                    ->label('User')
                    ->formatStateUsing(function ($state): string {
                        return User::query()->find($state)?->email ?? (string) $state;
                    }),
                Tables\Columns\TextColumn::make('scopes')
                    ->badge()
                    ->separator(','),
                Tables\Columns\TextColumn::make('expires_at')->dateTime()->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->since()->label('Créé'),
            ])
            ->actions([
                Tables\Actions\Action::make('revoke')
                    ->label('Révoquer')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function (Token $record): void {
                        $record->revoke();
                        Notification::make()->title('Token révoqué')->success()->send();
                    }),
            ])
            ->paginated([10, 25, 50]);
    }
}
