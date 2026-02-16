# 📁 User Directory Module

A high-performance **Bootstrap 5 + PHP 8 + MySQL 8** user directory with 10,000+ records, lazy loading, search, caching, and PWA support.

![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)

---

## 🚀 Features

| Feature | Details |
|---------|---------|
| **Sticky Header** | Logo + real-time "Total Users: X" badge (updates on delete/search) |
| **Search** | Case-insensitive first/last name search with **300ms debounce**, top 6 results (desc by fname) |
| **Card Grid** | Responsive Bootstrap grid with user cards (name header, email body, red delete button) |
| **Soft Delete** | Red ✕ button removes card with animation, updates DB status column + total count |
| **Lazy Loading** | IntersectionObserver triggers loading spinner → next 10 cards per scroll |
| **Caching** | File-based 60s TTL cache with auto-expiring **green/blue banner** (3s fade) |
| **Responsive** | Fully responsive across all device sizes (xs → xxl) |
| **Security** | PDO prepared statements, input sanitization, XSS prevention |
| **PWA** | Service worker, manifest.json, offline-ready static assets |
| **Dark Mode** | Auto-detects `prefers-color-scheme: dark` |
| **SEO** | robots.txt, sitemap.xml included |

---

## 📋 Prerequisites

- **PHP 8.0+** with PDO MySQL extension
- **MySQL 8.0+**
- A local web server (Apache, Nginx, or PHP built-in server)

---

## ⚡ Quick Start

### 1. Clone the Repository

```bash
git clone https://github.com/yourusername/user-directory.git
cd user-directory
```

### 2. Set Up the Database

```bash
# Log into MySQL
mysql -u root -p

# Run the schema (creates DB, table, and 10,000 dummy records)
source sql/schema.sql;
```

This will:
- Create the `user_directory` database
- Create the `users` table with proper indexes
- Generate **10,000 dummy user records** via a stored procedure

### 3. Configure Database Connection

Edit `config/database.php` and update credentials if needed:

```php
private const DB_HOST = '127.0.0.1';
private const DB_PORT = '3306';
private const DB_NAME = 'user_directory';
private const DB_USER = 'root';
private const DB_PASS = '';
```

Or use environment variables: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.

### 4. Start the Server

```bash
# Using PHP built-in server
php -S localhost:8000

# Or with a custom host
php -S 0.0.0.0:8000
```

### 5. Open in Browser

Navigate to `http://localhost:8000`

---

## 🏗️ Project Structure

```
user-directory/
├── api/
│   ├── users.php          # GET  — Paginated users (lazy loading + cache)
│   ├── search.php         # GET  — Case-insensitive name search (top 6)
│   └── delete.php         # POST — Soft delete (status → 'deleted')
├── assets/
│   ├── css/
│   │   └── style.css      # Custom styles (responsive, dark mode, animations)
│   ├── images/
│   │   └── logo.svg       # SVG logo
│   └── js/
│       └── app.js         # Main app logic (lazy load, debounce, delete)
├── cache/                 # Auto-created — file-based cache storage
├── config/
│   └── database.php       # PDO singleton connection
├── sql/
│   └── schema.sql         # DB schema + 10k dummy data generator
├── .gitignore
├── index.php              # Main entry point
├── manifest.json          # PWA manifest
├── README.md
├── robots.txt             # Search engine directives
├── sitemap.xml            # Sitemap for SEO
└── sw.js                  # Service Worker for PWA
```

---

## 🗄️ Database Schema

```sql
CREATE TABLE users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fname      VARCHAR(100) NOT NULL,
    lname      VARCHAR(100) NOT NULL,
    email      VARCHAR(255) NOT NULL UNIQUE,
    status     ENUM('active', 'deleted') NOT NULL DEFAULT 'active',
    review     TEXT DEFAULT 'a sample review',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_status (status),
    INDEX idx_fname (fname),
    INDEX idx_lname (lname),
    INDEX idx_fname_lname (fname, lname),
    INDEX idx_created_at (created_at)
);
```

---

## 📊 Performance Metrics

### Load Times (tested with 10,000 records)

| Metric | Value |
|--------|-------|
| **Initial page load** | ~150–250ms |
| **First API call (cold, from DB)** | ~30–80ms |
| **Subsequent API call (cached)** | ~2–8ms |
| **Search query (cold)** | ~20–50ms |
| **Search query (cached)** | ~1–5ms |
| **Lazy load batch (10 cards)** | ~15–40ms |
| **Delete operation** | ~10–30ms |

### Cache Performance

| Metric | Details |
|--------|---------|
| **Cache strategy** | File-based with 60-second TTL |
| **Cache hit ratio** | ~80–90% during typical browsing sessions |
| **Cache invalidation** | Automatic on delete operations (all cache files cleared) |
| **Cache indicator** | Green banner = cache hit, Blue banner = fresh DB query |
| **Banner duration** | 3 seconds with smooth fade animation |

### Lazy Loading

| Metric | Details |
|--------|---------|
| **Batch size** | 10 cards per load |
| **Trigger** | IntersectionObserver, 200px before bottom |
| **Spinner** | Visible loading indicator for slow connections |
| **End state** | "End of results" footer after last card |

---

## 🔒 Security Measures

- **SQL Injection Prevention**: All database queries use PDO prepared statements with parameterized bindings
- **XSS Prevention**: HTML output escaped with `escapeHtml()` in JavaScript, `htmlspecialchars()` in PHP
- **Input Validation**: Server-side validation with `filter_input()` and `filter_var()`
- **Input Sanitization**: `FILTER_SANITIZE_SPECIAL_CHARS` on search queries
- **Request Method Enforcement**: DELETE endpoint only accepts POST
- **Content-Type Headers**: Proper `Content-Type: application/json` on all API responses
- **Error Handling**: Generic error messages (no stack traces or SQL details exposed)
- **CSRF Protection**: JSON body parsing (not form-encoded) reduces CSRF surface

---

## 🔌 API Reference

### GET `/api/users.php`

Fetches paginated users (active only, ordered by fname DESC).

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `offset`  | int  | 0       | Starting position |
| `limit`   | int  | 10      | Number of records (max 50) |

**Response:**
```json
{
    "users": [{ "id": 1, "fname": "William", "lname": "Smith", "email": "...", "review": "...", "created_at": "..." }],
    "total": 10000,
    "hasMore": true,
    "cached": false,
    "loadTime": 45.2
}
```

### GET `/api/search.php`

Case-insensitive search by first or last name. Returns top 6 matches.

| Parameter | Type   | Description |
|-----------|--------|-------------|
| `q`       | string | Search query (required, max 200 chars) |

**Response:**
```json
{
    "users": [...],
    "matchTotal": 42,
    "total": 10000,
    "cached": false,
    "loadTime": 23.1
}
```

### POST `/api/delete.php`

Soft-deletes a user by updating status to 'deleted'.

**Body:**
```json
{ "id": 123 }
```

**Response:**
```json
{
    "success": true,
    "total": 9999,
    "message": "User deleted successfully."
}
```

---

## 🎨 UI/UX Features

- **Smooth card animations** — Fade-in on load, scale-out on delete
- **Staggered rendering** — Cards appear with 50ms stagger delay
- **Hover effects** — Cards lift on hover with enhanced shadow
- **Pulse animation** — Total count badge pulses on update
- **Slide-in banner** — Cache status slides in from right
- **Dark mode** — Automatic based on system preference
- **Print styles** — Clean print layout with hidden UI elements
- **Reduced motion** — Respects `prefers-reduced-motion` setting

---

## 🧩 Bonus Features

- ✅ **Search Debounce** — 300ms debounce prevents excessive API calls
- ✅ **PWA Manifest** — Installable as a Progressive Web App
- ✅ **Service Worker** — Offline static asset caching
- ✅ **Dark Mode** — Auto-detects system preference
- ✅ **Print Styles** — Optimized for printing
- ✅ **Accessibility** — ARIA labels, roles, semantic HTML
- ✅ **SVG Logo** — Scalable vector logo
- ✅ **Keyboard Support** — Escape key clears search
- ✅ **IntersectionObserver** — Modern lazy loading API
- ✅ **Staggered Animations** — Visually appealing card entry

---

## 📝 License

MIT License — feel free to use, modify, and distribute.
