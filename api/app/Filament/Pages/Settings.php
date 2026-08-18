<?php

namespace App\Filament\Pages;

use App\Support\ActivityLogger;
use App\Support\Settings as SokoniSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * "Editable platform values... read from config with database overrides"
 * (CLAUDE.md admin rebuild, Section 6). Admin-only — see canAccess()
 * below, real enforcement via User::isAdminRole() rather than just being
 * left off Staff's navigation. Every field here corresponds to a real
 * enforcement point elsewhere in the app (see App\Support\Settings'
 * own docblock for exactly where each one is read) — not a control panel
 * that looks real but does nothing.
 */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected string $view = 'filament.pages.settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdminRole() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(SokoniSettings::all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Discovery')
                    ->description('Where the "Near You" feed and product search default to.')
                    ->schema([
                        TextInput::make('search_radius_km')
                            ->label('Default search radius (km)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(100),
                    ]),
                Section::make('Listings')
                    ->schema([
                        TextInput::make('max_media_per_product')
                            ->label('Maximum photos/videos per product')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(20),
                    ]),
                Section::make('Offers')
                    ->schema([
                        TextInput::make('offer_max_duration_days')
                            ->label('Maximum Offer duration (days)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(30),
                    ]),
                Section::make('Moderation')
                    ->schema([
                        TextInput::make('report_auto_hide_threshold')
                            ->label('Upheld reports before auto-hide')
                            ->helperText('Content is automatically hidden pending review once this many reports against it have been upheld.')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(20),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save changes')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            SokoniSettings::set($key, $value);
        }

        ActivityLogger::record(auth()->user(), 'settings.updated', null, null, $data);

        Notification::make()->title('Settings saved')->success()->send();
    }

    public function getTitle(): string
    {
        return 'Settings';
    }
}
