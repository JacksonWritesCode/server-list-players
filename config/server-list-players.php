<?php

return [
    // How long a query result is shared between all users/requests (seconds).
    // Keeps game servers from being hammered when several people have the list open.
    'cache_seconds' => (int) env('SERVER_LIST_PLAYERS_CACHE', 20),

    // How often the browser refreshes the counts while the list is open (seconds, min 10).
    'poll_seconds' => (int) env('SERVER_LIST_PLAYERS_POLL', 30),

    // Show a muted "0" badge on offline servers instead of hiding the badge.
    'show_offline' => (bool) env('SERVER_LIST_PLAYERS_SHOW_OFFLINE', false),
];
