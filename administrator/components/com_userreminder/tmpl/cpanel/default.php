<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \JoomCoder\Component\UserReminder\Administrator\View\Cpanel\HtmlView $this */

$dashboard  = $this->dashboard ?? [];
$kpi        = $dashboard['kpi'] ?? [];
$analytics  = $dashboard['analytics'] ?? [];
$recent     = $dashboard['recent'] ?? [];
$health     = $dashboard['health'] ?? [];
$config     = $dashboard['configSnapshot'] ?? [];
$thresholds = $dashboard['thresholds'] ?? [];

$pendingActivation = (int) ($kpi['pendingActivation'] ?? 0);
$neverLoggedIn     = (int) ($kpi['neverLoggedIn'] ?? 0);
$inactiveUsers     = (int) ($kpi['inactiveUsers'] ?? 0);
$dueNow            = (int) ($kpi['dueNow'] ?? 0);
$sentTotal         = (int) ($kpi['sentTotal'] ?? 0);
$sent7d            = (int) ($kpi['sent7d'] ?? 0);
$sent30d           = (int) ($kpi['sent30d'] ?? 0);
$optedOut          = (int) ($kpi['optedOut'] ?? 0);
$totalUsers        = (int) ($kpi['totalUsers'] ?? 0);

$trend  = $analytics['trend'] ?? [];
$byType = $analytics['byType'] ?? [1 => 0, 2 => 0, 3 => 0];
$aging  = $analytics['aging'] ?? ['pending' => [], 'inactive' => []];

$recentLogs      = $recent['logs'] ?? [];
$oldestPending   = $recent['oldestPending'] ?? [];
$longestInactive = $recent['longestInactive'] ?? [];

$alerts      = $health['alerts'] ?? [];
$healthSum   = $health['summary'] ?? [];
$generatedAt = $dashboard['generatedAt'] ?? '';

$alertMap = [
    'plugin_disabled'   => ['class' => 'danger',  'text' => Text::_('COM_USERREMINDER_DASH_ALERT_PLUGIN_DISABLED'), 'cta' => Text::_('COM_USERREMINDER_PLUGIN_DISABLED_CTA'), 'ctaUrl' => Route::_('index.php?option=com_scheduler')],
    'scheduler_off'     => ['class' => 'warning', 'text' => Text::sprintf('COM_USERREMINDER_DASH_ALERT_SCHEDULER_OFF', ($healthSum['queueTotal'] ?? $pendingActivation + $neverLoggedIn + $inactiveUsers))],
    'debug_on'          => ['class' => 'info',    'text' => Text::_('COM_USERREMINDER_DASH_ALERT_DEBUG_ON')],
    'delete_enabled'    => ['class' => 'danger',  'text' => Text::_('COM_USERREMINDER_DASH_ALERT_DELETE_ENABLED')],
    'mail_invalid'      => ['class' => 'danger',  'text' => Text::_('COM_USERREMINDER_DASH_ALERT_MAIL_INVALID')],
    'bcc_missing'       => ['class' => 'warning', 'text' => Text::_('COM_USERREMINDER_DASH_ALERT_BCC_MISSING')],
    'backlog_pressure'  => ['class' => 'warning', 'text' => Text::sprintf('COM_USERREMINDER_DASH_ALERT_BACKLOG', $dueNow, $thresholds['batchSize'] ?? 50)],
];

// Trend max for fallback bars.
$trendMax = 1;
foreach ($trend as $pt) {
    $trendMax = max($trendMax, (int) ($pt['count'] ?? 0));
}
$byTypeTotal = max(1, (int) ($byType[1] ?? 0) + (int) ($byType[2] ?? 0) + (int) ($byType[3] ?? 0));

$pendingAging  = $aging['pending'] ?? ['1-7' => 0, '8-30' => 0, '30+' => 0];
$inactiveAging = $aging['inactive'] ?? ['just' => 0, '2x' => 0, '4x' => 0];
$pendingMax    = max(1, max(array_values($pendingAging ?: [0])));
$inactiveMax   = max(1, max(array_values($inactiveAging ?: [0])));

// KPI cards: [lang, value, icon, colour, url, sub-html]
$kpis = [
    ['COM_USERREMINDER_DASH_KPI_PENDING_ACTIVATION', $pendingActivation, 'fa-user-clock', 'warning', 'index.php?option=com_userreminder&view=reminders', Text::_('COM_USERREMINDER_DASH_KPI_PENDING_ACTIVATION_DESC')],
    ['COM_USERREMINDER_DASH_KPI_NEVER_LOGGED', $neverLoggedIn, 'fa-user-plus', 'info', 'index.php?option=com_userreminder&view=reminders', Text::_('COM_USERREMINDER_DASH_KPI_NEVER_LOGGED_DESC')],
    ['COM_USERREMINDER_DASH_KPI_INACTIVE', $inactiveUsers, 'fa-user-check', 'secondary', 'index.php?option=com_userreminder&view=activeusers', Text::sprintf('COM_USERREMINDER_DASH_KPI_INACTIVE_DESC', $thresholds['existingDays'] ?? 180)],
    ['COM_USERREMINDER_DASH_KPI_DUE_NOW', $dueNow, 'fa-paper-plane', $dueNow > 0 ? 'danger' : 'success', 'index.php?option=com_userreminder&view=reminders', Text::_('COM_USERREMINDER_DASH_KPI_DUE_NOW_DESC') . ' · ' . Text::_('COM_USERREMINDER_DASH_CONFIG_BATCH') . ' ' . (int) ($thresholds['batchSize'] ?? 50)],
    ['COM_USERREMINDER_DASH_KPI_SENT_TOTAL', $sentTotal, 'fa-clipboard-list', 'primary', 'index.php?option=com_userreminder&view=log', null],
    ['COM_USERREMINDER_DASH_KPI_OPTEDOUT', $optedOut, 'fa-user-slash', 'dark', 'index.php?option=com_userreminder&view=optoutusers', Text::_('COM_USERREMINDER_DASH_KPI_TOTAL_USERS') . ': ' . number_format($totalUsers)],
];
?>
<div>
    <form action="<?php echo Route::_('index.php?option=com_userreminder&view=cpanel'); ?>" method="post" name="adminForm" id="adminForm">
        <?php echo HTMLHelper::_('form.token'); ?>
        <input type="hidden" name="task" value="">
        <input type="hidden" name="option" value="com_userreminder">
        <input type="hidden" name="view" value="cpanel">
    </form>

    <?php if (!$this->taskReady): ?>
        <div class="alert alert-danger d-flex align-items-center gap-3" role="alert">
            <i class="fas fa-plug fa-2x flex-shrink-0"></i>
            <div>
                <h4 class="alert-heading mb-1"><?php echo Text::_('COM_USERREMINDER_PLUGIN_DISABLED_HEADING'); ?></h4>
                <p class="mb-2"><?php echo Text::_('COM_USERREMINDER_PLUGIN_DISABLED_TEXT'); ?></p>
                <a class="btn btn-sm btn-light fw-bold" href="<?php echo Route::_('index.php?option=com_scheduler'); ?>">
                    <i class="fas fa-external-link-alt me-1"></i> <?php echo Text::_('COM_USERREMINDER_PLUGIN_DISABLED_CTA'); ?>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($alerts)): ?>
        <?php foreach ($alerts as $a): ?>
            <?php
            $key    = $a['key'] ?? '';
            $level  = $a['level'] ?? 'warning';
            $map    = $alertMap[$key] ?? null;
            $text   = $map['text'] ?? $key;
            $cls    = $map['class'] ?? $level;
            $cta    = $map['cta'] ?? '';
            $ctaUrl = $map['ctaUrl'] ?? '';
            $icon   = $a['icon'] ?? 'fa-exclamation-triangle';
            ?>
            <div class="alert alert-<?php echo $cls; ?> d-flex align-items-center gap-2 py-2" role="alert">
                <i class="fas <?php echo htmlspecialchars($icon, ENT_QUOTES); ?>"></i>
                <span class="flex-grow-1"><?php echo htmlspecialchars($text, ENT_QUOTES); ?></span>
                <?php if ($cta && $ctaUrl): ?>
                    <a class="btn btn-sm btn-outline-<?php echo $cls === 'danger' ? 'light' : $cls; ?> ms-2" href="<?php echo $ctaUrl; ?>"><?php echo htmlspecialchars($cta, ENT_QUOTES); ?></a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- KPI cards -->
    <div class="row g-3 mb-4">
        <?php foreach ($kpis as $k): ?>
            <div class="col-6 col-md-4 col-xxl-2">
                <a class="card text-decoration-none text-body shadow-sm h-100" href="<?php echo Route::_($k[4]); ?>">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-3 text-bg-<?php echo $k[3]; ?> p-2 fs-4" style="width:2.75rem;height:2.75rem;">
                                <i class="fas <?php echo $k[2]; ?>"></i>
                            </span>
                            <div style="min-width:0;">
                                <div class="fs-3 fw-bold lh-1"><?php echo number_format($k[1]); ?></div>
                                <div class="small text-uppercase text-muted fw-semibold text-truncate"><?php echo Text::_($k[0]); ?></div>
                            </div>
                        </div>
                        <?php if ($k[5] !== null): ?>
                            <div class="small text-muted mt-2"><?php echo htmlspecialchars($k[5], ENT_QUOTES); ?></div>
                        <?php endif; ?>
                        <?php if ($k[0] === 'COM_USERREMINDER_DASH_KPI_SENT_TOTAL'): ?>
                            <div class="small mt-2">
                                <span class="badge bg-primary-subtle text-primary-emphasis"><?php echo Text::_('COM_USERREMINDER_DASH_KPI_SENT_7D'); ?>: <?php echo number_format($sent7d); ?></span>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis"><?php echo Text::_('COM_USERREMINDER_DASH_KPI_SENT_30D'); ?>: <?php echo number_format($sent30d); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Analytics + Health -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-body d-flex align-items-center justify-content-between fw-semibold">
                    <span><i class="fas fa-chart-bar me-2 text-primary"></i><?php echo Text::_('COM_USERREMINDER_DASH_ANALYTICS_TITLE'); ?></span>
                    <small class="text-muted fw-normal"><?php echo htmlspecialchars($generatedAt, ENT_QUOTES); ?> · <?php echo Text::_('COM_USERREMINDER_DASH_CACHED'); ?></small>
                </div>
                <div class="card-body">
                    <h6 class="mb-2"><i class="fas fa-chart-line me-1 text-muted"></i> <?php echo Text::_('COM_USERREMINDER_DASH_TREND_30D'); ?></h6>
                    <div class="ur-chart mb-3">
                        <canvas id="ur-trend-canvas" style="display:none;" aria-label="<?php echo Text::_('COM_USERREMINDER_DASH_TREND_30D'); ?>" role="img"></canvas>
                        <div id="ur-trend-fallback" class="d-flex align-items-end gap-1 h-100 pb-2">
                            <?php if (empty($trend) || array_sum(array_column($trend, 'count')) === 0): ?>
                                <div class="text-muted small w-100 text-center py-4"><?php echo Text::_('COM_USERREMINDER_DASH_TREND_30D_EMPTY'); ?></div>
                            <?php else: ?>
                                <?php foreach ($trend as $pt): ?>
                                    <?php $h = max(4, (int) round(($pt['count'] / $trendMax) * 100)); ?>
                                    <div class="flex-fill bg-primary rounded-top" style="height: <?php echo $h; ?>%;" title="<?php echo htmlspecialchars($pt['date'] . ': ' . $pt['count'], ENT_QUOTES); ?>"></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <h6 class="mb-2"><i class="fas fa-chart-pie me-1 text-muted"></i> <?php echo Text::_('COM_USERREMINDER_DASH_BY_TYPE'); ?></h6>
                            <div class="ur-chart">
                                <canvas id="ur-bytype-canvas" style="display:none;" aria-label="<?php echo Text::_('COM_USERREMINDER_DASH_BY_TYPE'); ?>" role="img"></canvas>
                                <div id="ur-bytype-fallback">
                                <?php if ($byTypeTotal <= 1): ?>
                                    <div class="text-muted small py-3"><?php echo Text::_('COM_USERREMINDER_DASH_BY_TYPE_EMPTY'); ?></div>
                                <?php else: ?>
                                    <ul class="list-unstyled mb-0 small">
                                        <li class="d-flex justify-content-between py-1"><span><span class="d-inline-block rounded-circle bg-warning me-1" style="width:.6rem;height:.6rem;"></span> <?php echo Text::_('COM_USERREMINDER_DASH_TYPE_1'); ?></span><strong><?php echo number_format((int) ($byType[1] ?? 0)); ?> (<?php echo round(((int) ($byType[1] ?? 0) / $byTypeTotal) * 100); ?>%)</strong></li>
                                        <li class="d-flex justify-content-between py-1"><span><span class="d-inline-block rounded-circle bg-info me-1" style="width:.6rem;height:.6rem;"></span> <?php echo Text::_('COM_USERREMINDER_DASH_TYPE_2'); ?></span><strong><?php echo number_format((int) ($byType[2] ?? 0)); ?> (<?php echo round(((int) ($byType[2] ?? 0) / $byTypeTotal) * 100); ?>%)</strong></li>
                                        <li class="d-flex justify-content-between py-1"><span><span class="d-inline-block rounded-circle bg-secondary me-1" style="width:.6rem;height:.6rem;"></span> <?php echo Text::_('COM_USERREMINDER_DASH_TYPE_3'); ?></span><strong><?php echo number_format((int) ($byType[3] ?? 0)); ?> (<?php echo round(((int) ($byType[3] ?? 0) / $byTypeTotal) * 100); ?>%)</strong></li>
                                    </ul>
                                <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <h6 class="mb-2"><i class="fas fa-hourglass-half me-1 text-muted"></i> <?php echo Text::_('COM_USERREMINDER_DASH_AGING_TITLE'); ?></h6>
                            <div class="mb-3">
                                <div class="small fw-semibold text-muted mb-2"><?php echo Text::_('COM_USERREMINDER_DASH_AGING_PENDING'); ?></div>
                                <?php foreach (['1-7' => 'COM_USERREMINDER_DASH_AGING_PENDING_1_7', '8-30' => 'COM_USERREMINDER_DASH_AGING_PENDING_8_30', '30+' => 'COM_USERREMINDER_DASH_AGING_PENDING_30P'] as $key => $lang): ?>
                                    <?php $cnt = (int) ($pendingAging[$key] ?? 0); $pct = round(($cnt / $pendingMax) * 100); ?>
                                    <div class="row g-2 align-items-center mb-1">
                                        <div class="col-5 small text-muted text-nowrap"><?php echo Text::_($lang); ?></div>
                                        <div class="col">
                                            <div class="progress" role="progressbar" style="height:.75rem;"><div class="progress-bar bg-warning" style="width: <?php echo $pct; ?>%"></div></div>
                                        </div>
                                        <div class="col-auto small fw-semibold text-end" style="min-width:2.5rem;"><?php echo number_format($cnt); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div>
                                <div class="small fw-semibold text-muted mb-2"><?php echo Text::_('COM_USERREMINDER_DASH_AGING_INACTIVE'); ?></div>
                                <?php foreach (['just' => 'COM_USERREMINDER_DASH_AGING_INACTIVE_JUST', '2x' => 'COM_USERREMINDER_DASH_AGING_INACTIVE_2X', '4x' => 'COM_USERREMINDER_DASH_AGING_INACTIVE_4X'] as $key => $lang): ?>
                                    <?php $cnt = (int) ($inactiveAging[$key] ?? 0); $pct = round(($cnt / $inactiveMax) * 100); ?>
                                    <div class="row g-2 align-items-center mb-1">
                                        <div class="col-5 small text-muted text-nowrap"><?php echo Text::_($lang); ?></div>
                                        <div class="col">
                                            <div class="progress" role="progressbar" style="height:.75rem;"><div class="progress-bar bg-secondary" style="width: <?php echo $pct; ?>%"></div></div>
                                        </div>
                                        <div class="col-auto small fw-semibold text-end" style="min-width:2.5rem;"><?php echo number_format($cnt); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4 d-flex flex-column gap-3">
            <div class="card shadow-sm">
                <div class="card-header bg-body fw-semibold"><i class="fas fa-heartbeat me-2 text-success"></i><?php echo Text::_('COM_USERREMINDER_DASH_HEALTH_TITLE'); ?></div>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-plug me-2 text-muted"></i><?php echo Text::_('COM_USERREMINDER_DASH_HEALTH_PLUGIN'); ?></span>
                        <span class="badge bg-<?php echo $this->taskReady ? 'success' : 'danger'; ?>"><?php echo $this->taskReady ? Text::_('COM_USERREMINDER_DASH_ENABLED') : Text::_('COM_USERREMINDER_DASH_DISABLED'); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-clock me-2 text-muted"></i><?php echo Text::_('COM_USERREMINDER_DASH_HEALTH_SCHEDULER'); ?></span>
                        <span class="badge bg-<?php echo !empty($healthSum['schedulerEnabled']) ? 'success' : 'secondary'; ?>"><?php echo !empty($healthSum['schedulerEnabled']) ? Text::_('COM_USERREMINDER_DASH_ON') : Text::_('COM_USERREMINDER_DASH_OFF'); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-bug me-2 text-muted"></i><?php echo Text::_('COM_USERREMINDER_DASH_HEALTH_DEBUG'); ?></span>
                        <span class="badge bg-<?php echo !empty($healthSum['debugOn']) ? 'warning text-dark' : 'secondary'; ?>"><?php echo !empty($healthSum['debugOn']) ? Text::_('COM_USERREMINDER_DASH_ON') : Text::_('COM_USERREMINDER_DASH_OFF'); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-trash me-2 text-muted"></i><?php echo Text::_('COM_USERREMINDER_DASH_HEALTH_AUTODELETE'); ?></span>
                        <span class="badge bg-<?php echo !empty($healthSum['deleteEnabled']) ? 'danger' : 'secondary'; ?>"><?php echo !empty($healthSum['deleteEnabled']) ? Text::_('COM_USERREMINDER_DASH_ON') : Text::_('COM_USERREMINDER_DASH_OFF'); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-envelope me-2 text-muted"></i><?php echo Text::_('COM_USERREMINDER_DASH_HEALTH_MAIL'); ?></span>
                        <span class="badge bg-<?php echo !empty($healthSum['mailOk']) ? 'success' : 'danger'; ?>"><?php echo !empty($healthSum['mailOk']) ? Text::_('COM_USERREMINDER_DASH_OK') : Text::_('COM_USERREMINDER_DASH_FAIL'); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?php echo Text::_('COM_USERREMINDER_DASH_HEALTH_QUEUE'); ?></span>
                        <span><strong><?php echo number_format($healthSum['queueTotal'] ?? 0); ?></strong> / <strong class="<?php echo $dueNow > ($thresholds['batchSize'] ?? 50) ? 'text-danger' : 'text-success'; ?>"><?php echo number_format($dueNow); ?></strong></span>
                    </li>
                </ul>
                <?php if (empty($alerts)): ?>
                    <div class="card-footer small text-success"><i class="fas fa-check-circle me-1"></i> <?php echo Text::_('COM_USERREMINDER_DASH_ALERTS_NONE'); ?></div>
                <?php endif; ?>
            </div>
            <div class="card shadow-sm flex-fill">
                <div class="card-header bg-body fw-semibold"><i class="fas fa-cog me-2 text-muted"></i><?php echo Text::_('COM_USERREMINDER_DASH_CONFIG_TITLE'); ?></div>
                <div class="card-body p-0">
                    <table class="table table-sm small mb-0">
                        <tbody>
                            <tr><td class="text-muted"><?php echo Text::_('COM_USERREMINDER_DASH_CONFIG_DAYS'); ?></td><td class="text-end fw-semibold"><?php echo (int) ($config['days'] ?? $thresholds['days'] ?? 0); ?></td></tr>
                            <tr><td class="text-muted"><?php echo Text::_('COM_USERREMINDER_DASH_CONFIG_EXISTING_DAYS'); ?></td><td class="text-end fw-semibold"><?php echo (int) ($config['existingDays'] ?? $thresholds['existingDays'] ?? 0); ?></td></tr>
                            <tr><td class="text-muted"><?php echo Text::_('COM_USERREMINDER_DASH_CONFIG_MAX_REMINDERS'); ?></td><td class="text-end fw-semibold"><?php echo (int) ($config['maxReminders'] ?? $thresholds['maxReminders'] ?? 0); ?></td></tr>
                            <tr><td class="text-muted"><?php echo Text::_('COM_USERREMINDER_DASH_CONFIG_BATCH'); ?></td><td class="text-end fw-semibold"><?php echo (int) ($config['batchSize'] ?? $thresholds['batchSize'] ?? 0); ?></td></tr>
                            <tr><td class="text-muted"><?php echo Text::_('COM_USERREMINDER_DASH_CONFIG_MAX_PER_RUN'); ?></td><td class="text-end fw-semibold"><?php echo (int) ($config['maxPerRun'] ?? 0); ?></td></tr>
                            <tr><td class="text-muted"><?php echo Text::_('COM_USERREMINDER_DASH_CONFIG_SCHEDULE'); ?></td><td class="text-end"><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($config['scheduleText'] ?? '—', ENT_QUOTES); ?></span><?php if (!empty($config['nextRun'])): ?><div class="small text-muted text-nowrap mt-1"><?php echo HTMLHelper::_('date', $config['nextRun'], Text::_('DATE_FORMAT_LC2')); ?></div><?php endif; ?></td></tr>
                            <tr><td class="text-muted"><?php echo Text::_('COM_USERREMINDER_DASH_CONFIG_ACTIVATION'); ?></td><td class="text-end"><span class="badge bg-<?php echo !empty($config['activationOn']) ? 'success' : 'secondary'; ?>"><?php echo !empty($config['activationOn']) ? Text::_('COM_USERREMINDER_DASH_ENABLED') : Text::_('COM_USERREMINDER_DASH_DISABLED'); ?></span></td></tr>
                            <tr><td class="text-muted"><?php echo Text::_('COM_USERREMINDER_DASH_CONFIG_LOGIN'); ?></td><td class="text-end"><span class="badge bg-<?php echo !empty($config['loginOn']) ? 'success' : 'secondary'; ?>"><?php echo !empty($config['loginOn']) ? Text::_('COM_USERREMINDER_DASH_ENABLED') : Text::_('COM_USERREMINDER_DASH_DISABLED'); ?></span></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <a class="btn btn-sm btn-outline-primary w-100" href="<?php echo Route::_('index.php?option=com_config&view=component&component=com_userreminder'); ?>">
                        <i class="fas fa-cog me-1"></i> <?php echo Text::_('COM_USERREMINDER_CPANEL_PARAMS'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent + detail tables -->
    <div class="row g-3">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-body d-flex justify-content-between align-items-center fw-semibold">
                    <span><i class="fas fa-clipboard-list me-2 text-primary"></i><?php echo Text::_('COM_USERREMINDER_DASH_RECENT_LOGS'); ?></span>
                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_userreminder&view=log'); ?>"><?php echo Text::_('COM_USERREMINDER_DASH_VIEW_LOG'); ?> <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentLogs)): ?>
                        <div class="p-4 text-center text-muted fst-italic"><?php echo Text::_('COM_USERREMINDER_DASH_RECENT_LOGS_EMPTY'); ?></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead class="small text-uppercase text-muted">
                                    <tr>
                                        <th><?php echo Text::_('COM_USERREMINDER_USER_ID'); ?></th>
                                        <th><?php echo Text::_('COM_USERREMINDER_USER_NAME'); ?></th>
                                        <th><?php echo Text::_('COM_USERREMINDER_ACTION_DESCRIPTION'); ?></th>
                                        <th><?php echo Text::_('COM_USERREMINDER_ACTION_DATE'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentLogs as $row): ?>
                                        <tr>
                                            <td><?php echo (int) ($row['userId'] ?? $row['user_id'] ?? 0); ?></td>
                                            <td><?php echo htmlspecialchars($row['username'] ?? '', ENT_QUOTES); ?></td>
                                            <td class="text-truncate" style="max-width: 240px;"><?php echo htmlspecialchars($row['description'] ?? '', ENT_QUOTES); ?></td>
                                            <td class="text-nowrap small text-muted"><?php echo !empty($row['date']) ? HTMLHelper::_('date', $row['date'], Text::_('DATE_FORMAT_LC4')) : '—'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-body d-flex justify-content-between align-items-center fw-semibold">
                    <span class="small"><i class="fas fa-user-clock me-1 text-warning"></i> <?php echo Text::_('COM_USERREMINDER_DASH_OLDEST_PENDING'); ?></span>
                    <a class="btn btn-sm btn-outline-secondary py-0" href="<?php echo Route::_('index.php?option=com_userreminder&view=reminders'); ?>"><?php echo Text::_('COM_USERREMINDER_DASH_KPI_VIEW_ALL'); ?></a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($oldestPending)): ?>
                        <div class="p-3 text-center text-muted fst-italic small"><?php echo Text::_('COM_USERREMINDER_DASH_OLDEST_PENDING_EMPTY'); ?></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="small text-uppercase text-muted">
                                    <tr><th><?php echo Text::_('COM_USERREMINDER_NAME'); ?></th><th><?php echo Text::_('COM_USERREMINDER_EMAIL'); ?></th><th><?php echo Text::_('COM_USERREMINDER_REGISTRATIONDATE'); ?></th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($oldestPending as $u): ?>
                                        <tr>
                                            <td class="text-truncate" style="max-width:130px;"><?php echo htmlspecialchars($u['name'] ?? '', ENT_QUOTES); ?></td>
                                            <td class="text-truncate small" style="max-width:150px;"><?php echo htmlspecialchars($u['email'] ?? '', ENT_QUOTES); ?></td>
                                            <td class="small text-muted text-nowrap"><?php echo !empty($u['registerDate']) ? HTMLHelper::_('date', $u['registerDate'], Text::_('DATE_FORMAT_LC4')) : '—'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-body d-flex justify-content-between align-items-center fw-semibold">
                    <span class="small"><i class="fas fa-user-check me-1 text-secondary"></i> <?php echo Text::_('COM_USERREMINDER_DASH_LONGEST_INACTIVE'); ?></span>
                    <a class="btn btn-sm btn-outline-secondary py-0" href="<?php echo Route::_('index.php?option=com_userreminder&view=activeusers'); ?>"><?php echo Text::_('COM_USERREMINDER_DASH_KPI_VIEW_ALL'); ?></a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($longestInactive)): ?>
                        <div class="p-3 text-center text-muted fst-italic small"><?php echo Text::_('COM_USERREMINDER_DASH_LONGEST_INACTIVE_EMPTY'); ?></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="small text-uppercase text-muted">
                                    <tr><th><?php echo Text::_('COM_USERREMINDER_NAME'); ?></th><th><?php echo Text::_('COM_USERREMINDER_EMAIL'); ?></th><th><?php echo Text::_('COM_USERREMINDER_LASTLOGINDATE'); ?></th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($longestInactive as $u): ?>
                                        <tr>
                                            <td class="text-truncate" style="max-width:130px;"><?php echo htmlspecialchars($u['name'] ?? '', ENT_QUOTES); ?></td>
                                            <td class="text-truncate small" style="max-width:150px;"><?php echo htmlspecialchars($u['email'] ?? '', ENT_QUOTES); ?></td>
                                            <td class="small text-muted text-nowrap"><?php echo !empty($u['lastvisitDate']) ? HTMLHelper::_('date', $u['lastvisitDate'], Text::_('DATE_FORMAT_LC4')) : '—'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="text-end text-muted small mt-3">
        <?php echo Text::_('COM_USERREMINDER_DASH_GENERATED'); ?>: <?php echo htmlspecialchars($generatedAt, ENT_QUOTES); ?> ·
        <?php echo number_format($totalUsers); ?> <?php echo Text::_('COM_USERREMINDER_DASH_KPI_TOTAL_USERS'); ?>
    </div>
    <noscript>
        <div class="alert alert-info"><i class="fas fa-info-circle me-1"></i> <?php echo Text::_('COM_USERREMINDER_DASH_FALLBACK_CHART'); ?></div>
    </noscript>
</div>
