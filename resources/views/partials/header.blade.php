<header class="topbar"> 
    @php 
        $permissionService = app(\App\Services\PermissionService::class); 
 
        $staticUser = session('static_auth_user', [ 
            'name' => 'Arjun Sharma', 
            'role' => 'Administrator', 
            'initials' => 'AS', 
        ]); 
 
        $isAdminLoggedIn = filled(session('static_auth_user.admin_id'));

        $selectedCentre = session('selected_centre');
    @endphp 
 
    <div class="d-flex align-items-center gap-3"> 
 
        <button 
            class="icon-btn" 
            id="sidebarToggle" 
            aria-label="Toggle sidebar"
        > 
            <i class="fa-solid fa-bars"></i> 
        </button> 
 
        <div 
            class="global-search" 
            data-global-search 
            data-search-url="{{ route('global-search.index') }}" 
            role="search"
        > 
 
            <i class="fa-solid fa-magnifying-glass"></i> 
 
            <input 
                id="globalSearchInput" 
                type="search" 
                placeholder="Search assets, tickets, users…" 
                aria-label="Global search" 
                aria-autocomplete="list" 
                aria-controls="globalSearchResults" 
                aria-expanded="false" 
                autocomplete="off" 
                maxlength="100" 
            > 
 
            <div 
                class="global-search-results" 
                id="globalSearchResults" 
                role="listbox" 
                hidden 
            ></div> 
 
        </div> 
 
    </div> 
 
    <div class="topbar-actions"> 
 
        {{-- =========================================
             Centre Filter - Admin Only
        ========================================== --}} 
        @if($isAdminLoggedIn) 

            <div class="dropdown centre-filter-dropdown"> 

                <button 
                    class="centre-filter-btn" 
                    type="button" 
                    data-bs-toggle="dropdown" 
                    aria-expanded="false"
                > 

                    <i class="fa-solid fa-location-dot"></i> 

                    <span> 
                        @switch($selectedCentre)

                            @case('noida')
                                Noida
                                @break

                            @case('lucknow')
                                Lucknow
                                @break

                            @case(null)
                                Select Centre
                                @break

                            @default
                                All Centres

                        @endswitch
                    </span> 

                    <i class="fa-solid fa-chevron-down"></i> 

                </button> 

                <ul class="dropdown-menu dropdown-menu-end"> 

                    {{-- All Centres --}}
                    <li> 
                        <a 
                            class="dropdown-item {{ $selectedCentre === 'all' ? 'active' : '' }}" 
                            href="{{ route('centre.switch', ['centre' => 'all']) }}"
                        > 
                            <i class="fa-solid fa-globe me-2"></i> 
                            All Centres 
                        </a> 
                    </li> 

                    {{-- Noida --}}
                    <li> 
                        <a 
                            class="dropdown-item {{ $selectedCentre === 'noida' ? 'active' : '' }}" 
                            href="{{ route('centre.switch', ['centre' => 'noida']) }}"
                        > 
                            <i class="fa-solid fa-location-dot me-2"></i> 
                            Noida 
                        </a> 
                    </li> 

                    {{-- Lucknow --}}
                    <li> 
                        <a 
                            class="dropdown-item {{ $selectedCentre === 'lucknow' ? 'active' : '' }}" 
                            href="{{ route('centre.switch', ['centre' => 'lucknow']) }}"
                        > 
                            <i class="fa-solid fa-location-dot me-2"></i> 
                            Lucknow 
                        </a> 
                    </li> 

                </ul> 

            </div> 

        @endif 
 
 
        {{-- =========================================
             Fullscreen
        ========================================== --}} 
        <button 
            class="icon-btn" 
            id="fullscreenToggle" 
            aria-label="Toggle Fullscreen"
        > 
            <i class="fa-solid fa-expand"></i> 
        </button> 
 
 
        {{-- =========================================
             Notifications
        ========================================== --}} 
        @if($permissionService->allows('notifications')) 

            <div class="dropdown"> 

                <button 
                    class="icon-btn position-relative" 
                    data-bs-toggle="dropdown" 
                    aria-label="Notifications"
                > 
                    <i class="fa-regular fa-bell"></i> 

                    @if($headerUnreadCount) 
                        <span class="notification-dot"></span> 
                    @endif 
                </button> 

                <div class="dropdown-menu dropdown-menu-end notification-menu p-0"> 

                    <div class="dropdown-heading"> 
                        Notifications 

                        @if($headerUnreadCount) 
                            <span class="badge text-bg-primary"> 
                                {{ $headerUnreadCount }} new 
                            </span> 
                        @endif 
                    </div> 

                    @forelse($headerNotifications as $headerNotification) 

                        <a 
                            class="dropdown-item" 
                            href="{{ route('notifications.index') }}"
                        > 
                            <span class="notice-icon {{ $headerNotification->severity === 'critical' ? 'danger' : $headerNotification->severity }}"> 
                                <i class="fa-solid fa-bell"></i> 
                            </span> 

                            <span> 
                                {{ Str::limit($headerNotification->title, 35) }} 

                                <small> 
                                    {{ $headerNotification->module }} 
                                    · 
                                    {{ $headerNotification->created_at->diffForHumans() }} 
                                </small> 
                            </span> 
                        </a> 

                    @empty 

                        <div class="p-4 text-center text-secondary small"> 
                            No notifications yet. 
                        </div> 

                    @endforelse 

                    <a 
                        class="notification-view-all" 
                        href="{{ route('notifications.index') }}"
                    > 
                        View all notifications 
                        <i class="fa-solid fa-arrow-right"></i> 
                    </a> 

                </div> 

            </div> 

        @endif 
 
 
        {{-- =========================================
             Profile
        ========================================== --}} 
        <div class="dropdown"> 

            <button 
                class="profile-btn" 
                data-bs-toggle="dropdown"
            > 

                @if(filled($staticUser['image_path'] ?? null)) 

                    <img 
                        src="{{ \App\Support\PublicUrl::storage($staticUser['image_path']) }}" 
                        alt="{{ $staticUser['name'] }}" 
                        class="avatar" 
                        style="object-fit:cover" 
                        onerror="this.hidden=true;this.nextElementSibling.hidden=false"
                    > 

                    <span class="avatar" hidden> 
                        {{ $staticUser['initials'] }} 
                    </span> 

                @else 

                    <span class="avatar"> 
                        {{ $staticUser['initials'] }} 
                    </span> 

                @endif 

                <span class="profile-copy"> 
                    <strong>{{ $staticUser['name'] }}</strong> 
                    <small>{{ $staticUser['role'] }}</small> 
                </span> 

                <i class="fa-solid fa-chevron-down"></i> 

            </button> 

            <ul class="dropdown-menu dropdown-menu-end"> 

                @if($permissionService->allows('settings')) 

                    <li> 
                        <a 
                            class="dropdown-item" 
                            href="{{ route('settings.index') }}"
                        > 
                            <i class="fa-regular fa-user me-2"></i> 
                            My profile 
                        </a> 
                    </li> 

                    <li> 
                        <a 
                            class="dropdown-item" 
                            href="{{ route('settings.index') }}"
                        > 
                            <i class="fa-solid fa-gear me-2"></i> 
                            Settings 
                        </a> 
                    </li> 

                    <li> 
                        <hr class="dropdown-divider"> 
                    </li> 

                @endif 

                <li> 
                    <form method="POST" action="{{ route('logout') }}"> 
                        @csrf 

                        <button 
                            class="dropdown-item text-danger" 
                            type="submit"
                        > 
                            <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> 
                            Sign out 
                        </button> 

                    </form> 
                </li> 

            </ul> 

        </div> 

    </div> 
</header>