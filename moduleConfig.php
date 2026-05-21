<?php

/**
 * Module Configuration Page - paho_openemr_translations
 *
 * Minimal admin page: shows a Run button that executes the bundled translation
 * SQL against lang_constants / lang_definitions using a single multi_query()
 * on a fresh mysqli connection. The fresh connection is required because the
 * .sql uses a TEMPORARY TABLE that needs session affinity.
 *
 * No backups, no per-statement splitting, no audit log, no drift detection —
 * the SQL itself is idempotent (INSERTs guarded by NOT EXISTS, UPDATEs scoped
 * by lang_id). Back up the database manually before running if needed.
 *
 * @package OpenEMR\Modules\PahoOpenemrTranslations
 * @license GNU General Public License 3
 */

$sessionAllowWrite = true;
require_once(__DIR__ . "/../../../globals.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;

if (!AclMain::aclCheckCore('admin', 'super')) {
    echo xlt('Access Denied');
    exit;
}

$csrfToken  = CsrfUtils::collectCsrfToken();
$moduleRoot = __DIR__;
$sqlPath    = $moduleRoot . '/sql/paio/sql_translations_spanish.sql';

$runError    = null;
$runOk       = false;
$runDuration = 0.0;
$rowsBefore  = null;
$rowsAfter   = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'run') {
    if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
        CsrfUtils::csrfNotVerified();
    }

    if (!is_file($sqlPath) || !is_readable($sqlPath)) {
        $runError = xl('SQL file is missing or unreadable.') . ' ' . $sqlPath;
    } else {
        $db = paho_openemr_translations_open_fresh_db();
        if ($db === null) {
            $runError = xl('Could not open a database connection.');
        } else {
            $sql = file_get_contents($sqlPath);
            if ($sql === false || $sql === '') {
                $runError = xl('Could not read SQL file.');
            } else {
                $rowsBefore = paho_openemr_translations_counts($db);
                $start = microtime(true);

                if (!$db->multi_query($sql)) {
                    $runError = $db->error;
                } else {
                    while (true) {
                        if ($res = $db->store_result()) {
                            $res->free();
                        }
                        if ($db->errno !== 0) {
                            $runError = $db->error;
                            break;
                        }
                        if (!$db->more_results()) {
                            break;
                        }
                        if (!$db->next_result()) {
                            $runError = $db->error;
                            break;
                        }
                    }
                }

                $runDuration = (microtime(true) - $start) * 1000.0;
                $rowsAfter   = paho_openemr_translations_counts($db);
                $runOk       = ($runError === null);
            }
            $db->close();
        }
    }
}

$constantsCount   = (int) (sqlQuery("SELECT COUNT(*) AS c FROM lang_constants")['c']   ?? 0);
$definitionsCount = (int) (sqlQuery("SELECT COUNT(*) AS c FROM lang_definitions")['c'] ?? 0);

function paho_openemr_translations_open_fresh_db(): ?\mysqli
{
    $host  = $GLOBALS['host']  ?? '';
    $port  = (int) ($GLOBALS['port'] ?? 3306);
    $login = $GLOBALS['login'] ?? '';
    $pass  = $GLOBALS['pass']  ?? '';
    $dbase = $GLOBALS['dbase'] ?? '';
    if ($host === '' || $login === '' || $dbase === '') {
        return null;
    }
    try {
        $db = new \mysqli($host, $login, $pass, $dbase, $port);
    } catch (\Throwable $e) {
        return null;
    }
    if ($db->connect_errno !== 0) {
        return null;
    }
    $db->set_charset('utf8mb4');
    return $db;
}

function paho_openemr_translations_counts(\mysqli $db): array
{
    $out = ['constants' => 0, 'definitions' => 0];
    if ($r = $db->query("SELECT COUNT(*) AS c FROM lang_constants")) {
        $out['constants'] = (int) ($r->fetch_assoc()['c'] ?? 0);
        $r->free();
    }
    if ($r = $db->query("SELECT COUNT(*) AS c FROM lang_definitions")) {
        $out['definitions'] = (int) ($r->fetch_assoc()['c'] ?? 0);
        $r->free();
    }
    return $out;
}

?><!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('PAIO Translations'); ?></title>
    <?php Header::setupHeader(); ?>
</head>
<body class="body_top">
    <div class="container mt-3">
        <h2><?php echo xlt('PAIO Translations'); ?></h2>

        <?php if ($runError !== null): ?>
            <div class="alert alert-danger">
                <strong><?php echo xlt('Run failed:'); ?></strong>
                <?php echo text($runError); ?>
            </div>
        <?php elseif ($runOk): ?>
            <div class="alert alert-success">
                <strong><?php echo xlt('Run succeeded.'); ?></strong>
                <?php echo xlt('Duration:'); ?>
                <?php echo text(number_format($runDuration, 1)); ?> ms
                <dl class="row mb-0 mt-2">
                    <dt class="col-sm-3"><code>lang_constants</code></dt>
                    <dd class="col-sm-9">
                        <?php echo text(number_format($rowsBefore['constants'])); ?>
                        →
                        <?php echo text(number_format($rowsAfter['constants'])); ?>
                    </dd>
                    <dt class="col-sm-3"><code>lang_definitions</code></dt>
                    <dd class="col-sm-9">
                        <?php echo text(number_format($rowsBefore['definitions'])); ?>
                        →
                        <?php echo text(number_format($rowsAfter['definitions'])); ?>
                    </dd>
                </dl>
            </div>
        <?php endif; ?>

        <div class="alert alert-warning">
            <strong><?php echo xlt('Warning:'); ?></strong>
            <?php echo xlt('Clicking Run executes the bundled translation SQL directly against lang_constants and lang_definitions. No automatic backup is created — back up your database manually if needed. The script is idempotent and safe to re-run.'); ?>
        </div>

        <div class="card mb-3">
            <div class="card-header"><?php echo xlt('Translation script'); ?></div>
            <div class="card-body">
                <dl class="row mb-3">
                    <dt class="col-sm-3"><?php echo xlt('Path'); ?></dt>
                    <dd class="col-sm-9"><code><?php echo text('sql/paio/sql_translations_spanish.sql'); ?></code></dd>

                    <dt class="col-sm-3"><?php echo xlt('File'); ?></dt>
                    <dd class="col-sm-9">
                        <?php if (is_file($sqlPath)): ?>
                            <span class="badge badge-success"><?php echo xlt('present'); ?></span>
                            <span class="text-muted small ml-2">
                                (<?php echo text(number_format((int) filesize($sqlPath))); ?> <?php echo xlt('bytes'); ?>)
                            </span>
                        <?php else: ?>
                            <span class="badge badge-danger"><?php echo xlt('missing'); ?></span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-3"><code>lang_constants</code></dt>
                    <dd class="col-sm-9"><?php echo text(number_format($constantsCount)); ?></dd>

                    <dt class="col-sm-3"><code>lang_definitions</code></dt>
                    <dd class="col-sm-9"><?php echo text(number_format($definitionsCount)); ?></dd>
                </dl>

                <?php if (is_file($sqlPath)): ?>
                    <form method="POST" action=""
                          onsubmit="return confirm(<?php echo attr_js(xl('Run the translation SQL? This will modify lang_constants and lang_definitions. There is no automatic rollback.')); ?>);">
                        <input type="hidden" name="csrf_token_form" value="<?php echo attr($csrfToken); ?>">
                        <input type="hidden" name="action" value="run">
                        <button type="submit" class="btn btn-primary"><?php echo xlt('Run'); ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
