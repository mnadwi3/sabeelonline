<?php
/**
 * =========================================================
 * Small helper functions used on many pages
 * =========================================================
 */

/**
 * Make a URL-friendly slug from a title
 * Example: "Learn Quran Fast" → "learn-quran-fast"
 */
function make_slug(string $text): string
{
    $text = strtolower(trim($text));
    // Keep letters, numbers, and spaces/hyphens
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Make sure slug is unique in posts table
 * If "my-post" exists, create "my-post-2", "my-post-3", ...
 */
function unique_post_slug(PDO $pdo, string $slug, ?int $ignoreId = null): string
{
    $base = $slug !== '' ? $slug : 'post';
    $final = $base;
    $i = 2;

    while (true) {
        if ($ignoreId) {
            $row = db_one(
                $pdo,
                "SELECT id FROM posts WHERE slug = ? AND id != ? LIMIT 1",
                [$final, $ignoreId]
            );
        } else {
            $row = db_one($pdo, "SELECT id FROM posts WHERE slug = ? LIMIT 1", [$final]);
        }

        if (!$row) {
            return $final;
        }

        $final = $base . '-' . $i;
        $i++;
    }
}

/**
 * Rough reading time (about 200 words per minute)
 */
function reading_time(string $content): string
{
    $words = str_word_count(strip_tags($content));
    $minutes = max(1, (int) ceil($words / 200));
    return $minutes . ' min read';
}

/**
 * Nice status label for tables
 */
function status_label(string $status): string
{
    $map = [
        'draft' => 'Draft',
        'pending_review' => 'Pending Review',
        'published' => 'Published',
        'rejected' => 'Rejected',
    ];
    return $map[$status] ?? $status;
}

/**
 * Upload a featured image safely
 * Returns relative path like "uploads/blog-images/abc.jpg" or null
 */
function upload_blog_image(array $file): ?string
{
    // No file chosen
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed. Please try again.');
    }

    // Max 2MB
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Image is too large. Max size is 2MB.');
    }

    // Check real image type
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        throw new RuntimeException('Please upload a valid image file.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $mime = $info['mime'] ?? '';
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, and WEBP images are allowed.');
    }

    $folder = __DIR__ . '/../uploads/blog-images';
    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }

    $name = 'blog_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $dest = $folder . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save uploaded image.');
    }

    return 'uploads/blog-images/' . $name;
}

/**
 * Format date for display
 */
function format_date(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }
    return date('d M Y', strtotime($datetime));
}

/**
 * CSRF token helpers (protect POST actions)
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Turn plain blog text into HTML paragraphs (tighter, cleaner spacing)
 * - Splits on blank lines into <p> tags
 * - Single line breaks become <br>
 */
function format_post_content(string $content): string
{
    $content = str_replace(["\r\n", "\r"], "\n", trim($content));
    if ($content === '') {
        return '';
    }

    // Rich-text HTML from the editor (whitelist safe tags)
    if (preg_match('/<(p|br|strong|b|em|i|u|s|h[1-3]|ul|ol|li|a|span|blockquote|div)\b/i', $content)) {
        $allowed = '<p><br><strong><b><em><i><u><s><h1><h2><h3><ul><ol><li><a><span><blockquote><div>';
        $safe = strip_tags($content, $allowed);

        // Allow only safe href / style="color:..." attributes
        $safe = preg_replace_callback(
            '/<(a|span)\b([^>]*)>/i',
            static function (array $m): string {
                $tag = strtolower($m[1]);
                $attrs = $m[2];
                $out = '<' . $tag;

                if ($tag === 'a' && preg_match('/\bhref\s*=\s*([\'"])(.*?)\1/i', $attrs, $href)) {
                    $url = trim($href[2]);
                    if (preg_match('#^(https?:|mailto:|/|#)#i', $url)) {
                        $out .= ' href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" rel="noopener noreferrer"';
                    }
                }

                if (preg_match('/\bstyle\s*=\s*([\'"])(.*?)\1/i', $attrs, $style)) {
                    if (preg_match('/color\s*:\s*([^;]+)/i', $style[2], $color)) {
                        $c = trim($color[1]);
                        if (preg_match('/^(#[0-9a-f]{3,8}|rgb\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\)|[a-z]+)$/i', $c)) {
                            $out .= ' style="color:' . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . '"';
                        }
                    }
                }

                return $out . '>';
            },
            $safe
        ) ?? $safe;

        return $safe;
    }

    // Legacy plain text posts
    $blocks = preg_split("/\n\s*\n/", $content) ?: [];
    $html = '';

    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') {
            continue;
        }
        $escaped = htmlspecialchars($block, ENT_QUOTES, 'UTF-8');
        $html .= '<p>' . nl2br($escaped) . '</p>';
    }

    return $html;
}
