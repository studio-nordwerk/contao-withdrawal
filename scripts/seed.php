<?php

declare(strict_types=1);

require __DIR__.'/../app/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

$url = getenv('DATABASE_URL');
$email = getenv('CONTAO_ADMIN_EMAIL');
$password = getenv('CONTAO_ADMIN_PASSWORD');

if (!$url || !$email || !$password || 'change-me' === $password) {
    throw new RuntimeException('Set CONTAO_ADMIN_EMAIL and CONTAO_ADMIN_PASSWORD in .env before seeding.');
}

$parts = parse_url($url);
if (false === $parts) {
    throw new RuntimeException('Invalid DATABASE_URL.');
}
$db = DriverManager::getConnection([
    'driver' => 'pdo_mysql',
    'host' => $parts['host'] ?? 'db',
    'port' => $parts['port'] ?? 3306,
    'user' => $parts['user'] ?? 'contao',
    'password' => $parts['pass'] ?? 'contao',
    'dbname' => ltrim($parts['path'] ?? '/contao', '/'),
]);
$now = time();
if (0 < (int) $db->fetchOne('SELECT COUNT(*) FROM tl_page')) {
    echo "Existing pages found; demo seed skipped.\n";
    exit(0);
}

$db->beginTransaction();

try {
    if (0 === (int) $db->fetchOne('SELECT COUNT(*) FROM tl_user WHERE username = ?', [$email])) {
        $db->insert('tl_user', [
            'tstamp' => $now,
            'username' => $email,
            'name' => 'Demo Admin',
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'admin' => 1,
            'dateAdded' => $now,
        ]);
    }

    $db->insert('tl_theme', ['tstamp' => $now, 'name' => 'Withdrawal Demo', 'author' => 'Nordwerk']);
    $themeId = (int) $db->lastInsertId();

    $db->insert('tl_content', [
        'tstamp' => $now,
        'type' => 'html',
        'html' => '<nav aria-label="Widerruf">{{withdrawal_link}}</nav>',
        'ptable' => 'tl_theme',
        'pid' => $themeId,
    ]);
    $footerId = (int) $db->lastInsertId();

    $db->executeStatement(
        'INSERT INTO tl_layout (pid,tstamp,name,`rows`,cols,modules,head) VALUES (?,?,?,?,?,?,?)',
        [
            $themeId,
            $now,
            'Demo',
            '2rwf',
            '1cl',
            serialize([
                ['mod' => 0, 'col' => 'main', 'enable' => 1],
                ['mod' => 'content-'.$footerId, 'col' => 'footer', 'enable' => 1],
            ]),
            '<style>body{font:1.1rem/1.5 system-ui;margin:0;color:#17212b}#wrapper{max-width:60rem;margin:auto;padding:2rem}#footer{margin-top:4rem;padding:1.5rem 0;border-top:2px solid #17212b}.withdrawal-link{display:inline-block;background:#17212b;color:white;padding:.8rem 1.2rem;font-weight:700;text-decoration:none}.withdrawal-link:focus,.withdrawal-link:hover{background:#005ea8}</style>',
        ],
    );
    $layoutId = (int) $db->lastInsertId();

    $db->insert('tl_page', [
        'tstamp' => $now,
        'title' => 'Withdrawal Demo',
        'type' => 'root',
        'alias' => 'root',
        'language' => 'de',
        'fallback' => 1,
        'published' => 1,
        'useSSL' => 0,
        'includeLayout' => 1,
        'layout' => $layoutId,
    ]);
    $rootId = (int) $db->lastInsertId();

    foreach ([['Startseite', 'home', 'text', '<h1>Willkommen</h1><p>Demoseite für den Widerrufsbutton.</p>'], ['Kontakt', 'kontakt', 'text', '<h1>Kontakt</h1><p>Auch hier bleibt der Widerrufslink im Footer sichtbar.</p>'], ['Vertrag widerrufen', 'withdrawal', 'withdrawal', null]] as [$title, $alias, $type, $text]) {
        $db->insert('tl_page', [
            'pid' => $rootId,
            'sorting' => 'home' === $alias ? 128 : ('kontakt' === $alias ? 256 : 384),
            'tstamp' => $now,
            'title' => $title,
            'type' => 'regular',
            'alias' => $alias,
            'published' => 1,
        ]);
        $pageId = (int) $db->lastInsertId();
        $db->insert('tl_article', [
            'pid' => $pageId,
            'tstamp' => $now,
            'title' => $title,
            'alias' => $alias,
            'inColumn' => 'main',
            'published' => 1,
        ]);
        $articleId = (int) $db->lastInsertId();
        $db->insert('tl_content', [
            'pid' => $articleId,
            'ptable' => 'tl_article',
            'tstamp' => $now,
            'type' => $type,
            'text' => $text,
        ]);
    }

    $db->commit();
    echo "Demo seeded.\n";
} catch (Throwable $exception) {
    $db->rollBack();

    throw $exception;
}
