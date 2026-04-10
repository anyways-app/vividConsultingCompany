<?php
/**
 * vividConsulting.info — Database Connection (PDO + PostgreSQL)
 *
 * Usage: $pdo = require __DIR__ . '/db.php';
 * Returns a singleton PDO instance.
 */

(function () {
    static $pdo = null;
    if ($pdo !== null) {
        return;
    }

    $cfg = require __DIR__ . '/config.php';

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $cfg['DB_HOST'],
        $cfg['DB_PORT'],
        $cfg['DB_NAME']
    );

    $pdo = new PDO($dsn, $cfg['DB_USER'], $cfg['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $GLOBALS['__vc_pdo'] = $pdo;
})();

/**
 * @return PDO
 */
function vc_db(): PDO
{
    if (!isset($GLOBALS['__vc_pdo'])) {
        // Re-trigger the connection
        require __DIR__ . '/db.php';
    }
    return $GLOBALS['__vc_pdo'];
}

return vc_db();
