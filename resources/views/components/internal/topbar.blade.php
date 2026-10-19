@props(['user', 'unreadCount', 'title'])

<header class="shell-topbar">
    <button type="button" class="icon-button sidebar-trigger" data-sidebar-open aria-controls="internal-sidebar" aria-expanded="false" aria-label="Buka navigasi">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <span class="topbar-context">{{ $title }}</span>
    <div class="topbar-actions">
        <details class="shell-popover notification-popover">
            <summary class="icon-button" aria-label="Notifikasi: {{ $unreadCount }} belum dibaca">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                @if($unreadCount > 0)<span class="notification-count" aria-hidden="true">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>@endif
            </summary>
            <div class="popover-panel">
                <strong>Notifikasi</strong>
                <p>{{ $unreadCount === 0 ? 'Tidak ada notifikasi yang belum dibaca.' : $unreadCount.' notifikasi belum dibaca.' }}</p>
                <p class="muted">Pusat notifikasi akan tersedia pada tahap berikutnya.</p>
            </div>
        </details>
        <details class="shell-popover account-popover">
            <summary class="account-summary" aria-label="Akun {{ $user->name }}">
                <span class="user-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                <span class="user-identity"><strong>{{ $user->name }}</strong><small>{{ $user->role->label() }}</small></span>
                <span aria-hidden="true">&#8964;</span>
            </summary>
            <div class="popover-panel">
                <strong>{{ $user->name }}</strong>
                <p>{{ $user->role->label() }}</p>
                <form method="POST" action="{{ route('logout') }}" data-pending-form>
                    @csrf
                    <button type="submit" class="primary-button" data-pending-label="Sedang keluar...">Keluar</button>
                </form>
            </div>
        </details>
    </div>
</header>
