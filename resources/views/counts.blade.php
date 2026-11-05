<div wire:ignore hidden aria-hidden="true" data-server-list-players>
    <style>
        .slp-badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .15rem .6rem;
            border-radius: 999px;
            font-size: .8125rem;
            line-height: 1.25rem;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            color: inherit;
            background: color-mix(in srgb, currentColor 9%, transparent);
            border: 1px solid color-mix(in srgb, currentColor 14%, transparent);
            /* Pushes the badge to the right edge when the title row is a flex container. */
            margin-inline-start: auto;
        }
        .slp-badge svg { width: 1rem; height: 1rem; flex: none; opacity: .8; }
        .slp-badge.slp-active { color: rgb(34 197 94); }
        .slp-badge.slp-idle { opacity: .8; }
        .slp-badge.slp-unknown { opacity: .5; }

        /* Fallback used only when the card's title can't be located: pin to the card's top-right. */
        .slp-badge.slp-floating {
            position: absolute;
            top: var(--slp-top, 1.25rem);
            right: var(--slp-right, 1.5rem);
            margin: 0;
            z-index: 2;
        }
    </style>
</div>

@script
<script>
    const POLL_MS = {{ (int) $pollSeconds }} * 1000;
    const SHOW_OFFLINE = @js($showOffline);
    const TEXT = @js([
        'online' => trans('server-list-players::strings.players_online'),
        'unavailable' => trans('server-list-players::strings.unavailable'),
    ]);

    // Pelican's server cards are not links: the card is a <div> whose Alpine click handler calls
    // Livewire.navigate('https://panel/server/<uuid_short>/console'). We also accept plain <a href>.
    const SERVER_URL = /\/server\/([A-Za-z0-9_-]{1,32})(?:[/?#'"]|$)/;
    const CARD_SELECTOR = '[x-on\\:click*="/server/"], a[href*="/server/"]';
    const ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';

    const root = $wire.$el;
    let data = {};
    let loading = false;
    let debounce = null;
    let warned = false;

    const area = (el) => { const r = el.getBoundingClientRect(); return r.width * r.height; };

    // id => the element that represents the whole card
    const cards = () => {
        const found = new Map();

        document.querySelectorAll(CARD_SELECTOR).forEach((el) => {
            if (el.closest('[data-slp-badge]')) return;

            const source = el.getAttribute('x-on:click') ?? el.getAttribute('href') ?? '';
            const match = SERVER_URL.exec(source);
            if (!match) return;

            const current = found.get(match[1]);
            if (!current || area(el) > area(current)) found.set(match[1], el);
        });

        return found;
    };

    // The text node holding the server's name -> its element.
    const findTitle = (host, name) => {
        const walker = document.createTreeWalker(host, NodeFilter.SHOW_TEXT);
        let partial = null;

        while (walker.nextNode()) {
            const node = walker.currentNode;
            if (node.parentElement.closest('[data-slp-badge]')) continue;

            const text = node.nodeValue.trim();
            if (text === name) return node.parentElement;
            if (!partial && text.startsWith(name)) partial = node.parentElement;
        }

        return partial;
    };

    const label = (info) => {
        if (!info) return null;
        if (info.state === 'online') {
            return {
                text: info.max ? `${info.current} / ${info.max}` : `${info.current}`,
                cls: info.current > 0 ? 'slp-active' : 'slp-idle',
                title: TEXT.online,
            };
        }
        if (info.state === 'offline') {
            return SHOW_OFFLINE ? { text: '0', cls: 'slp-unknown', title: TEXT.online } : null;
        }
        if (info.state === 'unknown') return { text: '—', cls: 'slp-unknown', title: TEXT.unavailable };
        return null; // 'none': no Game Query linked to this egg
    };

    const place = (badge, host, name) => {
        const title = findTitle(host, name);

        if (title) {
            // Put it in the title row (the nearest horizontal flex ancestor), right of the name/uptime/power button.
            let row = null;
            for (let el = title.parentElement; el && el !== host.parentElement; el = el.parentElement) {
                const style = window.getComputedStyle(el);
                if (style.display.includes('flex') && !style.flexDirection.startsWith('column')) { row = el; break; }
            }
            row ??= title.parentElement ?? title;

            badge.classList.remove('slp-floating');
            if (badge.parentElement !== row) row.appendChild(badge);
            return;
        }

        // Fallback: pin to the card's top-right corner.
        if (window.getComputedStyle(host).position === 'static') host.style.position = 'relative';
        badge.classList.add('slp-floating');
        if (badge.parentElement !== host) host.appendChild(badge);
    };

    const decorate = () => {
        const found = cards();

        // Drop badges whose card disappeared (pagination, filtering, tab change).
        document.querySelectorAll('[data-slp-badge]').forEach((badge) => {
            if (!found.has(badge.dataset.slpBadge)) badge.remove();
        });

        found.forEach((host, id) => {
            const info = data[id];
            const view = label(info);
            let badge = document.querySelector(`[data-slp-badge="${CSS.escape(id)}"]`);

            if (!view) { badge?.remove(); return; }

            if (!badge) {
                badge = document.createElement('span');
                badge.dataset.slpBadge = id;
            }

            const html = `${ICON}<span>${view.text}</span>`;
            const cls = `slp-badge ${view.cls}`;
            const floating = badge.classList.contains('slp-floating') ? ' slp-floating' : '';

            if (badge.innerHTML !== html) badge.innerHTML = html;
            if (badge.title !== view.title) badge.title = view.title;
            if (badge.dataset.slpClass !== cls) {
                badge.className = cls + floating;
                badge.dataset.slpClass = cls;
            }

            place(badge, host, info.name);
        });
    };

    const refresh = async () => {
        if (loading || document.hidden || !root.isConnected) return;

        const ids = [...cards().keys()];

        if (ids.length === 0) {
            if (!warned && document.readyState === 'complete') {
                warned = true;
                console.debug('[server-list-players] No server cards found on this page yet.');
            }
            return;
        }

        loading = true;
        try {
            data = { ...data, ...(await $wire.counts(ids)) };
            decorate();
        } catch (error) {
            console.debug('[server-list-players] Failed to load player counts', error);
        } finally {
            loading = false;
        }
    };

    // Cards load lazily and Livewire re-renders them while polling, which wipes our badge.
    // Re-apply on any DOM change; only hit the server when a new card shows up.
    const observer = new MutationObserver(() => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            if (!root.isConnected) { stop(); return; }
            const unseen = [...cards().keys()].some((id) => !(id in data));
            unseen ? refresh() : decorate();
        }, 80);
    });

    const timer = setInterval(() => { root.isConnected ? refresh() : stop(); }, POLL_MS);

    function stop() {
        clearInterval(timer);
        clearTimeout(debounce);
        observer.disconnect();
        document.querySelectorAll('[data-slp-badge]').forEach((badge) => badge.remove());
    }

    observer.observe(document.body, { childList: true, subtree: true });
    refresh();
</script>
@endscript
