<?php

namespace ServerListPlayers;

use App\Filament\App\Resources\Servers\Pages\ListServers;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

class ServerListPlayersPlugin implements Plugin
{
    public function getId(): string
    {
        return 'server-list-players';
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void
    {
        if ($panel->getId() !== 'app') {
            return;
        }

        // Mounted once at the end of the <body>, only on the server list page.
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => Blade::render("@livewire('server-list-player-counts')"),
            scopes: ListServers::class,
        );
    }
}
