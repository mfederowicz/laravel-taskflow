<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Laravel\Passport\Token;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->disabled(),
                Forms\Components\TextInput::make('email')
                    ->disabled(),
                Forms\Components\TextInput::make('role')
                    ->formatStateUsing(fn (UserRole $state) => $state->label())
                    ->disabled(),
                Forms\Components\TextInput::make('status')
                    ->formatStateUsing(fn (UserStatus $state) => $state->label())
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (UserStatus $state): string => match ($state) {
                        UserStatus::Active => 'success',
                        UserStatus::Locked => 'danger',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options(collect(UserRole::cases())->mapWithKeys(
                        fn (UserRole $role) => [$role->value => $role->label()]
                    )),
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(UserStatus::cases())->mapWithKeys(
                        fn (UserStatus $status) => [$status->value => $status->label()]
                    )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('lock')
                    ->label('Lock')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => auth()->user()->can('lock', $record))
                    ->action(function (User $record): void {
                        $record->update(['status' => UserStatus::Locked]);
                        $record->tokens()->delete();
                        Token::where('user_id', $record->id)->update(['revoked' => true]);

                        Notification::make()
                            ->title('User locked')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('unlock')
                    ->label('Unlock')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => auth()->user()->can('unlock', $record))
                    ->action(function (User $record): void {
                        $record->update(['status' => UserStatus::Active]);

                        Notification::make()
                            ->title('User unlocked')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('changeRole')
                    ->label('Change role')
                    ->icon('heroicon-o-identification')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => auth()->user()->can('updateRole', $record))
                    ->form([
                        Forms\Components\Select::make('role')
                            ->options(collect(UserRole::cases())->mapWithKeys(
                                fn (UserRole $role) => [$role->value => $role->label()]
                            ))
                            ->required(),
                    ])
                    ->fillForm(fn (User $record): array => ['role' => $record->role->value])
                    ->action(function (User $record, array $data): void {
                        $record->update(['role' => $data['role']]);

                        Notification::make()
                            ->title('Role updated')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('resetPassword')
                    ->label('Reset password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => auth()->user()->can('resetPassword', $record))
                    ->form([
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required()
                            ->minLength(8),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update(['password' => bcrypt($data['password'])]);

                        Notification::make()
                            ->title('Password reset')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
        ];
    }
}
