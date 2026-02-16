/**
 * User Directory Module — Main Application Logic
 *
 * Features:
 * - Lazy loading with IntersectionObserver
 * - Search debounce (300ms)
 * - Delete with UI animation + DB sync
 * - Cache banner (3s fade)
 * - Dynamic total user count
 */

'use strict';

// =============================================================
// STATE
// =============================================================
const State = {
    offset: 0,
    limit: 10,
    loading: false,
    hasMore: true,
    searchMode: false,
    searchQuery: '',
    totalUsers: parseInt(document.getElementById('totalCount')?.textContent?.replace(/,/g, '') || '0', 10),
};

// =============================================================
// DOM REFERENCES
// =============================================================
const DOM = {
    userGrid:       document.getElementById('userGrid'),
    totalCount:     document.getElementById('totalCount'),
    totalBadge:     document.getElementById('totalBadge'),
    searchInput:    document.getElementById('searchInput'),
    clearSearch:    document.getElementById('clearSearch'),
    searchInfo:     document.getElementById('searchInfo'),
    loadingSpinner: document.getElementById('loadingSpinner'),
    noResults:      document.getElementById('noResults'),
    endOfResults:   document.getElementById('endOfResults'),
    cacheBanner:    document.getElementById('cacheBanner'),
    cacheBannerText:document.getElementById('cacheBannerText'),
    scrollSentinel: document.getElementById('scrollSentinel'),
};

// =============================================================
// UTILITY FUNCTIONS
// =============================================================

/**
 * Debounce function — delays execution until after `delay` ms of inactivity
 */
function debounce(fn, delay = 300) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}

/**
 * Format number with commas
 */
function formatNumber(num) {
    return num.toLocaleString('en-US');
}

/**
 * Update the total user count badge with animation
 */
function updateTotalCount(total) {
    State.totalUsers = total;
    DOM.totalCount.textContent = formatNumber(total);
    DOM.totalBadge.classList.add('count-updated');
    setTimeout(() => DOM.totalBadge.classList.remove('count-updated'), 400);
}

/**
 * Show cache banner — green for cache hit, blue for fresh DB
 */
function showCacheBanner(cached, loadTime) {
    // Remove previous classes
    DOM.cacheBanner.classList.remove('show', 'cache-hit', 'cache-miss');

    if (cached) {
        DOM.cacheBannerText.textContent = `✓ Loaded from cache (${loadTime}ms)`;
        DOM.cacheBanner.classList.add('cache-hit');
    } else {
        DOM.cacheBannerText.textContent = `⚡ Fresh from DB (${loadTime}ms)`;
        DOM.cacheBanner.classList.add('cache-miss');
    }

    // Show
    requestAnimationFrame(() => {
        DOM.cacheBanner.classList.add('show');
    });

    // Auto-hide after 3 seconds with fade
    setTimeout(() => {
        DOM.cacheBanner.classList.remove('show');
    }, 3000);
}

/**
 * Show/hide loading spinner
 */
function toggleSpinner(show) {
    DOM.loadingSpinner.style.display = show ? 'block' : 'none';
}

/**
 * Show/hide "No results" message
 */
function toggleNoResults(show) {
    DOM.noResults.classList.toggle('d-none', !show);
}

/**
 * Show/hide "End of results" footer
 */
function toggleEndOfResults(show) {
    DOM.endOfResults.classList.toggle('d-none', !show);
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

// =============================================================
// CARD RENDERING
// =============================================================

/**
 * Create a single user card HTML
 */
function createUserCard(user) {
    const col = document.createElement('div');
    col.className = 'col-12 col-sm-6 col-md-4 col-lg-3 col-xl-2 card-fade-in';
    col.setAttribute('data-user-id', user.id);

    col.innerHTML = `
        <div class="card user-card">
            <div class="card-header">
                <span class="text-truncate me-2" title="${escapeHtml(user.fname)} ${escapeHtml(user.lname)}">
                    ${escapeHtml(user.fname)} ${escapeHtml(user.lname)}
                </span>
                <button class="btn-delete" 
                        onclick="deleteUser(${user.id}, this)" 
                        title="Delete user"
                        aria-label="Delete ${escapeHtml(user.fname)} ${escapeHtml(user.lname)}">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
            <div class="card-body">
                <p class="email-text mb-0">
                    <i class="fas fa-envelope email-icon"></i>
                    ${escapeHtml(user.email)}
                </p>
            </div>
        </div>
    `;

    return col;
}

/**
 * Append user cards to the grid with staggered animation
 */
function appendCards(users) {
    const fragment = document.createDocumentFragment();

    users.forEach((user, index) => {
        const card = createUserCard(user);
        card.style.animationDelay = `${index * 50}ms`;
        fragment.appendChild(card);
    });

    DOM.userGrid.appendChild(fragment);
}

// =============================================================
// API CALLS
// =============================================================

/**
 * Fetch users with pagination (lazy loading)
 */
async function fetchUsers() {
    if (State.loading || !State.hasMore || State.searchMode) return;

    State.loading = true;
    toggleSpinner(true);

    try {
        const response = await fetch(`/api/users.php?offset=${State.offset}&limit=${State.limit}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.message || data.error);
        }

        // Append cards
        if (data.users && data.users.length > 0) {
            appendCards(data.users);
            State.offset += data.users.length;
        }

        // Update state
        State.hasMore = data.hasMore;
        updateTotalCount(data.total);

        // Show cache banner
        showCacheBanner(data.cached, data.loadTime);

        // Show end of results if no more data
        if (!data.hasMore) {
            toggleEndOfResults(true);
        }

    } catch (error) {
        console.error('Failed to fetch users:', error);
    } finally {
        State.loading = false;
        toggleSpinner(false);
    }
}

/**
 * Search users by name
 */
async function searchUsers(query) {
    if (State.loading) return;

    State.loading = true;
    State.searchMode = true;
    State.searchQuery = query;

    // Clear grid and hide messages
    DOM.userGrid.innerHTML = '';
    toggleNoResults(false);
    toggleEndOfResults(false);
    toggleSpinner(true);

    try {
        const response = await fetch(`/api/search.php?q=${encodeURIComponent(query)}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.message || data.error);
        }

        // Show results or "no results"
        if (data.users && data.users.length > 0) {
            appendCards(data.users);
            DOM.searchInfo.textContent = `Showing ${data.users.length} of ${formatNumber(data.matchTotal)} matches`;
            DOM.searchInfo.classList.remove('d-none');
        } else {
            toggleNoResults(true);
            DOM.searchInfo.classList.add('d-none');
        }

        // Update total count (overall, not search-specific)
        updateTotalCount(data.total);

        // Show cache banner
        showCacheBanner(data.cached, data.loadTime);

    } catch (error) {
        console.error('Search failed:', error);
        toggleNoResults(true);
    } finally {
        State.loading = false;
        toggleSpinner(false);
    }
}

/**
 * Delete a user (soft delete)
 */
async function deleteUser(userId, buttonElement) {
    const cardCol = buttonElement.closest('[data-user-id]');
    if (!cardCol) return;

    // Disable button to prevent double-clicks
    buttonElement.disabled = true;

    try {
        const response = await fetch('/api/delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: userId }),
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Delete failed');
        }

        // Animate card removal
        cardCol.classList.add('card-removing');
        setTimeout(() => {
            cardCol.remove();

            // Update total count
            updateTotalCount(data.total);

            // If in search mode and grid is empty, show no results
            if (State.searchMode && DOM.userGrid.children.length === 0) {
                toggleNoResults(true);
            }

            // If not in search mode and few cards remain, try loading more
            if (!State.searchMode && DOM.userGrid.children.length < 5 && State.hasMore) {
                fetchUsers();
            }
        }, 400);

    } catch (error) {
        console.error('Delete failed:', error);
        buttonElement.disabled = false;
        alert('Failed to delete user. Please try again.');
    }
}

/**
 * Reset search and reload users
 */
function resetSearch() {
    State.searchMode = false;
    State.searchQuery = '';
    State.offset = 0;
    State.hasMore = true;

    DOM.searchInput.value = '';
    DOM.clearSearch.classList.add('d-none');
    DOM.searchInfo.classList.add('d-none');
    DOM.userGrid.innerHTML = '';
    toggleNoResults(false);
    toggleEndOfResults(false);

    fetchUsers();
}

// =============================================================
// EVENT LISTENERS
// =============================================================

// Search input with debounce
const debouncedSearch = debounce((query) => {
    const trimmed = query.trim();

    if (trimmed.length === 0) {
        resetSearch();
        return;
    }

    if (trimmed.length >= 1) {
        searchUsers(trimmed);
    }
}, 300);

DOM.searchInput.addEventListener('input', (e) => {
    const value = e.target.value;

    // Toggle clear button visibility
    DOM.clearSearch.classList.toggle('d-none', value.length === 0);

    debouncedSearch(value);
});

// Clear search button
DOM.clearSearch.addEventListener('click', () => {
    resetSearch();
    DOM.searchInput.focus();
});

// Handle Escape key to clear search
DOM.searchInput.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (DOM.searchInput.value.length > 0) {
            resetSearch();
        }
    }
});

// =============================================================
// LAZY LOADING — IntersectionObserver
// =============================================================
const lazyLoadObserver = new IntersectionObserver(
    (entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !State.searchMode && !State.loading && State.hasMore) {
                fetchUsers();
            }
        });
    },
    {
        root: null,
        rootMargin: '200px', // Start loading 200px before reaching the bottom
        threshold: 0.1,
    }
);

// Observe the sentinel element at the bottom of the page
if (DOM.scrollSentinel) {
    lazyLoadObserver.observe(DOM.scrollSentinel);
}

// =============================================================
// INITIALIZATION
// =============================================================
document.addEventListener('DOMContentLoaded', () => {
    // Load initial batch
    fetchUsers();
});

// Make deleteUser globally accessible (used in onclick)
window.deleteUser = deleteUser;
