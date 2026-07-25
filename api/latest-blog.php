<?php
/**
 * Public JSON feed — two latest published blog posts for the homepage.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

function latest_blog_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function latest_blog_image(?string $path): string
{
    $path = trim(str_replace('\\', '/', (string) $path));
    if ($path === '') {
        return 'blog/uploads/blog-images/woman.jpg';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if (str_starts_with($path, 'blog/')) {
        return $path;
    }
    return 'blog/' . ltrim($path, '/');
}

function latest_blog_excerpt(array $row): string
{
    $excerpt = trim((string) ($row['short_description'] ?? ''));
    if ($excerpt !== '') {
        return $excerpt;
    }
    $plain = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) ($row['content'] ?? ''))));
    if ($plain === '') {
        return '';
    }
    $len = function_exists('mb_strlen') ? mb_strlen($plain) : strlen($plain);
    if ($len <= 140) {
        return $plain;
    }
    $cut = function_exists('mb_substr') ? mb_substr($plain, 0, 137) : substr($plain, 0, 137);
    return rtrim($cut) . '…';
}

try {
    require_once dirname(__DIR__) . '/blog/includes/db.php';
} catch (Throwable $e) {
    latest_blog_json(['ok' => false, 'error' => 'Blog database unavailable.', 'posts' => []], 503);
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    latest_blog_json(['ok' => false, 'error' => 'Blog database unavailable.', 'posts' => []], 503);
}

try {
    $rows = db_all(
        $pdo,
        "SELECT p.title, p.slug, p.featured_image, p.short_description, p.content,
                p.published_at, p.created_at,
                t.name AS teacher_name,
                c.name AS category_name
         FROM posts p
         JOIN teachers t ON t.id = p.teacher_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.status = 'published'
         ORDER BY p.published_at DESC, p.created_at DESC
         LIMIT 2"
    );
} catch (Throwable $e) {
    error_log('latest-blog.php: ' . $e->getMessage());
    latest_blog_json(['ok' => false, 'error' => 'Could not load posts.', 'posts' => []], 500);
}

$posts = [];
foreach ($rows as $row) {
    $dateRaw = (string) ($row['published_at'] ?: $row['created_at'] ?: '');
    $posts[] = [
        'title' => (string) ($row['title'] ?? ''),
        'slug' => (string) ($row['slug'] ?? ''),
        'url' => 'blog/post.php?slug=' . rawurlencode((string) ($row['slug'] ?? '')),
        'image' => latest_blog_image($row['featured_image'] ?? null),
        'excerpt' => latest_blog_excerpt($row),
        'category' => (string) ($row['category_name'] ?: 'General'),
        'author' => (string) ($row['teacher_name'] ?? ''),
        'date' => $dateRaw !== '' ? date('d M Y', strtotime($dateRaw)) : '',
    ];
}

latest_blog_json(['ok' => true, 'posts' => $posts]);
