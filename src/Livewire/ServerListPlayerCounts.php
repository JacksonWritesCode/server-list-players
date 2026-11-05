<?php

namespace ServerListPlayers\Livewire;

use App\Models\Allocation;
use App\Models\Server;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Invisible component mounted on the server list. The browser sends the short
 * ids of the server cards it can see, this class answers with the player count
 * for each one the user is allowed to access, and a small script paints the
 * badges onto the cards (see resources/views/counts.blade.php).
 *
 * Player data comes from Boy132's Player Counter plugin (Game Query linked to
 * the server's egg), so anything that plugin can query shows up here as well.
 */
class ServerListPlayerCounts extends Component
{
    private const MAX_IDS = 60;

    /**
     * @param  array<int, mixed>  $identifiers  Server uuid_short values from the visible cards
     * @return array<string, array{state: string, name: string, current?: int, max?: int|null}>
     */
    #[Renderless]
    public function counts(array $identifiers): array
    {
        $user = user();

        if (!$user) {
            return [];
        }

        $ids = collect($identifiers)
            ->filter(fn ($id) => is_string($id) && preg_match('/^[A-Za-z0-9_-]{1,32}$/', $id))
            ->unique()
            ->take(self::MAX_IDS)
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        // Only ever answer for servers this user can access. Using a sub-select keeps any
        // OR-conditions inside accessibleServers() from escaping into our own filter.
        $accessible = method_exists($user, 'accessibleServers')
            ? $user->accessibleServers()->select('servers.id')
            : Server::query()->where('owner_id', $user->id)->select('servers.id');

        $servers = Server::query()
            ->whereIn('uuid_short', $ids)
            ->whereIn('servers.id', $accessible)
            ->with(['egg', 'allocation'])
            ->get();

        $result = [];

        foreach ($servers as $server) {
            /** @var Server $server */
            $result[$server->uuid_short] = ['name' => $server->name] + $this->resolve($server);
        }

        return $result;
    }

    /**
     * @return array{state: string, current?: int, max?: int|null}
     */
    private function resolve(Server $server): array
    {
        $ttl = max(5, (int) config('server-list-players.cache_seconds', 20));

        return Cache::remember("server-list-players:{$server->id}", $ttl, function () use ($server): array {
            try {
                return $this->query($server);
            } catch (Throwable $exception) {
                report($exception);

                return ['state' => 'unknown'];
            }
        });
    }

    /**
     * @return array{state: string, current?: int, max?: int|null}
     */
    private function query(Server $server): array
    {
        // Player Counter not installed/enabled: nothing to show.
        if (!class_exists(\Boy132\PlayerCounter\Models\GameQuery::class)) {
            return ['state' => 'none'];
        }

        // Player Counter adds this relation to the Egg model at runtime.
        $gameQuery = $server->egg?->gameQuery; // @phpstan-ignore property.notFound

        if (!$gameQuery) {
            return ['state' => 'none'];
        }

        if ($this->isOffline($server)) {
            return ['state' => 'offline'];
        }

        $data = $this->runGameQuery($gameQuery, $server);

        if (!is_array($data) || !isset($data['current_players'])) {
            return ['state' => 'unknown'];
        }

        return [
            'state' => 'online',
            'current' => (int) $data['current_players'],
            'max' => isset($data['max_players']) ? (int) $data['max_players'] : null,
        ];
    }

    /**
     * Calls GameQuery::runQuery() without hard-coding its signature. Player Counter
     * has changed it before (Allocation only, then Allocation + Server schemas), so the
     * arguments are built from the parameter types the installed version declares.
     *
     * @return array<string, mixed>|null
     */
    private function runGameQuery(object $gameQuery, Server $server): ?array
    {
        if (!$server->allocation) {
            return null;
        }

        $arguments = [];

        foreach ((new ReflectionMethod($gameQuery, 'runQuery'))->getParameters() as $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

            if ($typeName === Server::class || $parameter->getName() === 'server') {
                $arguments[] = $server;
            } elseif ($typeName === Allocation::class || $parameter->getName() === 'allocation') {
                $arguments[] = $server->allocation;
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            } else {
                return null; // Unknown signature, better to show "unavailable" than to guess.
            }
        }

        $data = $gameQuery->runQuery(...$arguments);

        return is_array($data) ? $data : null;
    }

    /**
     * Skips the (slow, usually timing-out) game query for servers that are not running.
     * If the status can't be determined we simply try the query anyway.
     */
    private function isOffline(Server $server): bool
    {
        if (!method_exists($server, 'retrieveStatus')) {
            return false;
        }

        try {
            $status = $server->retrieveStatus();
        } catch (Throwable) {
            return false;
        }

        $value = $status instanceof \BackedEnum ? $status->value : (is_string($status) ? $status : null);

        return in_array($value, ['offline', 'missing', 'exited', 'dead'], true);
    }

    public function render(): View
    {
        return view('server-list-players::counts', [
            'pollSeconds' => max(10, (int) config('server-list-players.poll_seconds', 30)),
            'showOffline' => (bool) config('server-list-players.show_offline', false),
        ]);
    }
}
