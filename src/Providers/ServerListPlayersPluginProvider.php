<?php

namespace ServerListPlayers\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use ServerListPlayers\Livewire\ServerListPlayerCounts;

class ServerListPlayersPluginProvider extends ServiceProvider
{
    public function boot(): void
    {
        Livewire::component('server-list-player-counts', ServerListPlayerCounts::class);
    }
}
