<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/CrmClient.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use WHMCS\Module\Addon\CrmConnector\CrmClient;

const CRMCONNECTOR_TABLE = 'mod_crmconnector_contacts';
const CRMCONNECTOR_LOGS_TABLE = 'mod_crmconnector_logs';

function crmconnector_config()
{
    return [
        'name' => 'CRM Connector',
        'description' => 'Sync WHMCS clients to an external CRM endpoint.',
        'version' => '1.0.0',
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

    if (isset($_POST['crmconnector_action'])) {
        check_token('WHMCS.admin.default');

        $action = (string) $_POST['crmconnector_action'];
        if ($action === 'sync_single') {
            $userId = (int) ($_POST['userid'] ?? 0);
            $message = crmconnector_sync_user($userId, $vars);
            echo '<div class="alert alert-info">' . htmlspecialchars($message) . '</div>';
        }

        if ($action === 'sync_all') {
            $result = crmconnector_sync_all($vars);
            echo '<div class="alert alert-info">' . htmlspecialchars($result) . '</div>';
        }
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
