<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/CrmClient.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use WHMCS\Module\Addon\CrmConnector\CrmClient;

const CRMCONNECTOR_TABLE = 'mod_crmconnector_contacts';
const CRMCONNECTOR_LOGS_TABLE = 'mod_crmconnector_logs';
const CRMCONNECTOR_NOTES_TABLE = 'mod_crmconnector_notes';

function crmconnector_config()
{
    return [
        'name' => 'CRM Connector',
        'description' => 'Sync WHMCS clients to an external CRM endpoint.',
        'version' => '1.1.0',
        'author' => 'CRMModuleForWHMCS',
        'language' => 'english',
        'fields' => [
            'endpoint' => [
                'FriendlyName' => 'CRM Endpoint URL',
                'Type' => 'text',
                'Size' => '80',
                'Description' => 'Example: https://crm.example.com/api',
            ],
            'api_key' => [
                'FriendlyName' => 'API Key',
                'Type' => 'password',
                'Size' => '50',
                'Description' => 'API key used for Bearer authentication.',
            ],
            'default_tag' => [
                'FriendlyName' => 'Default CRM Tag',
                'Type' => 'text',
                'Size' => '30',
                'Default' => 'whmcs',
                'Description' => 'Tag attached to synced contacts.',
            ],
            'auto_sync' => [
                'FriendlyName' => 'Auto Sync via Hook',
                'Type' => 'yesno',
                'Description' => 'Enable client sync on create/edit hooks.',
            ],
        ],
    ];
}

function crmconnector_activate()
{
    try {
        if (!Capsule::schema()->hasTable(CRMCONNECTOR_TABLE)) {
            Capsule::schema()->create(CRMCONNECTOR_TABLE, function ($table) {
                $table->increments('id');
                $table->integer('userid')->unsigned()->unique();
                $table->string('crm_external_id', 100)->nullable();
                $table->string('crm_status', 30)->default('pending');
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable(CRMCONNECTOR_LOGS_TABLE)) {
            Capsule::schema()->create(CRMCONNECTOR_LOGS_TABLE, function ($table) {
                $table->increments('id');
                $table->integer('userid')->unsigned()->nullable();
                $table->string('action', 40);
                $table->string('status', 30);
                $table->text('message')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable(CRMCONNECTOR_NOTES_TABLE)) {
            Capsule::schema()->create(CRMCONNECTOR_NOTES_TABLE, function ($table) {
                $table->increments('id');
                $table->integer('userid')->unsigned();
                $table->integer('adminid')->unsigned()->nullable();
                $table->text('note');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        return [
            'status' => 'success',
            'description' => 'CRM Connector activated successfully.',
        ];
    } catch (\Exception $exception) {
        return [
            'status' => 'error',
            'description' => 'Unable to activate module: ' . $exception->getMessage(),
        ];
    }
}

function crmconnector_deactivate()
{
    return [
        'status' => 'success',
        'description' => 'CRM Connector deactivated. Data table retained intentionally.',
    ];
}

function crmconnector_output($vars)
{
    $moduleLink = $vars['modulelink'];
    $message = '';

    if (isset($_POST['crmconnector_action'])) {
        check_token('WHMCS.admin.default');
        $message = crmconnector_handle_post_action($vars);
    }

    if (isset($_GET['crmconnector_action']) && $_GET['crmconnector_action'] === 'export_logs') {
        check_token('WHMCS.admin.default');
        crmconnector_export_logs_csv();
        return;
    }

    if ($message !== '') {
        echo '<div class="alert alert-info">' . htmlspecialchars($message) . '</div>';
    }

    $records = Capsule::table(CRMCONNECTOR_TABLE)
        ->leftJoin('tblclients', 'tblclients.id', '=', CRMCONNECTOR_TABLE . '.userid')
        ->select(
            CRMCONNECTOR_TABLE . '.userid',
            CRMCONNECTOR_TABLE . '.crm_external_id',
            CRMCONNECTOR_TABLE . '.crm_status',
            CRMCONNECTOR_TABLE . '.last_synced_at',
            'tblclients.firstname',
            'tblclients.lastname',
            'tblclients.email'
        )
        ->orderBy(CRMCONNECTOR_TABLE . '.updated_at', 'desc')
        ->limit(50)
        ->get();

    $logs = Capsule::table(CRMCONNECTOR_LOGS_TABLE)
        ->orderBy('id', 'desc')
        ->limit(20)
        ->get();

    $failedRecords = Capsule::table(CRMCONNECTOR_TABLE)
        ->where('crm_status', 'failed')
        ->orderBy('updated_at', 'desc')
        ->limit(100)
        ->get();

    $notes = Capsule::table(CRMCONNECTOR_NOTES_TABLE)
        ->leftJoin('tblclients', 'tblclients.id', '=', CRMCONNECTOR_NOTES_TABLE . '.userid')
        ->leftJoin('tbladmins', 'tbladmins.id', '=', CRMCONNECTOR_NOTES_TABLE . '.adminid')
        ->select(
            CRMCONNECTOR_NOTES_TABLE . '.id',
            CRMCONNECTOR_NOTES_TABLE . '.userid',
            CRMCONNECTOR_NOTES_TABLE . '.note',
            CRMCONNECTOR_NOTES_TABLE . '.created_at',
            'tblclients.firstname',
            'tblclients.lastname',
            'tbladmins.username as admin_username'
        )
        ->orderBy(CRMCONNECTOR_NOTES_TABLE . '.id', 'desc')
        ->limit(30)
        ->get();

    echo '<h2>CRM Connector Dashboard</h2>';
    echo '<p>Manual sync controls and recent synchronization status.</p>';

    echo '<form method="post" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:16px;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="sync_single">';
    echo '<label>WHMCS User ID: <input type="number" name="userid" min="1" required></label> ';
    echo '<button type="submit" class="btn btn-primary">Sync User</button>';
    echo '</form>';

    echo '<form method="post" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:20px;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="sync_all">';
    echo '<button type="submit" class="btn btn-default">Sync All Clients</button>';
    echo '</form>';

    echo '<h3>Retry Queue</h3>';
    echo '<form method="post" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:10px;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="retry_failed_all">';
    echo '<button type="submit" class="btn btn-warning">Retry All Failed</button>';
    echo '</form>';

    echo '<form method="post" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:20px;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="retry_selected">';
    echo '<table class="table table-bordered table-condensed">';
    echo '<thead><tr><th>Select</th><th>User ID</th><th>Status</th><th>Last Synced</th></tr></thead><tbody>';
    foreach ($failedRecords as $failed) {
        echo '<tr>';
        echo '<td><input type="checkbox" name="retry_userids[]" value="' . (int) $failed->userid . '"></td>';
        echo '<td>' . (int) $failed->userid . '</td>';
        echo '<td>' . htmlspecialchars((string) $failed->crm_status) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($failed->last_synced_at ?? '')) . '</td>';
        echo '</tr>';
    }
    if (count($failedRecords) === 0) {
        echo '<tr><td colspan="4">No failed records in queue.</td></tr>';
    }
    echo '</tbody></table>';
    echo '<button type="submit" class="btn btn-default">Retry Selected</button>';
    echo '</form>';

    echo '<table class="table table-striped">';
    echo '<thead><tr><th>User ID</th><th>Name</th><th>Email</th><th>External ID</th><th>Status</th><th>Last Synced</th></tr></thead><tbody>';
    foreach ($records as $record) {
        $name = trim(($record->firstname ?? '') . ' ' . ($record->lastname ?? ''));
        echo '<tr>';
        echo '<td>' . (int) $record->userid . '</td>';
        echo '<td>' . htmlspecialchars($name) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($record->email ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($record->crm_external_id ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($record->crm_status ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($record->last_synced_at ?? '')) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    echo '<h3>Recent Sync Logs</h3>';
    echo '<form method="get" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:10px;">';
    echo '<input type="hidden" name="module" value="crmconnector">';
    echo '<input type="hidden" name="crmconnector_action" value="export_logs">';
    echo generate_token('form');
    echo '<button type="submit" class="btn btn-success">Export Logs CSV</button>';
    echo '</form>';

    echo '<table class="table table-condensed">';
    echo '<thead><tr><th>Time</th><th>User ID</th><th>Action</th><th>Status</th><th>Message</th></tr></thead><tbody>';
    foreach ($logs as $log) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars((string) ($log->created_at ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($log->userid ?? '-')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($log->action ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($log->status ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($log->message ?? '')) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    echo '<h3>CRM Notes</h3>';
    echo '<form method="post" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:20px;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="add_note">';
    echo '<label>WHMCS User ID: <input type="number" name="note_userid" min="1" required></label> ';
    echo '<label>Note: <input type="text" name="note_content" size="80" required></label> ';
    echo '<button type="submit" class="btn btn-primary">Add Note</button>';
    echo '</form>';

    echo '<table class="table table-striped">';
    echo '<thead><tr><th>Time</th><th>User ID</th><th>Client</th><th>Admin</th><th>Note</th></tr></thead><tbody>';
    foreach ($notes as $noteRow) {
        $clientName = trim(($noteRow->firstname ?? '') . ' ' . ($noteRow->lastname ?? ''));
        echo '<tr>';
        echo '<td>' . htmlspecialchars((string) ($noteRow->created_at ?? '')) . '</td>';
        echo '<td>' . (int) $noteRow->userid . '</td>';
        echo '<td>' . htmlspecialchars($clientName) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($noteRow->admin_username ?? 'system')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($noteRow->note ?? '')) . '</td>';
        echo '</tr>';
    }
    if (count($notes) === 0) {
        echo '<tr><td colspan="5">No notes yet.</td></tr>';
    }
    echo '</tbody></table>';
}

function crmconnector_handle_post_action(array $vars)
{
    $action = (string) ($_POST['crmconnector_action'] ?? '');

    if ($action === 'sync_single') {
        $userId = (int) ($_POST['userid'] ?? 0);
        return crmconnector_sync_user($userId, $vars);
    }

    if ($action === 'sync_all') {
        return crmconnector_sync_all($vars);
    }

    if ($action === 'retry_failed_all') {
        return crmconnector_retry_failed_all($vars);
    }

    if ($action === 'retry_selected') {
        $selectedIds = $_POST['retry_userids'] ?? [];
        if (!is_array($selectedIds)) {
            return 'Invalid retry selection.';
        }

        return crmconnector_retry_selected($selectedIds, $vars);
    }

    if ($action === 'add_note') {
        $userId = (int) ($_POST['note_userid'] ?? 0);
        $note = trim((string) ($_POST['note_content'] ?? ''));
        return crmconnector_add_note($userId, $note);
    }

    return 'No action executed.';
}

function crmconnector_retry_failed_all(array $vars)
{
    $userIds = Capsule::table(CRMCONNECTOR_TABLE)
        ->where('crm_status', 'failed')
        ->pluck('userid');

    $synced = 0;
    $total = count($userIds);
    foreach ($userIds as $userId) {
        $message = crmconnector_sync_user((int) $userId, $vars);
        if (strpos($message, 'synced successfully') !== false) {
            $synced++;
        }
    }

    $summary = 'Retry completed. Synced ' . $synced . ' of ' . $total . ' failed records.';
    crmconnector_log(null, 'retry_failed_all', 'completed', $summary);
    return $summary;
}

function crmconnector_retry_selected(array $selectedIds, array $vars)
{
    $synced = 0;
    $total = 0;

    foreach ($selectedIds as $selectedId) {
        $userId = (int) $selectedId;
        if ($userId <= 0) {
            continue;
        }

        $total++;
        $message = crmconnector_sync_user($userId, $vars);
        if (strpos($message, 'synced successfully') !== false) {
            $synced++;
        }
    }

    $summary = 'Retry selected completed. Synced ' . $synced . ' of ' . $total . ' selected records.';
    crmconnector_log(null, 'retry_selected', 'completed', $summary);
    return $summary;
}

function crmconnector_add_note($userId, $note)
{
    if ($userId <= 0 || $note === '') {
        return 'User ID and note content are required.';
    }

    $clientExists = Capsule::table('tblclients')->where('id', $userId)->exists();
    if (!$clientExists) {
        return 'Cannot add note. Client not found.';
    }

    $adminId = isset($_SESSION['adminid']) ? (int) $_SESSION['adminid'] : null;

    Capsule::table(CRMCONNECTOR_NOTES_TABLE)->insert([
        'userid' => $userId,
        'adminid' => $adminId,
        'note' => $note,
        'created_at' => Capsule::raw('NOW()'),
        'updated_at' => Capsule::raw('NOW()'),
    ]);

    crmconnector_log($userId, 'add_note', 'completed', 'Note added by admin.');
    return 'Note added for client #' . $userId . '.';
}

function crmconnector_export_logs_csv()
{
    $logs = Capsule::table(CRMCONNECTOR_LOGS_TABLE)
        ->orderBy('id', 'desc')
        ->limit(5000)
        ->get();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="crmconnector-logs.csv"');

    $output = fopen('php://output', 'w');
    if ($output === false) {
        exit;
    }

    fputcsv($output, ['id', 'created_at', 'userid', 'action', 'status', 'message']);
    foreach ($logs as $log) {
        fputcsv($output, [
            $log->id,
            $log->created_at,
            $log->userid,
            $log->action,
            $log->status,
            $log->message,
        ]);
    }

    fclose($output);
    exit;
}

function crmconnector_sync_user($userId, array $vars)
{
    if ($userId <= 0) {
        crmconnector_log($userId, 'sync_user', 'failed', 'Invalid user ID.');
        return 'Invalid user ID.';
    }

    $client = Capsule::table('tblclients')->where('id', $userId)->first();
    if (!$client) {
        crmconnector_log($userId, 'sync_user', 'failed', 'Client not found.');
        return 'Client not found.';
    }

    $crmClient = new CrmClient((string) ($vars['endpoint'] ?? ''), (string) ($vars['api_key'] ?? ''));
    $result = $crmClient->upsertContact([
        'userid' => $client->id,
        'firstname' => $client->firstname,
        'lastname' => $client->lastname,
        'email' => $client->email,
        'companyname' => $client->companyname,
        'tag' => (string) ($vars['default_tag'] ?? 'whmcs'),
    ]);

    $status = $result['success'] ? 'synced' : 'failed';
    Capsule::table(CRMCONNECTOR_TABLE)->updateOrInsert(
        ['userid' => $client->id],
        [
            'crm_external_id' => $result['external_id'] ?? null,
            'crm_status' => $status,
            'last_synced_at' => Capsule::raw('NOW()'),
            'updated_at' => Capsule::raw('NOW()'),
            'created_at' => Capsule::raw('NOW()'),
        ]
    );

    if ($result['success']) {
        crmconnector_log((int) $client->id, 'sync_user', 'synced', 'Client synced successfully.');
        return 'Client #' . $client->id . ' synced successfully.';
    }

    crmconnector_log((int) $client->id, 'sync_user', 'failed', (string) ($result['message'] ?? 'Unknown error'));
    return 'Sync failed for client #' . $client->id . ': ' . ($result['message'] ?? 'Unknown error');
}

function crmconnector_sync_all(array $vars)
{
    $clientIds = Capsule::table('tblclients')->pluck('id');
    $successCount = 0;

    foreach ($clientIds as $clientId) {
        $message = crmconnector_sync_user((int) $clientId, $vars);
        if (strpos($message, 'synced successfully') !== false) {
            $successCount++;
        }
    }

    crmconnector_log(null, 'sync_all', 'completed', 'Synced ' . $successCount . ' of ' . count($clientIds) . ' clients.');
    return 'Completed. Synced ' . $successCount . ' of ' . count($clientIds) . ' clients.';
}

function crmconnector_log($userId, $action, $status, $message)
{
    Capsule::table(CRMCONNECTOR_LOGS_TABLE)->insert([
        'userid' => $userId,
        'action' => (string) $action,
        'status' => (string) $status,
        'message' => (string) $message,
        'created_at' => Capsule::raw('NOW()'),
    ]);
}
