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

$dashboard = $this->dashboard ?? [];
$kpi       = $dashboard['kpi'] ?? [];
$analytics = $dashboard['analytics'] ?? [];
$recent    = $dashboard['recent'] ?? [];
$health    = $dashboard['health'] ?? [];
$config    = $dashboard['configSnapshot'] ?? [];
$thresholds = $dashboard['thresholds'] ?? [];

$pendingActivation = (int) ($kpi['pendingActivation'] ?? 0);
$neverLoggedIn     = (int) ($kpi['neverLoggedIn'] ?? 0);
$pendingTotal      = (int) ($kpi['pendingTotal'] ?? ($pendingActivation + $neverLoggedIn));
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

$app = Factory::getApplication();

// Helpers for alert rendering.
$alertMap = [
    'plugin_disabled'  => ['class' => 'danger',  'text' => Text::_('COM_USERREMINDER_DASH_ALERT_PLUGIN_DISABLED'), 'cta' => Text::_('COM_USERREMINDER_PLUGIN_DISABLED_CTA'), 'ctaUrl' => Route::_('index.php?option=com_plugins&filter_folder=system&filter_search=userreminder')],
    'scheduler_off'    => ['class' => 'warning', 'text' => Text::sprintf('COM_USERREMINDER_DASH_ALERT_SCHEDULER_OFF', ($healthSum['queueTotal'] ?? $pendingTotal + $inactiveUsers))],
    'scheduler_nothing'=> ['class' => 'warning', 'text' => Text::_('COM_USERREMINDER_DASH_ALERT_SCHEDULER_NOTHING')],
    'debug_on'         => ['class' => 'info',    'text' => Text::_('COM_USERREMINDER_DASH_ALERT_DEBUG_ON')],
    'delete_enabled'   => ['class' => 'danger',  'text' => Text::_('COM_USERREMINDER_DASH_ALERT_DELETE_ENABLED')],
    'mail_invalid'     => ['class' => 'danger',  'text' => Text::_('COM_USERREMINDER_DASH_ALERT_MAIL_INVALID')],
    'bcc_missing'      => ['class' => 'warning', 'text' => Text::_('COM_USERREMINDER_DASH_ALERT_BCC_MISSING')],
    'backlog_pressure' => ['class' => 'warning', 'text' => Text::sprintf('COM_USERREMINDER_DASH_ALERT_BACKLOG', $dueNow, $thresholds['batchSize'] ?? 50)],
];

// Trend max for fallback bars.
$trendMax = 1;
foreach ($trend as $pt) {
    $trendMax = max($trendMax, (int) ($pt['count'] ?? 0));
}
$byTypeTotal = max(1, (int) ($byType[1] ?? 0) + (int) ($byType[2] ?? 0) + (int) ($byType[3] ?? 0));

// Aging maxes.
$pendingAging = $aging['pending'] ?? ['1-7' => 0, '8-30' => 0, '30+' => 0];
$inactiveAging = $aging['inactive'] ?? ['just' => 0, '2x' => 0, '4x' => 0];
$pendingMax  = max(1, max(array_values($pendingAging ?: [0])));
$inactiveMax = max(1, max(array_values($inactiveAging ?: [0])));
?>
<div class="ur-dashboard">
    <form action="<?php echo Route::_('index.php?option=com_userreminder&view=cpanel'); ?>" method="post" name="adminForm" id="adminForm">
        <?php echo HTMLHelper::_('form.token'); ?>
        <input type="hidden" name="task" value="">
        <input type="hidden" name="option" value="com_userreminder">
        <input type="hidden" name="view" value="cpanel">
    </form>

    <?php if (!$this->systemPluginEnabled): ?>
        <div class="alert alert-danger d-flex align-items-center gap-3" role="alert">
            <i class="fas fa-plug fa-2x flex-shrink-0"></i>
            <div>
                <h4 class="alert-heading mb-1"><?php echo Text::_('COM_USERREMINDER_PLUGIN_DISABLED_HEADING'); ?></h4>
                <p class="mb-2"><?php echo Text::_('COM_USERREMINDER_PLUGIN_DISABLED_TEXT'); ?></p>
                <a class="btn btn-sm btn-light fw-bold" href="<?php echo Route::_('index.php?option=com_plugins&filter_folder=system&filter_search=userreminder'); ?>">
                    <i class="fas fa-external-link-alt me-1"></i> <?php echo Text::_('COM_USERREMINDER_PLUGIN_DISABLED_CTA'); ?>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($alerts)): ?>
        <?php foreach ($alerts as $a): ?>
            <?php
            $key   = $a['key'] ?? '';
            $level = $a['level'] ?? 'warning';
            $map   = $alertMap[$key] ?? null;
            $text  = $map['text'] ?? $key;
            $cls   = $map['class'] ?? $level;
            $cta   = $map['cta'] ?? '';
            $ctaUrl= $map['ctaUrl'] ?? '';
            $icon  = $a['icon'] ?? 'fa-exclamation-triangle';
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
    <div class="row ur-kpi-grid g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-2">
            <a class="card ur-kpi-card ur-kpi-accent-warning h-100 text-decoration-none" href="<?php echo Route::_('index.php?option=com_userreminder&view=reminders'); ?>">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="ur-kpi-icon"><i class="fas fa-user-clock"></i></span>
                        <div>
                            <div class="ur-kpi-value"><?php echo number_format($pendingActivation); ?></div>
                            <div class="ur-kpi-label"><?php echo Text::_('COM_USERREMINDER_DASH_KPI_PENDING_ACTIVATION'); ?></div>
                        </div>
                    </div>
                    <div class="ur-kpi-sub mt-2"><?php echo Text::_('COM_USERREMINDER_DASH_KPI_PENDING_ACTIVATION_DESC'); ?></div>
                </div>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <a class="card ur-kpi-card ur-kpi-accent-info h-100 text-decoration-none" href="<?php echo Route::_('index.php?option=com_userreminder&view=reminders'); ?>">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="ur-kpi-icon"><i class="fas fa-user-plus"></i></span>
                        <div>
                            <div class="ur-kpi-value"><?php echo number_format($neverLoggedIn); ?></div>
                            <div class="ur-kpi-label"><?php echo Text::_('COM_USERREMINDER_DASH_KPI_NEVER_LOGGED'); ?></div>
                        </div>
                    </div>
                    <div class="ur-kpi-sub mt-2"><?php echo Text::_('COM_USERREMINDER_DASH_KPI_NEVER_LOGGED_DESC'); ?></div>
                </div>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <a class="card ur-kpi-card ur-kpi-accent-secondary h-100 text-decoration-none" href="<?php echo Route::_('index.php?option=com_userreminder&view=activeusers'); ?>">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="ur-kpi-icon"><i class="fas fa-user-check"></i></span>
                        <div>
                            <div class="ur-kpi-value"><?php echo number_format($inactiveUsers); ?></div>
                            <div class="ur-kpi-label"><?php echo Text::_('COM_USERREMINDER_DASH_KPI_INACTIVE'); ?></div>
                        </div>
                    </div>
                    <div class="ur-kpi-sub mt-2"><?php echo Text::sprintf('COM_USERREMINDER_DASH_KPI_INACTIVE_DESC', $thresholds['existingDays'] ?? 180); ?></div>
                </div>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <a class="card ur-kpi-card <?php echo $dueNow > 0 ? 'ur-kpi-accent-danger' : 'ur-kpi-accent-success'; ?> h-100 text-decoration-none" href="<?php echo Route::_('index.php?option=com_userreminder&view=reminders'); ?>">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="ur-kpi-icon"><i class="fas fa-paper-plane"></i></span>
                        <div>
                            <div class="ur-kpi-value"><?php echo number_format($dueNow); ?></div>
                            <div class="ur-kpi-label"><?php echo Text::_('COM_USERREMINDER_DASH_KPI_DUE_NOW'); ?></div>
                        </div>
                    </div>
                    <div class="ur-kpi-sub mt-2"><?php echo Text::_('COM_USERREMINDER_DASH_KPI_DUE_NOW_DESC'); ?> · <?php echo Text::sprintf('COM_USERREMINDER_DASH_CONFIG_BATCH', ''); ?> <?php echo (int) ($thresholds['batchSize'] ?? 50); ?></div>
                </div>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <a class="card ur-kpi-card ur-kpi-accent-primary h-100 text-decoration-none" href="<?php echo Route::_('index.php?option=com_userreminder&view=log'); ?>">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="ur-kpi-icon"><i class="fas fa-clipboard-list"></i></span>
                        <div>
                            <div class="ur-kpi-value"><?php echo number_format($sentTotal); ?></div>
                            <div class="ur-kpi-label"><?php echo Text::_('COM_USERREMINDER_DASH_KPI_SENT_TOTAL'); ?></div>
                        </div>
                    </div>
                    <div class="ur-kpi-sub mt-2">
                        <span class="badge bg-primary"><?php echo Text::_('COM_USERREMINDER_DASH_KPI_SENT_7D'); ?>: <?php echo number_format($sent7d); ?></span>
                        <span class="badge bg-secondary ms-1"><?php echo Text::_('COM_USERREMINDER_DASH_KPI_SENT_30D'); ?>: <?php echo number_format($sent30d); ?></span>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <a class="card ur-kpi-card ur-kpi-accent-dark h-100 text-decoration-none" href="<?php echo Route::_('index.php?option=com_userreminder&view=optoutusers'); ?>">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="ur-kpi-icon"><i class="fas fa-user-slash"></i></span>
                        <div>
                            <div class="ur-kpi-value"><?php echo number_format($optedOut); ?></div>
                            <div class="ur-kpi-label"><?php echo Text::_('COM_USERREMINDER_DASH_KPI_OPTEDOUT'); ?></div>
                        </div>
                    </div>
                    <div class="ur-kpi-sub mt-2"><?php echo Text::_('COM_USERREMINDER_DASH_KPI_TOTAL_USERS'); ?>: <?php echo number_format($totalUsers); ?></div>
                </div>
            </a>
        </div>
    </div>

    <!-- Row 2: Analytics + Health -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><i class="fas fa-chart-bar me-2 text-primary"></i><?php echo Text::_('COM_USERREMINDER_DASH_ANALYTICS_TITLE'); ?></span>
                    <small class="text-muted"><?php echo htmlspecialchars($generatedAt, ENT_QUOTES); ?> · <?php echo Text::_('COM_USERREMINDER_DASH_CACHED'); ?> 10m</small>
                </div>
                <div class="card-body">
                    <h6 class="mb-2"><i class="fas fa-chart-line me-1 text-muted"></i> <?php echo Text::_('COM_USERREMINDER_DASH_TREND_30D'); ?></h6>
                    <div class="ur-chart-wrap mb-3">
                        <canvas id="ur-trend-canvas" style="display:none;" aria-label="<?php echo Text::_('COM_USERREMINDER_DASH_TREND_30D'); ?>" role="img"></canvas>
                        <div id="ur-trend-fallback" class="ur-fallback-bars">
                            <?php if (empty($trend) || array_sum(array_column($trend, 'count')) === 0): ?>
                                <div class="text-muted small w-100 text-center py-4"><?php echo Text::_('COM_USERREMINDER_DASH_TREND_30D_EMPTY'); ?></div>
                            <?php else: ?>
                                <?php foreach ($trend as $pt): ?>
                                    <?php $h = max(4, (int) round(($pt['count'] / $trendMax) * 100)); ?>
                                    <div class="ur-bar" style="height: <?php echo $h; ?>%;" title="<?php echo htmlspecialchars($pt['date'] . ': ' . $pt['count'], ENT_QUOTES); ?>"></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <h6 class="mb-2"><i class="fas fa-chart-pie me-1 text-muted"></i> <?php echo Text::_('COM_USERREMINDER_DASH_BY_TYPE'); ?></h6>
                            <div class="ur-chart-wrap" style="min-height: 200px;">
                                <canvas id="ur-bytype-canvas" style="display:none; height: 200px !important;" aria-label="<?php echo Text::_('COM_USERREMINDER_DASH_BY_TYPE'); ?>" role="img"></canvas>
                                <div id="ur-bytype-fallback">
                                    <?php if ($byTypeTotal <= 1): ?>
                                        <div class="text-muted small py-3"><?php echo Text::_('COM_USERREMINDER_DASH_BY_TYPE_EMPTY'); ?></div>
                                    <?php else: ?>
                                        <ul class="list-unstyled mb-0 small">
                                            <li class="d-flex justify-content-between py-1"><span><span class="ur-health-dot" style="background:#ffb340"></span> <?php echo Text::_('COM_USERREMINDER_DASH_TYPE_1'); ?></span><strong><?php echo number_format((int)($byType[1] ?? 0)); ?> (<?php echo round(((int)($byType[1] ?? 0) / $byTypeTotal) * 100); ?>%)</strong></li>
                                            <li class="d-flex justify-content-between py-1"><span><span class="ur-health-dot" style="background:#0dcaf0"></span> <?php echo Text::_('COM_USERREMINDER_DASH_TYPE_2'); ?></span><strong><?php echo number_format((int)($byType[2] ?? 0)); ?> (<?php echo round(((int)($byType[2] ?? 0) / $byTypeTotal) * 100); ?>%)</strong></li>
                                            <li class="d-flex justify-content-between py-1"><span><span class="ur-health-dot" style="background:#6c757d"></span> <?php echo Text::_('COM_USERREMINDER_DASH_TYPE_3'); ?></span><strong><?php echo number_format((int)($byType[3] ?? 0)); ?> (<?php echo round(((int)($byType[3] ?? 0) / $byTypeTotal) * 100); ?>%)</strong></li>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <h6 class="mb-2"><i class="fas fa-hourglass-half me-1 text-muted"></i> <?php echo Text::_('COM_USERREMINDER_DASH_AGING_TITLE'); ?></h6>
                            <div class="mb-3">
                                <div class="small fw-semibold text-muted mb-1"><?php echo Text::_('COM_USERREMINDER_DASH_AGING_PENDING'); ?></div>
                                <?php foreach (['1-7' => 'COM_USERREMINDER_DASH_AGING_PENDING_1_7', '8-30' => 'COM_USERREMINDER_DASH_AGING_PENDING_8_30', '30+' => 'COM_USERREMINDER_DASH_AGING_PENDING_30P'] as $k => $lang): ?>
                                    <?php $cnt = (int) ($pendingAging[$k] ?? 0); $pct = round(($cnt / $pendingMax) * 100); ?>
                                    <div class="ur-aging-row">
                                        <span class="ur-aging-label"><?php echo Text::_($lang); ?></span>
                                        <span class="ur-aging-track"><span class="ur-aging-fill pending" style="width: <?php echo $pct; ?>%"></span></span>
                                        <span class="ur-aging-count"><?php echo number_format($cnt); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div>
                                <div class="small fw-semibold text-muted mb-1"><?php echo Text::_('COM_USERREMINDER_DASH_AGING_INACTIVE'); ?></div>
                                <?php foreach (['just' => 'COM_USERREMINDER_DASH_AGING_INACTIVE_JUST', '2x' => 'COM_USERREMINDER_DASH_AGING_INACTIVE_2X', '4x' => 'COM_USERREMINDER_DASH_AGING_INACTIVE_4X'] as $k => $lang): ?>
                                    <?php $cnt = (int) ($inactiveAging[$k] ?? 0); $pct = round(($cnt / $inactiveMax) * 100); ?>
                                    <div class="ur-aging-row">
                                        <span class="ur-aging-label"><?php echo Text::_($lang); ?></span>
                                        <span class="ur-aging-track"><span class="ur-aging-fill inactive" style="width: <?php echo $pct; ?>%"></span></span>
                                        <span class="ur-aging-count"><?php echo number_format($cnt); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-heartbeat me-2 text-success"></i><?php echo Text::_('COM_USERREMINDER_DASH_HEALTH_TITLE'); ?></div>
                <ul class="list-group list-group-flush ur-health-list">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-plug me-2 text-muted"></i>System plugin</span>
                        <span class="badge bg-<?php echo $this->systemPluginEnabled ? 'success' : 'danger'; ?>"><?php echo $this->systemPluginEnabled ? Text::_('COM_USERREMINDER_DASH_ENABLED') : Text::_('COM_USERREMINDER_DASH_DISABLED'); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-clock me-2 text-muted"></i>Scheduler</span>
                        <span class="badge bg-<?php echo !empty($healthSum['schedulerEnabled']) ? 'success' : 'secondary'; ?>"><?php echo !empty($healthSum['schedulerEnabled']) ? Text::_('COM_USERREMINDER_DASH_ON') : Text::_('COM_USERREMINDER_DASH_OFF'); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-bug me-2 text-muted"></i>Debug mode</span>
                        <span class="badge bg-<?php echo !empty($healthSum['debugOn']) ? 'warning text-dark' : 'secondary'; ?>"><?php echo !empty($healthSum['debugOn']) ? Text::_('COM_USERREMINDER_DASH_ON') : Text::_('COM_USERREMINDER_DASH_OFF'); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-trash me-2 text-muted"></i>Auto-delete</span>
                        <span class="badge bg-<?php echo !empty($healthSum['deleteEnabled']) ? 'danger' : 'secondary'; ?>"><?php echo !empty($healthSum['deleteEnabled']) ? Text::_('COM_USERREMINDER_DASH_ON') : Text::_('COM_USERREMINDER_DASH_OFF'); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-envelope me-2 text-muted"></i>Mail</span>
                        <span class="badge bg-<?php echo !empty($healthSum['mailOk']) ? 'success' : 'danger'; ?>"><?php echo !empty($healthSum['mailOk']) ? 'OK' : 'Fail'; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Queue / Due now</span>
                        <span><strong><?php echo number_format($healthSum['queueTotal'] ?? 0); ?></strong> / <strong class="<?php echo $dueNow > ($thresholds['batchSize'] ?? 50) ? 'text-danger' : 'text-success'; ?>"><?php echo number_format($dueNow); ?></strong></span>
                    </li>
                </ul>
                <?php if (empty($alerts)): ?>
                    <div class="card-footer small text-success"><i class="fas fa-check-circle me-1"></i> <?php echo Text::_('COM_USERREMINDER_DASH_ALERTS_NONE'); ?></div>
                <?php endif; ?>
            </div>
            <div class="card">
                <div class="card-header"><i class="fas fa-cog me-2 text-muted"></i><?php echo Text::_('COM_USERREMINDER_DASH_CONFIG_TITLE'); ?></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 ur-config-table">
                        <tbody>
                            <tr><td><?php echo Text::_('COM_USERREMINDER_DASH_CONFIG_DAYS'); ?></td><td class="text-end fw-semibold"><?php echo (int) ($config['days'] ?? $thresholds['days'] ?? 0); ?></td></tr>
                            <tr><td><?php echo Text::_('COM_USERREMINDER_DASH_CONFIG_EXISTING_DAYS'); ?></td><td class="text-end fw-semibold"><?php echo (int) ($config['existingDays'] ?? $thresholds['existingDays'] ?? 0); ?></td></tr>
                            <tr><td><?php echo Text::_('COM_USERREMINDER_DASH_CONFIG_MAX_REMINDERS'); ?></td><td class="text-end fw-semibold"><?php echo (int) ($config['maxReminders'] ?? $thresholds['maxReminders'] ?? 0); ?></td></tr>
                            <tr><td><?php echo Text::_('COM_USERREMINDER_DASH_CONFIG_BATCH'); ?></td><td class="text-end fw-semibold"><?php echo (int) ($config['batchSize'] ?? $thresholds['batchSize'] ?? 0); ?></td></tr>
                            <tr><td><?php echo Text::_('COM_USERREMINDER_DASH_CONFIG_MAX_PER_RUN'); ?></td><td class="text-end fw-semibold"><?php echo (int) ($config['maxPerRun'] ?? 0); ?></td></tr>
                            <tr><td><?php echo Text::_('COM_USERREMINDER_DASH_CONFIG_SCHEDULE'); ?></td><td class="text-end"><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($config['scheduleText'] ?? '—', ENT_QUOTES); ?></span></td></tr>
                            <tr><td><?php echo Text::_('COM_USERREMINDER_DASH_CONFIG_ACTIVATION'); ?></td><td class="text-end"><span class="badge bg-<?php echo !empty($config['activationOn']) ? 'success' : 'secondary'; ?>"><?php echo !empty($config['activationOn']) ? Text::_('COM_USERREMINDER_DASH_ENABLED') : Text::_('COM_USERREMINDER_DASH_DISABLED'); ?></span></td></tr>
                            <tr><td><?php echo Text::_('COM_USERREMINDER_DASH_CONFIG_LOGIN'); ?></td><td class="text-end"><span class="badge bg-<?php echo !empty($config['loginOn']) ? 'success' : 'secondary'; ?>"><?php echo !empty($config['loginOn']) ? Text::_('COM_USERREMINDER_DASH_ENABLED') : Text::_('COM_USERREMINDER_DASH_DISABLED'); ?></span></td></tr>
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

    <!-- Row 3: Tables + Actions -->
    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-clipboard-list me-2 text-primary"></i><?php echo Text::_('COM_USERREMINDER_DASH_RECENT_LOGS'); ?></span>
                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_userreminder&view=log'); ?>"><?php echo Text::_('COM_USERREMINDER_DASH_VIEW_LOG'); ?> <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentLogs)): ?>
                        <div class="p-4 text-center ur-empty"><?php echo Text::_('COM_USERREMINDER_DASH_RECENT_LOGS_EMPTY'); ?></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0 ur-recent-table">
                                <thead>
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
                                            <td class="text-nowrap small text-muted"><?php echo htmlspecialchars($row['date'] ?? '', ENT_QUOTES); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="small fw-semibold"><i class="fas fa-user-clock me-1 text-warning"></i> <?php echo Text::_('COM_USERREMINDER_DASH_OLDEST_PENDING'); ?></span>
                            <a class="btn btn-sm btn-outline-secondary py-0" href="<?php echo Route::_('index.php?option=com_userreminder&view=reminders'); ?>"><?php echo Text::_('COM_USERREMINDER_DASH_KPI_VIEW_ALL'); ?></a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($oldestPending)): ?>
                                <div class="p-3 text-center ur-empty small"><?php echo Text::_('COM_USERREMINDER_DASH_OLDEST_PENDING_EMPTY'); ?></div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0 ur-recent-table">
                                        <thead><tr><th><?php echo Text::_('COM_USERREMINDER_NAME'); ?></th><th><?php echo Text::_('COM_USERREMINDER_EMAIL'); ?></th><th><?php echo Text::_('COM_USERREMINDER_REGISTRATIONDATE'); ?></th></tr></thead>
                                        <tbody>
                                            <?php foreach ($oldestPending as $u): ?>
                                                <tr>
                                                    <td class="text-truncate" style="max-width:130px;"><?php echo htmlspecialchars($u['name'] ?? '', ENT_QUOTES); ?></td>
                                                    <td class="text-truncate small" style="max-width:150px;"><?php echo htmlspecialchars($u['email'] ?? '', ENT_QUOTES); ?></td>
                                                    <td class="small text-muted text-nowrap"><?php echo htmlspecialchars($u['registerDate'] ?? '', ENT_QUOTES); ?></td>
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
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="small fw-semibold"><i class="fas fa-user-check me-1 text-secondary"></i> <?php echo Text::_('COM_USERREMINDER_DASH_LONGEST_INACTIVE'); ?></span>
                            <a class="btn btn-sm btn-outline-secondary py-0" href="<?php echo Route::_('index.php?option=com_userreminder&view=activeusers'); ?>"><?php echo Text::_('COM_USERREMINDER_DASH_KPI_VIEW_ALL'); ?></a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($longestInactive)): ?>
                                <div class="p-3 text-center ur-empty small"><?php echo Text::_('COM_USERREMINDER_DASH_LONGEST_INACTIVE_EMPTY'); ?></div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0 ur-recent-table">
                                        <thead><tr><th><?php echo Text::_('COM_USERREMINDER_NAME'); ?></th><th><?php echo Text::_('COM_USERREMINDER_EMAIL'); ?></th><th><?php echo Text::_('COM_USERREMINDER_LASTLOGINDATE'); ?></th></tr></thead>
                                        <tbody>
                                            <?php foreach ($longestInactive as $u): ?>
                                                <tr>
                                                    <td class="text-truncate" style="max-width:130px;"><?php echo htmlspecialchars($u['name'] ?? '', ENT_QUOTES); ?></td>
                                                    <td class="text-truncate small" style="max-width:150px;"><?php echo htmlspecialchars($u['email'] ?? '', ENT_QUOTES); ?></td>
                                                    <td class="small text-muted text-nowrap"><?php echo htmlspecialchars($u['lastvisitDate'] ?? '', ENT_QUOTES); ?></td>
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
        </div>
        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-bolt me-2 text-warning"></i><?php echo Text::_('COM_USERREMINDER_DASH_ACTIONS_TITLE'); ?></div>
                <div class="list-group list-group-flush">
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?php echo Route::_('index.php?option=com_userreminder&view=reminders'); ?>">
                        <span><i class="fas fa-user-clock me-2 text-warning"></i> <?php echo Text::_('COM_USERREMINDER_CPANEL_REMINDERS'); ?></span>
                        <span class="badge bg-warning text-dark rounded-pill"><?php echo number_format($pendingTotal); ?></span>
                    </a>
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?php echo Route::_('index.php?option=com_userreminder&view=activeusers'); ?>">
                        <span><i class="fas fa-user-check me-2 text-secondary"></i> <?php echo Text::_('COM_USERREMINDER_CPANEL_ACTIVE_USERS'); ?></span>
                        <span class="badge bg-secondary rounded-pill"><?php echo number_format($inactiveUsers); ?></span>
                    </a>
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?php echo Route::_('index.php?option=com_userreminder&view=optoutusers'); ?>">
                        <span><i class="fas fa-user-slash me-2 text-dark"></i> <?php echo Text::_('COM_USERREMINDER_CPANEL_OPTOUT'); ?></span>
                        <span class="badge bg-dark rounded-pill"><?php echo number_format($optedOut); ?></span>
                    </a>
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?php echo Route::_('index.php?option=com_userreminder&view=log'); ?>">
                        <span><i class="fas fa-clipboard-list me-2 text-primary"></i> <?php echo Text::_('COM_USERREMINDER_CPANEL_LOG'); ?></span>
                        <span class="badge bg-primary rounded-pill"><?php echo number_format($sentTotal); ?></span>
                    </a>
                    <a class="list-group-item list-group-item-action" href="<?php echo Route::_('index.php?option=com_config&view=component&component=com_userreminder'); ?>">
                        <i class="fas fa-cog me-2 text-muted"></i> <?php echo Text::_('COM_USERREMINDER_CPANEL_PARAMS'); ?>
                    </a>
                    <a class="list-group-item list-group-item-action" href="<?php echo Route::_('index.php?option=com_userreminder&view=help'); ?>">
                        <i class="fas fa-info-circle me-2 text-info"></i> <?php echo Text::_('COM_USERREMINDER_CPANEL_HELP'); ?>
                    </a>
                </div>
                <div class="card-body border-top">
                    <div class="d-grid gap-2">
                        <a class="btn btn-success" href="<?php echo Route::_('index.php?option=com_userreminder&view=reminders'); ?>">
                            <i class="fas fa-paper-plane me-1"></i> <?php echo Text::_('COM_USERREMINDER_DASH_ACTION_SEND_REMINDERS'); ?>
                        </a>
                        <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_userreminder&view=activeusers'); ?>">
                            <i class="fas fa-paper-plane me-1"></i> <?php echo Text::_('COM_USERREMINDER_DASH_ACTION_SEND_ACTIVE'); ?>
                        </a>
                    </div>
                    <div class="text-center mt-3">
                        <small class="text-muted d-block"><?php echo Text::_('COM_USERREMINDER_DASH_GENERATED'); ?>: <?php echo htmlspecialchars($generatedAt, ENT_QUOTES); ?></small>
                        <small class="text-muted"><?php echo number_format($totalUsers); ?> <?php echo Text::_('COM_USERREMINDER_DASH_KPI_TOTAL_USERS'); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <noscript>
        <div class="alert alert-info"><i class="fas fa-info-circle me-1"></i> <?php echo Text::_('COM_USERREMINDER_DASH_FALLBACK_CHART'); ?></div>
    </noscript>
</div>
