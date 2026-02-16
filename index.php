<?php
/**
 * User Directory Module — Main Page
 * 
 * Bootstrap 5 + PHP + MySQL
 * Features: Sticky header, search, card grid, lazy loading, caching, PWA
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

require_once __DIR__ . '/config/database.php';

// Get initial total count for header badge
$totalUsers = 0;
try {
    $db = Database::getConnection();
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
    $totalUsers = (int)$stmt->fetch()['total'];
} catch (Exception $e) {
    $totalUsers = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="User Directory Module — Browse, search and manage users">
    <meta name="theme-color" content="#0d6efd">

    <title>User Directory</title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/assets/images/logo.png">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" 
          rel="stylesheet" 
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YcnS/1DUgGo3JOTPnh/CMPTO1wLkY2l5QY" 
          crossorigin="anonymous">

    <!-- Font Awesome (delete icon library) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" 
          rel="stylesheet" 
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" 
          crossorigin="anonymous" 
          referrerpolicy="no-referrer">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

    <!-- ====================== CACHE BANNER ====================== -->
    <div id="cacheBanner" class="cache-banner" role="alert" aria-live="polite">
        <span id="cacheBannerText"></span>
    </div>

    <!-- ====================== STICKY HEADER ====================== -->
    <header class="header-row" id="mainHeader">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center py-2">
                <!-- Logo -->
                <div class="d-flex align-items-center gap-3">
                    <img src="/assets/images/logo.svg" 
                         alt="User Directory Logo" 
                         class="header-logo"
                         width="40" 
                         height="40"
                         onerror="this.onerror=null; this.src='https://via.placeholder.com/40x40/4f46e5/ffffff?text=UD';">
                    <h1 class="h5 mb-0 text-white fw-bold d-none d-sm-block" style="font-family:'Inter',sans-serif; letter-spacing:-0.02em;">User Directory</h1>
                </div>
                <!-- Total Users Badge (right side of header) -->
                <span class="badge fs-6 flex-shrink-0" id="totalBadge">
                    Total Users: <strong id="totalCount"><?= number_format($totalUsers) ?></strong>
                </span>
            </div>
        </div>
    </header>

    <!-- ====================== STICKY SEARCH ROW ====================== -->
    <div class="search-row" id="searchRow">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-center gap-3 py-2">
                <div class="flex-grow-1" style="max-width: 600px;">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="search" 
                               id="searchInput" 
                               class="form-control border-start-0 ps-0" 
                               placeholder="Search by first or last name..." 
                               aria-label="Search users"
                               autocomplete="off"
                               maxlength="200">
                        <button class="btn btn-outline-secondary d-none" 
                                id="clearSearch" 
                                type="button" 
                                aria-label="Clear search">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div id="searchInfo" class="text-muted small mt-1 text-center d-none"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====================== MAIN CONTENT ====================== -->
    <main class="container-fluid py-4" id="mainContent">

        <!-- User Cards Grid -->
        <div class="row g-3" id="userGrid">
            <!-- Cards injected dynamically by JS -->
        </div>

        <!-- No Users Found Message -->
        <div id="noResults" class="text-center py-5 d-none">
            <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">No users found</h4>
            <p class="text-muted">Try a different search term</p>
        </div>

        <!-- Lazy Loading Spinner -->
        <div id="loadingSpinner" class="text-center py-4">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted mt-2">Loading more users...</p>
        </div>

        <!-- End of Results -->
        <div id="endOfResults" class="text-center py-4 d-none">
            <hr class="mx-auto" style="max-width: 200px;">
            <p class="text-muted mb-0">
                <i class="fas fa-check-circle text-success me-1"></i>
                End of results
            </p>
        </div>

        <!-- Scroll-to-top sentinel for IntersectionObserver -->
        <div id="scrollSentinel" style="height: 1px;"></div>
    </main>

    <!-- ====================== FOOTER ====================== -->
    <footer class="text-center py-3 text-muted small border-top mt-4">
        <div class="container">
            &copy; <?= date('Y') ?> User Directory Module &middot; Built with Bootstrap 5 + PHP + MySQL
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" 
            crossorigin="anonymous"></script>

    <!-- App JS -->
    <script src="/assets/js/app.js"></script>

    <!-- PWA Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(reg => console.log('SW registered:', reg.scope))
                    .catch(err => console.warn('SW registration failed:', err));
            });
        }
    </script>
</body>
</html>
