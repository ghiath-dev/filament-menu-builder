<?php

declare(strict_types=1);

namespace Datlechin\FilamentMenuBuilder\Concerns;

use Datlechin\FilamentMenuBuilder\FilamentMenuBuilderPlugin;
use Filament\Actions\Action;
use Filament\Forms\Components;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Collection;

trait HasLocationAction
{
    protected ?Collection $menus = null;

    protected ?Collection $menuLocations = null;

    public function getLocationAction(): Action
    {
        return Action::make('locations')
            ->label(__('filament-menu-builder::menu-builder.actions.locations.label'))
            ->modalHeading(__('filament-menu-builder::menu-builder.actions.locations.heading'))
            ->modalDescription(__('filament-menu-builder::menu-builder.actions.locations.description'))
            ->modalSubmitActionLabel(__('filament-menu-builder::menu-builder.actions.locations.submit'))
            ->modalWidth(MaxWidth::ExtraLarge)
            ->modalSubmitAction($this->getRegisteredLocations()->isEmpty() ? false : null)
            ->color('gray')
            ->fillForm(function () {
                return collect(config('filament-menu-builder.locales'))
                    ->flatMap(function ($label, $locale) {
                        return $this->getRegisteredLocations()->mapWithKeys(function ($location, $key) use ($locale) {
                            $menuLocation = $this->getMenuLocations()
                                ->where('location', $key)
                                ->where('locale', $locale)
                                ->first();

                            return [
                                "{$locale}_{$key}" => [
                                    'location' => $location,
                                    'locale' => $locale,
                                    'menu' => $menuLocation?->menu_id,
                                ]
                            ];
                        });
                    })->all();
            })
            ->action(function (array $data) {
                $locales = config('filament-menu-builder.locales');
                $locations = $this->getRegisteredLocations()->keys();

                foreach ($locales as $locale => $label) {
                    foreach ($locations as $location) {
                        $key = "{$locale}_{$location}";
                        $menu = $data[$key]['menu'] ?? null;

                        $modelClass = FilamentMenuBuilderPlugin::get()->getMenuLocationModel();

                        if (! $menu) {
                            $modelClass::where('location', $location)
                                ->where('locale', $locale)
                                ->delete();
                            continue;
                        }

                        $modelClass::updateOrCreate(
                            ['location' => $location, 'locale' => $locale],
                            ['menu_id' => $menu],
                        );
                    }
                }

                Notification::make()
                    ->title(__('filament-menu-builder::menu-builder.notifications.locations.title'))
                    ->success()
                    ->send();
            })
            ->form(collect(config('filament-menu-builder.locales'))
                ->flatMap(function ($label, $locale) {
                    return $this->getRegisteredLocations()->mapWithKeys(function ($location, $key) use ($locale, $label) {
                        return [
                            "{$locale}_{$key}" => Components\Grid::make(3)
                                ->statePath("{$locale}_{$key}")
                                ->schema([
                                    Components\TextInput::make('location')
                                        ->disabled()
                                        ->hiddenLabel(),
                                    Components\TextInput::make('locale')
                                        ->default($label)
                                        ->disabled()
                                        ->hiddenLabel(),
                                    Components\Select::make('menu')
                                        ->label(__('filament-menu-builder::menu-builder.actions.locations.form.menu.label'))
                                        ->searchable()
                                        ->options($this->getMenus()->pluck('name', 'id')->all())
                                        ->hiddenLabel(),
                                ])
                        ];
                    });
                })->all() ?: [
                Components\View::make('filament-tables::components.empty-state.index')
                    ->viewData([
                        'heading' => __('filament-menu-builder::menu-builder.actions.locations.empty.heading'),
                        'icon' => 'heroicon-o-x-mark',
                    ]),
            ]);
    }

    protected function getMenus(): Collection
    {
        return $this->menus ??= FilamentMenuBuilderPlugin::get()->getMenuModel()::all();
    }

    protected function getMenuLocations(): Collection
    {
        return $this->menuLocations ??= FilamentMenuBuilderPlugin::get()->getMenuLocationModel()::all();
    }

    protected function getRegisteredLocations(): Collection
    {
        return collect(FilamentMenuBuilderPlugin::get()->getLocations());
    }
}
