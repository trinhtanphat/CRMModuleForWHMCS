<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/crmconnector.php';

use Illuminate\Database\Capsule\Manager as Capsule;

add_hook('ClientAdd', 1, function ($vars) {
    crmconnector_handle_auto_sync((int) ($vars['userid'] ?? 0));
});

add_hook('ClientEdit', 1, function ($vars) {
    crmconnector_handle_auto_sync((int) ($vars['userid'] ?? 0));
});

add_hook('DailyCronJob', 1, function () {
    $settings = crmconnector_get_settings();
    if (($settings['auto_sync'] ?? '') !== 'on') {
        $processedFollowups = crmconnector_process_due_followups();
        $executedRules = crmconnector_process_automation_rules();
        $cleanedLimits = crmconnector_cleanup_api_rate_limits();
        $cleanedObs = crmconnector_cleanup_old_observability_data();
        crmconnector_log(null, 'daily_cron', 'completed', 'Followups: ' . $processedFollowups . ', Rules: ' . $executedRules . ', API Cleanup: ' . $cleanedLimits . ', Webhooks Cleanup: ' . (int) $cleanedObs['webhooks'] . ', Audit Cleanup: ' . (int) $cleanedObs['audit']);
        return;
    }

    $failedOrPendingIds = Capsule::table(CRMCONNECTOR_TABLE)
        ->whereIn('crm_status', ['failed', 'pending'])
        ->pluck('userid');

    foreach ($failedOrPendingIds as $userId) {
        crmconnector_sync_user((int) $userId, $settings);
    }

    $processedFollowups = crmconnector_process_due_followups();
    $executedRules = crmconnector_process_automation_rules();
    $cleanedLimits = crmconnector_cleanup_api_rate_limits();
    $cleanedObs = crmconnector_cleanup_old_observability_data();
    crmconnector_log(null, 'daily_cron', 'completed', 'Followups: ' . $processedFollowups . ', Rules: ' . $executedRules . ', API Cleanup: ' . $cleanedLimits . ', Webhooks Cleanup: ' . (int) $cleanedObs['webhooks'] . ', Audit Cleanup: ' . (int) $cleanedObs['audit']);
});

function crmconnector_handle_auto_sync($userId)
{
    $settings = crmconnector_get_settings();
    if (($settings['auto_sync'] ?? '') !== 'on') {
        return;
    }

    crmconnector_sync_user($userId, $settings);
}

function crmconnector_get_settings()
{
    $rows = Capsule::table('tbladdonmodules')
        ->where('module', 'crmconnector')
        ->get();

    $settings = [];
    foreach ($rows as $row) {
        $settings[$row->setting] = $row->value;
    }

    return $settings;
}
