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
const CRMCONNECTOR_LEADS_TABLE = 'mod_crmconnector_leads';
const CRMCONNECTOR_DEALS_TABLE = 'mod_crmconnector_deals';
const CRMCONNECTOR_FOLLOWUPS_TABLE = 'mod_crmconnector_followups';
const CRMCONNECTOR_CAMPAIGNS_TABLE = 'mod_crmconnector_campaigns';
const CRMCONNECTOR_AUTOMATION_TABLE = 'mod_crmconnector_automation_rules';
const CRMCONNECTOR_LABELS_TABLE = 'mod_crmconnector_labels';
const CRMCONNECTOR_CONTACT_TYPES_TABLE = 'mod_crmconnector_contact_types';
const CRMCONNECTOR_WEBFORMS_TABLE = 'mod_crmconnector_webforms';
const CRMCONNECTOR_LEAD_CAMPAIGNS_TABLE = 'mod_crmconnector_lead_campaigns';
const CRMCONNECTOR_LEAD_LABELS_TABLE = 'mod_crmconnector_lead_labels';
const CRMCONNECTOR_PERMISSIONS_TABLE = 'mod_crmconnector_permissions';
const CRMCONNECTOR_API_RATE_LIMITS_TABLE = 'mod_crmconnector_api_rate_limits';
const CRMCONNECTOR_API_TOKENS_TABLE = 'mod_crmconnector_api_tokens';
const CRMCONNECTOR_WEBHOOK_DELIVERIES_TABLE = 'mod_crmconnector_webhook_deliveries';
const CRMCONNECTOR_AUDIT_TRAIL_TABLE = 'mod_crmconnector_audit_trail';
const CRMCONNECTOR_MODULE_VERSION = '1.5.0';
const CRMCONNECTOR_SCHEMA_VERSION = '2026.03.02.5';

function crmconnector_config()
{
    return [
        'name' => 'CRM Connector',
        'description' => 'Sync WHMCS clients to an external CRM endpoint.',
        'version' => CRMCONNECTOR_MODULE_VERSION,
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
            'restrict_write_admins' => [
                'FriendlyName' => 'Restrict Write Access',
                'Type' => 'yesno',
                'Description' => 'If enabled, only admins listed below can run write actions.',
            ],
            'write_admin_ids' => [
                'FriendlyName' => 'Write Admin IDs',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Comma-separated admin IDs, e.g. 1,2,5',
            ],
            'webform_token' => [
                'FriendlyName' => 'Webform Token',
                'Type' => 'password',
                'Size' => '50',
                'Description' => 'Shared token for public webform endpoint authentication.',
            ],
            'api_token' => [
                'FriendlyName' => 'API Token',
                'Type' => 'password',
                'Size' => '80',
                'Description' => 'Bearer token for CRM API endpoint authentication.',
            ],
            'api_rate_limit_per_min' => [
                'FriendlyName' => 'API Rate Limit/Min',
                'Type' => 'text',
                'Size' => '6',
                'Default' => '60',
                'Description' => 'Max API requests per minute per token and IP.',
            ],
            'api_token_ttl_days' => [
                'FriendlyName' => 'API Token TTL Days',
                'Type' => 'text',
                'Size' => '6',
                'Default' => '90',
                'Description' => 'Expiry in days for rotated API tokens.',
            ],
            'webhook_url' => [
                'FriendlyName' => 'Webhook URL',
                'Type' => 'text',
                'Size' => '80',
                'Description' => 'Optional outbound webhook endpoint for CRM events.',
            ],
            'webhook_secret' => [
                'FriendlyName' => 'Webhook Secret',
                'Type' => 'password',
                'Size' => '80',
                'Description' => 'HMAC secret used to sign webhook payloads.',
            ],
            'webhook_events' => [
                'FriendlyName' => 'Webhook Events',
                'Type' => 'text',
                'Size' => '80',
                'Default' => 'api_created,api_updated,api_deleted',
                'Description' => 'Comma-separated event names to send.',
            ],
            'api_default_per_page' => [
                'FriendlyName' => 'API Default Per Page',
                'Type' => 'text',
                'Size' => '6',
                'Default' => '25',
                'Description' => 'Default pagination size for API list endpoints.',
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

        if (!Capsule::schema()->hasTable(CRMCONNECTOR_LEADS_TABLE)) {
            Capsule::schema()->create(CRMCONNECTOR_LEADS_TABLE, function ($table) {
                $table->increments('id');
                $table->integer('userid')->unsigned()->nullable();
                $table->string('name', 150);
                $table->string('email', 190)->nullable();
                $table->string('status', 40)->default('new');
                $table->string('source', 80)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable(CRMCONNECTOR_DEALS_TABLE)) {
            Capsule::schema()->create(CRMCONNECTOR_DEALS_TABLE, function ($table) {
                $table->increments('id');
                $table->integer('lead_id')->unsigned()->nullable();
                $table->string('title', 180);
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('stage', 40)->default('qualification');
                $table->timestamp('expected_close_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable(CRMCONNECTOR_FOLLOWUPS_TABLE)) {
            Capsule::schema()->create(CRMCONNECTOR_FOLLOWUPS_TABLE, function ($table) {
                $table->increments('id');
                $table->integer('lead_id')->unsigned()->nullable();
                $table->integer('userid')->unsigned()->nullable();
                $table->string('title', 190);
                $table->string('channel', 30)->default('email');
                $table->string('status', 30)->default('pending');
                $table->timestamp('due_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable(CRMCONNECTOR_CAMPAIGNS_TABLE)) {
            Capsule::schema()->create(CRMCONNECTOR_CAMPAIGNS_TABLE, function ($table) {
                $table->increments('id');
                $table->string('name', 150);
                $table->string('status', 30)->default('active');
                $table->text('description')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable(CRMCONNECTOR_AUTOMATION_TABLE)) {
            Capsule::schema()->create(CRMCONNECTOR_AUTOMATION_TABLE, function ($table) {
                $table->increments('id');
                $table->string('name', 140);
                $table->string('trigger_event', 80);
                $table->string('action_type', 80);
                $table->string('is_enabled', 5)->default('yes');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable(CRMCONNECTOR_LABELS_TABLE)) {
            Capsule::schema()->create(CRMCONNECTOR_LABELS_TABLE, function ($table) {
                $table->increments('id');
                $table->string('name', 120);
                $table->string('color', 30)->default('#007bff');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable(CRMCONNECTOR_CONTACT_TYPES_TABLE)) {
            Capsule::schema()->create(CRMCONNECTOR_CONTACT_TYPES_TABLE, function ($table) {
                $table->increments('id');
                $table->string('name', 120);
                $table->string('is_active', 5)->default('yes');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable(CRMCONNECTOR_WEBFORMS_TABLE)) {
            Capsule::schema()->create(CRMCONNECTOR_WEBFORMS_TABLE, function ($table) {
                $table->increments('id');
                $table->string('name', 150);
                $table->string('default_status', 40)->default('new');
                $table->integer('contact_type_id')->unsigned()->nullable();
                $table->string('is_active', 5)->default('yes');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable(CRMCONNECTOR_LEAD_CAMPAIGNS_TABLE)) {
            Capsule::schema()->create(CRMCONNECTOR_LEAD_CAMPAIGNS_TABLE, function ($table) {
                $table->increments('id');
                $table->integer('lead_id')->unsigned();
                $table->integer('campaign_id')->unsigned();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable(CRMCONNECTOR_LEAD_LABELS_TABLE)) {
            Capsule::schema()->create(CRMCONNECTOR_LEAD_LABELS_TABLE, function ($table) {
                $table->increments('id');
                $table->integer('lead_id')->unsigned();
                $table->integer('label_id')->unsigned();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable(CRMCONNECTOR_PERMISSIONS_TABLE)) {
            Capsule::schema()->create(CRMCONNECTOR_PERMISSIONS_TABLE, function ($table) {
                $table->increments('id');
                $table->integer('adminid')->unsigned();
                $table->string('action_key', 80);
                $table->string('is_allowed', 5)->default('yes');
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable(CRMCONNECTOR_API_RATE_LIMITS_TABLE)) {
            Capsule::schema()->create(CRMCONNECTOR_API_RATE_LIMITS_TABLE, function ($table) {
                $table->increments('id');
                $table->string('token_hash', 64);
                $table->string('ip_address', 45);
                $table->string('window_key', 20);
                $table->integer('request_count')->unsigned()->default(0);
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable(CRMCONNECTOR_API_TOKENS_TABLE)) {
            Capsule::schema()->create(CRMCONNECTOR_API_TOKENS_TABLE, function ($table) {
                $table->increments('id');
                $table->string('name', 120);
                $table->string('token_hash', 64)->unique();
                $table->string('last4', 4);
                $table->string('is_active', 5)->default('yes');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable(CRMCONNECTOR_WEBHOOK_DELIVERIES_TABLE)) {
            Capsule::schema()->create(CRMCONNECTOR_WEBHOOK_DELIVERIES_TABLE, function ($table) {
                $table->increments('id');
                $table->string('event_name', 80);
                $table->string('status', 30);
                $table->integer('http_code')->unsigned()->nullable();
                $table->text('response_body')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable(CRMCONNECTOR_AUDIT_TRAIL_TABLE)) {
            Capsule::schema()->create(CRMCONNECTOR_AUDIT_TRAIL_TABLE, function ($table) {
                $table->increments('id');
                $table->string('actor_type', 30);
                $table->integer('actor_id')->unsigned()->nullable();
                $table->string('resource', 40);
                $table->integer('resource_id')->unsigned()->nullable();
                $table->string('action', 30);
                $table->longText('before_snapshot')->nullable();
                $table->longText('after_snapshot')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        crmconnector_set_setting('schema_version', CRMCONNECTOR_SCHEMA_VERSION);

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

function crmconnector_upgrade($vars)
{
    $result = crmconnector_activate();
    if (($result['status'] ?? '') === 'success') {
        crmconnector_set_setting('schema_version', CRMCONNECTOR_SCHEMA_VERSION);
    }

    return $result;
}

function crmconnector_output($vars)
{
    $moduleLink = $vars['modulelink'];
    $message = '';
    $schemaVersion = crmconnector_get_setting('schema_version', 'unknown');
    $canWrite = crmconnector_has_write_access($vars);

    if (isset($_POST['crmconnector_action'])) {
        check_token('WHMCS.admin.default');
        $message = crmconnector_handle_post_action($vars);
    }

    if (isset($_GET['crmconnector_action']) && $_GET['crmconnector_action'] === 'export_logs') {
        check_token('WHMCS.admin.default');
        crmconnector_export_logs_csv();
        return;
    }

    if (isset($_GET['crmconnector_action']) && $_GET['crmconnector_action'] === 'export_leads') {
        check_token('WHMCS.admin.default');
        crmconnector_export_leads_csv();
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

    $leads = Capsule::table(CRMCONNECTOR_LEADS_TABLE)
        ->orderBy('id', 'desc')
        ->limit(50)
        ->get();

    $deals = Capsule::table(CRMCONNECTOR_DEALS_TABLE)
        ->leftJoin(CRMCONNECTOR_LEADS_TABLE, CRMCONNECTOR_LEADS_TABLE . '.id', '=', CRMCONNECTOR_DEALS_TABLE . '.lead_id')
        ->select(
            CRMCONNECTOR_DEALS_TABLE . '.id',
            CRMCONNECTOR_DEALS_TABLE . '.title',
            CRMCONNECTOR_DEALS_TABLE . '.amount',
            CRMCONNECTOR_DEALS_TABLE . '.stage',
            CRMCONNECTOR_DEALS_TABLE . '.expected_close_at',
            CRMCONNECTOR_LEADS_TABLE . '.name as lead_name'
        )
        ->orderBy(CRMCONNECTOR_DEALS_TABLE . '.id', 'desc')
        ->limit(50)
        ->get();

    $followups = Capsule::table(CRMCONNECTOR_FOLLOWUPS_TABLE)
        ->orderBy('id', 'desc')
        ->limit(50)
        ->get();

    $campaigns = Capsule::table(CRMCONNECTOR_CAMPAIGNS_TABLE)
        ->orderBy('id', 'desc')
        ->limit(50)
        ->get();

    $rules = Capsule::table(CRMCONNECTOR_AUTOMATION_TABLE)
        ->orderBy('id', 'desc')
        ->limit(50)
        ->get();

    $labels = Capsule::table(CRMCONNECTOR_LABELS_TABLE)
        ->orderBy('id', 'desc')
        ->limit(50)
        ->get();

    $contactTypes = Capsule::table(CRMCONNECTOR_CONTACT_TYPES_TABLE)
        ->orderBy('id', 'desc')
        ->limit(50)
        ->get();

    $webforms = Capsule::table(CRMCONNECTOR_WEBFORMS_TABLE)
        ->orderBy('id', 'desc')
        ->limit(50)
        ->get();

    $leadCampaignAssignments = Capsule::table(CRMCONNECTOR_LEAD_CAMPAIGNS_TABLE)
        ->leftJoin(CRMCONNECTOR_LEADS_TABLE, CRMCONNECTOR_LEADS_TABLE . '.id', '=', CRMCONNECTOR_LEAD_CAMPAIGNS_TABLE . '.lead_id')
        ->leftJoin(CRMCONNECTOR_CAMPAIGNS_TABLE, CRMCONNECTOR_CAMPAIGNS_TABLE . '.id', '=', CRMCONNECTOR_LEAD_CAMPAIGNS_TABLE . '.campaign_id')
        ->select(
            CRMCONNECTOR_LEAD_CAMPAIGNS_TABLE . '.id',
            CRMCONNECTOR_LEADS_TABLE . '.name as lead_name',
            CRMCONNECTOR_CAMPAIGNS_TABLE . '.name as campaign_name',
            CRMCONNECTOR_LEAD_CAMPAIGNS_TABLE . '.created_at'
        )
        ->orderBy(CRMCONNECTOR_LEAD_CAMPAIGNS_TABLE . '.id', 'desc')
        ->limit(100)
        ->get();

    $admins = Capsule::table('tbladmins')
        ->select('id', 'username')
        ->orderBy('id', 'asc')
        ->get();

    $permissionRules = crmconnector_get_permission_rules();
    $actionKeys = crmconnector_get_action_keys();
    $apiTokens = Capsule::table(CRMCONNECTOR_API_TOKENS_TABLE)
        ->orderBy('id', 'desc')
        ->limit(30)
        ->get();
    $apiUsage24h = Capsule::table(CRMCONNECTOR_LOGS_TABLE)
        ->where('action', 'like', 'api_%')
        ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-24 hours')))
        ->count();
    $apiUsage1h = Capsule::table(CRMCONNECTOR_LOGS_TABLE)
        ->where('action', 'like', 'api_%')
        ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-1 hour')))
        ->count();
    $selectedCampaignId = (int) ($_GET['filter_campaign_id'] ?? 0);
    $selectedLeadStatus = trim((string) ($_GET['filter_lead_status'] ?? ''));

    $filteredCampaignLeads = [];
    if ($selectedCampaignId > 0) {
        $query = Capsule::table(CRMCONNECTOR_LEAD_CAMPAIGNS_TABLE)
            ->leftJoin(CRMCONNECTOR_LEADS_TABLE, CRMCONNECTOR_LEADS_TABLE . '.id', '=', CRMCONNECTOR_LEAD_CAMPAIGNS_TABLE . '.lead_id')
            ->select(
                CRMCONNECTOR_LEAD_CAMPAIGNS_TABLE . '.id',
                CRMCONNECTOR_LEAD_CAMPAIGNS_TABLE . '.campaign_id',
                CRMCONNECTOR_LEADS_TABLE . '.id as lead_id',
                CRMCONNECTOR_LEADS_TABLE . '.name as lead_name',
                CRMCONNECTOR_LEADS_TABLE . '.email as lead_email',
                CRMCONNECTOR_LEADS_TABLE . '.status as lead_status'
            )
            ->where(CRMCONNECTOR_LEAD_CAMPAIGNS_TABLE . '.campaign_id', $selectedCampaignId);

        if ($selectedLeadStatus !== '') {
            $query->where(CRMCONNECTOR_LEADS_TABLE . '.status', $selectedLeadStatus);
        }

        $filteredCampaignLeads = $query->orderBy(CRMCONNECTOR_LEADS_TABLE . '.id', 'desc')->limit(200)->get();
    }

    $leadLabelAssignments = Capsule::table(CRMCONNECTOR_LEAD_LABELS_TABLE)
        ->leftJoin(CRMCONNECTOR_LEADS_TABLE, CRMCONNECTOR_LEADS_TABLE . '.id', '=', CRMCONNECTOR_LEAD_LABELS_TABLE . '.lead_id')
        ->leftJoin(CRMCONNECTOR_LABELS_TABLE, CRMCONNECTOR_LABELS_TABLE . '.id', '=', CRMCONNECTOR_LEAD_LABELS_TABLE . '.label_id')
        ->select(
            CRMCONNECTOR_LEAD_LABELS_TABLE . '.id',
            CRMCONNECTOR_LEAD_LABELS_TABLE . '.lead_id',
            CRMCONNECTOR_LEAD_LABELS_TABLE . '.label_id',
            CRMCONNECTOR_LEADS_TABLE . '.name as lead_name',
            CRMCONNECTOR_LABELS_TABLE . '.name as label_name',
            CRMCONNECTOR_LABELS_TABLE . '.color as label_color',
            CRMCONNECTOR_LEAD_LABELS_TABLE . '.created_at'
        )
        ->orderBy(CRMCONNECTOR_LEAD_LABELS_TABLE . '.id', 'desc')
        ->limit(300)
        ->get();

    echo '<h2>CRM Connector Dashboard</h2>';
    echo '<p>Manual sync controls and recent synchronization status.</p>';
    echo '<p><strong>Module:</strong> ' . htmlspecialchars(CRMCONNECTOR_MODULE_VERSION) . ' | <strong>Schema:</strong> ' . htmlspecialchars($schemaVersion) . '</p>';
    if (!$canWrite) {
        echo '<div class="alert alert-warning">Read-only mode: your admin account is not allowed to run write actions in this module.</div>';
    }

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

    echo '<h3>Leads (Phase 2 MVP)</h3>';
    echo '<form method="get" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:10px;">';
    echo '<input type="hidden" name="module" value="crmconnector">';
    echo '<input type="hidden" name="crmconnector_action" value="export_leads">';
    echo generate_token('form');
    echo '<button type="submit" class="btn btn-success">Export Leads CSV</button>';
    echo '</form>';

    echo '<form method="post" enctype="multipart/form-data" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:10px;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="import_leads_csv">';
    echo '<label>Import Leads CSV: <input type="file" name="leads_csv_file" accept=".csv" required></label> ';
    echo '<button type="submit" class="btn btn-default">Import CSV</button>';
    echo '</form>';

    echo '<form method="post" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:20px;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="add_lead">';
    echo '<label>Name: <input type="text" name="lead_name" required></label> ';
    echo '<label>Email: <input type="email" name="lead_email"></label> ';
    echo '<label>Status: <select name="lead_status"><option>new</option><option>active</option><option>won</option><option>lost</option></select></label> ';
    echo '<button type="submit" class="btn btn-primary">Add Lead</button>';
    echo '</form>';
    echo '<table class="table table-striped"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th></tr></thead><tbody>';
    foreach ($leads as $lead) {
        echo '<tr><td>' . (int) $lead->id . '</td><td>' . htmlspecialchars((string) $lead->name) . '</td><td>' . htmlspecialchars((string) ($lead->email ?? '')) . '</td><td>' . htmlspecialchars((string) $lead->status) . '</td></tr>';
    }
    if (count($leads) === 0) {
        echo '<tr><td colspan="4">No leads yet.</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h3>Deals / Pipeline (Phase 2 MVP)</h3>';
    echo '<form method="post" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:20px;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="add_deal">';
    echo '<label>Lead ID: <input type="number" min="1" name="deal_lead_id"></label> ';
    echo '<label>Title: <input type="text" name="deal_title" required></label> ';
    echo '<label>Amount: <input type="number" step="0.01" min="0" name="deal_amount" value="0"></label> ';
    echo '<label>Stage: <select name="deal_stage"><option>qualification</option><option>proposal</option><option>negotiation</option><option>won</option><option>lost</option></select></label> ';
    echo '<button type="submit" class="btn btn-primary">Add Deal</button>';
    echo '</form>';
    echo '<table class="table table-striped"><thead><tr><th>ID</th><th>Lead</th><th>Title</th><th>Amount</th><th>Stage</th><th>Expected Close</th></tr></thead><tbody>';
    foreach ($deals as $deal) {
        echo '<tr><td>' . (int) $deal->id . '</td><td>' . htmlspecialchars((string) ($deal->lead_name ?? '-')) . '</td><td>' . htmlspecialchars((string) $deal->title) . '</td><td>' . htmlspecialchars((string) $deal->amount) . '</td><td>' . htmlspecialchars((string) $deal->stage) . '</td><td>' . htmlspecialchars((string) ($deal->expected_close_at ?? '')) . '</td></tr>';
    }
    if (count($deals) === 0) {
        echo '<tr><td colspan="6">No deals yet.</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h4>Kanban Board (Drag & Drop)</h4>';
    echo '<form id="crmconnector-kanban-form" method="post" action="' . htmlspecialchars($moduleLink) . '" style="display:none;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="move_deal_stage">';
    echo '<input type="hidden" name="move_deal_id" id="crmconnector-move-deal-id" value="">';
    echo '<input type="hidden" name="move_deal_stage" id="crmconnector-move-deal-stage" value="">';
    echo '</form>';
    crmconnector_render_kanban_board($deals);

    echo '<h3>Follow-ups (Phase 3 MVP)</h3>';
    echo '<form method="post" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:20px;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="add_followup">';
    echo '<label>User ID: <input type="number" min="1" name="followup_userid"></label> ';
    echo '<label>Lead ID: <input type="number" min="1" name="followup_lead_id"></label> ';
    echo '<label>Title: <input type="text" name="followup_title" required></label> ';
    echo '<label>Channel: <select name="followup_channel"><option>email</option><option>in_app</option></select></label> ';
    echo '<label>Due: <input type="datetime-local" name="followup_due_at"></label> ';
    echo '<button type="submit" class="btn btn-primary">Add Follow-up</button>';
    echo '</form>';
    echo '<table class="table table-striped"><thead><tr><th>ID</th><th>User ID</th><th>Lead ID</th><th>Title</th><th>Channel</th><th>Status</th><th>Due</th></tr></thead><tbody>';
    foreach ($followups as $followup) {
        echo '<tr><td>' . (int) $followup->id . '</td><td>' . htmlspecialchars((string) ($followup->userid ?? '-')) . '</td><td>' . htmlspecialchars((string) ($followup->lead_id ?? '-')) . '</td><td>' . htmlspecialchars((string) $followup->title) . '</td><td>' . htmlspecialchars((string) $followup->channel) . '</td><td>' . htmlspecialchars((string) $followup->status) . '</td><td>' . htmlspecialchars((string) ($followup->due_at ?? '')) . '</td></tr>';
    }
    if (count($followups) === 0) {
        echo '<tr><td colspan="7">No follow-ups yet.</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h3>Campaigns (Phase 4 MVP)</h3>';
    echo '<form method="post" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:20px;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="add_campaign">';
    echo '<label>Name: <input type="text" name="campaign_name" required></label> ';
    echo '<label>Status: <select name="campaign_status"><option>active</option><option>paused</option><option>closed</option></select></label> ';
    echo '<label>Description: <input type="text" name="campaign_description" size="60"></label> ';
    echo '<button type="submit" class="btn btn-primary">Add Campaign</button>';
    echo '</form>';
    echo '<table class="table table-striped"><thead><tr><th>ID</th><th>Name</th><th>Status</th><th>Description</th></tr></thead><tbody>';
    foreach ($campaigns as $campaign) {
        echo '<tr><td>' . (int) $campaign->id . '</td><td>' . htmlspecialchars((string) $campaign->name) . '</td><td>' . htmlspecialchars((string) $campaign->status) . '</td><td>' . htmlspecialchars((string) ($campaign->description ?? '')) . '</td></tr>';
    }
    if (count($campaigns) === 0) {
        echo '<tr><td colspan="4">No campaigns yet.</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h4>Campaign Filter by Lead Status</h4>';
    echo '<form method="get" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:12px;">';
    echo '<input type="hidden" name="module" value="crmconnector">';
    echo '<label>Campaign: <select name="filter_campaign_id">';
    echo '<option value="0">-- Select campaign --</option>';
    foreach ($campaigns as $campaign) {
        $isSelected = ((int) $campaign->id === $selectedCampaignId) ? ' selected' : '';
        echo '<option value="' . (int) $campaign->id . '"' . $isSelected . '>' . htmlspecialchars((string) $campaign->name) . '</option>';
    }
    echo '</select></label> ';
    echo '<label>Lead Status: <select name="filter_lead_status">';
    $statusOptions = ['', 'new', 'active', 'won', 'lost'];
    foreach ($statusOptions as $statusOption) {
        $display = $statusOption === '' ? '-- Any --' : $statusOption;
        $isSelected = ($statusOption === $selectedLeadStatus) ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($statusOption) . '"' . $isSelected . '>' . htmlspecialchars($display) . '</option>';
    }
    echo '</select></label> ';
    echo '<button type="submit" class="btn btn-default">Apply Filter</button>';
    echo '</form>';

    echo '<table class="table table-striped"><thead><tr><th>Lead ID</th><th>Name</th><th>Email</th><th>Status</th></tr></thead><tbody>';
    foreach ($filteredCampaignLeads as $filteredLead) {
        echo '<tr><td>' . (int) ($filteredLead->lead_id ?? 0) . '</td><td>' . htmlspecialchars((string) ($filteredLead->lead_name ?? '')) . '</td><td>' . htmlspecialchars((string) ($filteredLead->lead_email ?? '')) . '</td><td>' . htmlspecialchars((string) ($filteredLead->lead_status ?? '')) . '</td></tr>';
    }
    if (count($filteredCampaignLeads) === 0) {
        echo '<tr><td colspan="4">No leads found for current filter.</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h4>Lead-Campaign Assignment</h4>';
    echo '<form method="post" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:12px;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="assign_lead_campaign">';
    echo '<label>Lead ID: <input type="number" min="1" name="assignment_lead_id" required></label> ';
    echo '<label>Campaign ID: <input type="number" min="1" name="assignment_campaign_id" required></label> ';
    echo '<button type="submit" class="btn btn-primary">Assign</button>';
    echo '</form>';
    echo '<table class="table table-striped"><thead><tr><th>ID</th><th>Lead</th><th>Campaign</th><th>Assigned At</th></tr></thead><tbody>';
    foreach ($leadCampaignAssignments as $assignment) {
        echo '<tr><td>' . (int) $assignment->id . '</td><td>' . htmlspecialchars((string) ($assignment->lead_name ?? '-')) . '</td><td>' . htmlspecialchars((string) ($assignment->campaign_name ?? '-')) . '</td><td>' . htmlspecialchars((string) ($assignment->created_at ?? '')) . '</td></tr>';
    }
    if (count($leadCampaignAssignments) === 0) {
        echo '<tr><td colspan="4">No assignments yet.</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h3>Automation Rules (Phase 5 MVP)</h3>';
    echo '<form method="post" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:20px;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="add_rule">';
    echo '<label>Name: <input type="text" name="rule_name" required></label> ';
    echo '<label>Trigger: <select name="rule_trigger"><option>client_created</option><option>invoice_created</option><option>service_suspended</option></select></label> ';
    echo '<label>Action: <select name="rule_action"><option>create_followup</option><option>add_note</option><option>send_notification</option></select></label> ';
    echo '<button type="submit" class="btn btn-primary">Add Rule</button>';
    echo '</form>';
    echo '<table class="table table-striped"><thead><tr><th>ID</th><th>Name</th><th>Trigger</th><th>Action</th><th>Enabled</th></tr></thead><tbody>';
    foreach ($rules as $rule) {
        echo '<tr><td>' . (int) $rule->id . '</td><td>' . htmlspecialchars((string) $rule->name) . '</td><td>' . htmlspecialchars((string) $rule->trigger_event) . '</td><td>' . htmlspecialchars((string) $rule->action_type) . '</td><td>' . htmlspecialchars((string) $rule->is_enabled) . '</td></tr>';
    }
    if (count($rules) === 0) {
        echo '<tr><td colspan="5">No automation rules yet.</td></tr>';
    }
    echo '</tbody></table>';

    $leadCount = Capsule::table(CRMCONNECTOR_LEADS_TABLE)->count();
    $dealCount = Capsule::table(CRMCONNECTOR_DEALS_TABLE)->count();
    $followupCount = Capsule::table(CRMCONNECTOR_FOLLOWUPS_TABLE)->count();
    $campaignCount = Capsule::table(CRMCONNECTOR_CAMPAIGNS_TABLE)->count();

    echo '<h3>CRM Analytics (Phase 6 MVP)</h3>';
    echo '<ul>';
    echo '<li>Total Leads: ' . (int) $leadCount . '</li>';
    echo '<li>Total Deals: ' . (int) $dealCount . '</li>';
    echo '<li>Total Follow-ups: ' . (int) $followupCount . '</li>';
    echo '<li>Total Campaigns: ' . (int) $campaignCount . '</li>';
    echo '</ul>';

    echo '<h3>Contact Types</h3>';
    echo '<form method="post" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:12px;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="add_contact_type">';
    echo '<label>Name: <input type="text" name="contact_type_name" required></label> ';
    echo '<button type="submit" class="btn btn-primary">Add Contact Type</button>';
    echo '</form>';
    echo '<table class="table table-striped"><thead><tr><th>ID</th><th>Name</th><th>Active</th></tr></thead><tbody>';
    foreach ($contactTypes as $contactType) {
        echo '<tr><td>' . (int) $contactType->id . '</td><td>' . htmlspecialchars((string) $contactType->name) . '</td><td>' . htmlspecialchars((string) $contactType->is_active) . '</td></tr>';
    }
    if (count($contactTypes) === 0) {
        echo '<tr><td colspan="3">No contact types yet.</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h3>Labels / Board Columns</h3>';
    echo '<form method="post" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:12px;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="add_label">';
    echo '<label>Name: <input type="text" name="label_name" required></label> ';
    echo '<label>Color: <input type="text" name="label_color" value="#007bff" required></label> ';
    echo '<button type="submit" class="btn btn-primary">Add Label</button>';
    echo '</form>';
    echo '<table class="table table-striped"><thead><tr><th>ID</th><th>Name</th><th>Color</th></tr></thead><tbody>';
    foreach ($labels as $label) {
        echo '<tr><td>' . (int) $label->id . '</td><td>' . htmlspecialchars((string) $label->name) . '</td><td>' . htmlspecialchars((string) $label->color) . '</td></tr>';
    }
    if (count($labels) === 0) {
        echo '<tr><td colspan="3">No labels yet.</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h4>Assign Lead to Label</h4>';
    echo '<form method="post" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:12px;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="assign_lead_label">';
    echo '<label>Lead ID: <input type="number" min="1" name="label_assignment_lead_id" required></label> ';
    echo '<label>Label ID: <input type="number" min="1" name="label_assignment_label_id" required></label> ';
    echo '<button type="submit" class="btn btn-primary">Assign Label</button>';
    echo '</form>';

    echo '<table class="table table-striped"><thead><tr><th>ID</th><th>Lead</th><th>Label</th><th>Color</th><th>Assigned At</th></tr></thead><tbody>';
    foreach ($leadLabelAssignments as $leadLabelAssignment) {
        echo '<tr><td>' . (int) $leadLabelAssignment->id . '</td><td>' . htmlspecialchars((string) ($leadLabelAssignment->lead_name ?? '-')) . '</td><td>' . htmlspecialchars((string) ($leadLabelAssignment->label_name ?? '-')) . '</td><td>' . htmlspecialchars((string) ($leadLabelAssignment->label_color ?? '')) . '</td><td>' . htmlspecialchars((string) ($leadLabelAssignment->created_at ?? '')) . '</td></tr>';
    }
    if (count($leadLabelAssignments) === 0) {
        echo '<tr><td colspan="5">No lead-label assignments yet.</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h4>Lead Board by Labels (Drag & Drop)</h4>';
    echo '<form id="crmconnector-label-board-form" method="post" action="' . htmlspecialchars($moduleLink) . '" style="display:none;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="move_lead_label">';
    echo '<input type="hidden" name="move_lead_id" id="crmconnector-move-lead-id" value="">';
    echo '<input type="hidden" name="move_label_id" id="crmconnector-move-label-id" value="">';
    echo '</form>';
    crmconnector_render_label_board($labels, $leadLabelAssignments);

    echo '<h3>Web Forms</h3>';
    echo '<p>Public endpoint: modules/addons/crmconnector/webform.php</p>';
    echo '<form method="post" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:12px;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="add_webform">';
    echo '<label>Name: <input type="text" name="webform_name" required></label> ';
    echo '<label>Default Status: <input type="text" name="webform_status" value="new"></label> ';
    echo '<label>Contact Type ID: <input type="number" min="1" name="webform_contact_type_id"></label> ';
    echo '<button type="submit" class="btn btn-primary">Add Webform</button>';
    echo '</form>';
    echo '<table class="table table-striped"><thead><tr><th>ID</th><th>Name</th><th>Status</th><th>Type ID</th><th>Active</th></tr></thead><tbody>';
    foreach ($webforms as $webform) {
        echo '<tr><td>' . (int) $webform->id . '</td><td>' . htmlspecialchars((string) $webform->name) . '</td><td>' . htmlspecialchars((string) $webform->default_status) . '</td><td>' . htmlspecialchars((string) ($webform->contact_type_id ?? '-')) . '</td><td>' . htmlspecialchars((string) $webform->is_active) . '</td></tr>';
    }
    if (count($webforms) === 0) {
        echo '<tr><td colspan="5">No webforms yet.</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h3>Permissions Matrix by Action</h3>';
    echo '<form method="post" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:12px;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="save_permission_rule">';
    echo '<label>Admin: <select name="permission_adminid">';
    foreach ($admins as $admin) {
        echo '<option value="' . (int) $admin->id . '">#' . (int) $admin->id . ' - ' . htmlspecialchars((string) $admin->username) . '</option>';
    }
    echo '</select></label> ';
    echo '<label>Action: <select name="permission_action_key">';
    foreach ($actionKeys as $actionKey) {
        echo '<option value="' . htmlspecialchars($actionKey) . '">' . htmlspecialchars($actionKey) . '</option>';
    }
    echo '</select></label> ';
    echo '<label>Allowed: <select name="permission_is_allowed"><option value="yes">yes</option><option value="no">no</option></select></label> ';
    echo '<button type="submit" class="btn btn-primary">Save Rule</button>';
    echo '</form>';

    echo '<table class="table table-bordered"><thead><tr><th>Admin</th>';
    foreach ($actionKeys as $actionKey) {
        echo '<th>' . htmlspecialchars($actionKey) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($admins as $admin) {
        echo '<tr>';
        echo '<td>#' . (int) $admin->id . ' - ' . htmlspecialchars((string) $admin->username) . '</td>';
        foreach ($actionKeys as $actionKey) {
            $key = (int) $admin->id . '|' . $actionKey;
            $value = $permissionRules[$key] ?? 'default-allow';
            echo '<td>' . htmlspecialchars($value) . '</td>';
        }
        echo '</tr>';
    }
    if (count($admins) === 0) {
        echo '<tr><td colspan="99">No admins found.</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h3>API Management</h3>';
    echo '<p><strong>Usage last hour:</strong> ' . (int) $apiUsage1h . ' | <strong>Usage last 24h:</strong> ' . (int) $apiUsage24h . '</p>';
    echo '<form method="post" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:12px;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="rotate_api_token">';
    echo '<label>Name: <input type="text" name="api_token_name" value="default" required></label> ';
    echo '<button type="submit" class="btn btn-primary">Rotate/New API Token</button>';
    echo '</form>';

    echo '<form method="post" action="' . htmlspecialchars($moduleLink) . '" style="margin-bottom:12px;">';
    echo generate_token('form');
    echo '<input type="hidden" name="crmconnector_action" value="cleanup_api_limits">';
    echo '<button type="submit" class="btn btn-default">Cleanup API Rate Limits</button>';
    echo '</form>';

    echo '<table class="table table-striped"><thead><tr><th>ID</th><th>Name</th><th>Last4</th><th>Active</th><th>Created</th><th>Expires</th><th>Last Used</th><th>Action</th></tr></thead><tbody>';
    foreach ($apiTokens as $apiToken) {
        echo '<tr>';
        echo '<td>' . (int) $apiToken->id . '</td>';
        echo '<td>' . htmlspecialchars((string) $apiToken->name) . '</td>';
        echo '<td>' . htmlspecialchars((string) $apiToken->last4) . '</td>';
        echo '<td>' . htmlspecialchars((string) $apiToken->is_active) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($apiToken->created_at ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($apiToken->expires_at ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($apiToken->last_used_at ?? '')) . '</td>';
        echo '<td>';
        echo '<form method="post" action="' . htmlspecialchars($moduleLink) . '" style="display:inline;">';
        echo generate_token('form');
        echo '<input type="hidden" name="crmconnector_action" value="deactivate_api_token">';
        echo '<input type="hidden" name="api_token_id" value="' . (int) $apiToken->id . '">';
        echo '<button type="submit" class="btn btn-xs btn-danger">Deactivate</button>';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }
    if (count($apiTokens) === 0) {
        echo '<tr><td colspan="8">No API tokens created yet.</td></tr>';
    }
    echo '</tbody></table>';
}

function crmconnector_handle_post_action(array $vars)
{
    if (!crmconnector_has_write_access($vars)) {
        return 'Permission denied. Your admin account has read-only access for this module.';
    }

    $action = (string) ($_POST['crmconnector_action'] ?? '');
    if (!crmconnector_has_action_access($action)) {
        return 'Permission denied for action: ' . $action;
    }

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

    if ($action === 'add_lead') {
        return crmconnector_add_lead();
    }

    if ($action === 'add_deal') {
        return crmconnector_add_deal();
    }

    if ($action === 'add_followup') {
        return crmconnector_add_followup();
    }

    if ($action === 'add_campaign') {
        return crmconnector_add_campaign();
    }

    if ($action === 'add_rule') {
        return crmconnector_add_rule();
    }

    if ($action === 'add_contact_type') {
        return crmconnector_add_contact_type();
    }

    if ($action === 'add_label') {
        return crmconnector_add_label();
    }

    if ($action === 'add_webform') {
        return crmconnector_add_webform();
    }

    if ($action === 'import_leads_csv') {
        return crmconnector_import_leads_csv();
    }

    if ($action === 'assign_lead_campaign') {
        return crmconnector_assign_lead_campaign();
    }

    if ($action === 'move_deal_stage') {
        return crmconnector_move_deal_stage();
    }

    if ($action === 'save_permission_rule') {
        return crmconnector_save_permission_rule();
    }

    if ($action === 'assign_lead_label') {
        return crmconnector_assign_lead_label();
    }

    if ($action === 'move_lead_label') {
        return crmconnector_move_lead_label();
    }

    if ($action === 'rotate_api_token') {
        return crmconnector_rotate_api_token();
    }

    if ($action === 'deactivate_api_token') {
        return crmconnector_deactivate_api_token();
    }

    if ($action === 'cleanup_api_limits') {
        return crmconnector_cleanup_api_rate_limits();
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

function crmconnector_add_lead()
{
    $name = trim((string) ($_POST['lead_name'] ?? ''));
    $email = trim((string) ($_POST['lead_email'] ?? ''));
    $status = trim((string) ($_POST['lead_status'] ?? 'new'));

    if ($name === '') {
        return 'Lead name is required.';
    }

    Capsule::table(CRMCONNECTOR_LEADS_TABLE)->insert([
        'name' => $name,
        'email' => $email !== '' ? $email : null,
        'status' => $status,
        'created_at' => Capsule::raw('NOW()'),
        'updated_at' => Capsule::raw('NOW()'),
    ]);

    crmconnector_log(null, 'add_lead', 'completed', 'Lead created: ' . $name);
    return 'Lead created successfully.';
}

function crmconnector_add_deal()
{
    $title = trim((string) ($_POST['deal_title'] ?? ''));
    $leadId = (int) ($_POST['deal_lead_id'] ?? 0);
    $amount = (float) ($_POST['deal_amount'] ?? 0);
    $stage = trim((string) ($_POST['deal_stage'] ?? 'qualification'));

    if ($title === '') {
        return 'Deal title is required.';
    }

    Capsule::table(CRMCONNECTOR_DEALS_TABLE)->insert([
        'lead_id' => $leadId > 0 ? $leadId : null,
        'title' => $title,
        'amount' => $amount,
        'stage' => $stage,
        'created_at' => Capsule::raw('NOW()'),
        'updated_at' => Capsule::raw('NOW()'),
    ]);

    crmconnector_log(null, 'add_deal', 'completed', 'Deal created: ' . $title);
    return 'Deal created successfully.';
}

function crmconnector_add_followup()
{
    $title = trim((string) ($_POST['followup_title'] ?? ''));
    $userid = (int) ($_POST['followup_userid'] ?? 0);
    $leadId = (int) ($_POST['followup_lead_id'] ?? 0);
    $channel = trim((string) ($_POST['followup_channel'] ?? 'email'));
    $dueAt = trim((string) ($_POST['followup_due_at'] ?? ''));

    if ($title === '') {
        return 'Follow-up title is required.';
    }

    Capsule::table(CRMCONNECTOR_FOLLOWUPS_TABLE)->insert([
        'userid' => $userid > 0 ? $userid : null,
        'lead_id' => $leadId > 0 ? $leadId : null,
        'title' => $title,
        'channel' => $channel,
        'status' => 'pending',
        'due_at' => $dueAt !== '' ? date('Y-m-d H:i:s', strtotime($dueAt)) : null,
        'created_at' => Capsule::raw('NOW()'),
        'updated_at' => Capsule::raw('NOW()'),
    ]);

    crmconnector_log($userid > 0 ? $userid : null, 'add_followup', 'completed', 'Follow-up created: ' . $title);
    return 'Follow-up created successfully.';
}

function crmconnector_add_campaign()
{
    $name = trim((string) ($_POST['campaign_name'] ?? ''));
    $status = trim((string) ($_POST['campaign_status'] ?? 'active'));
    $description = trim((string) ($_POST['campaign_description'] ?? ''));

    if ($name === '') {
        return 'Campaign name is required.';
    }

    Capsule::table(CRMCONNECTOR_CAMPAIGNS_TABLE)->insert([
        'name' => $name,
        'status' => $status,
        'description' => $description !== '' ? $description : null,
        'created_at' => Capsule::raw('NOW()'),
        'updated_at' => Capsule::raw('NOW()'),
    ]);

    crmconnector_log(null, 'add_campaign', 'completed', 'Campaign created: ' . $name);
    return 'Campaign created successfully.';
}

function crmconnector_add_rule()
{
    $name = trim((string) ($_POST['rule_name'] ?? ''));
    $trigger = trim((string) ($_POST['rule_trigger'] ?? 'client_created'));
    $action = trim((string) ($_POST['rule_action'] ?? 'create_followup'));

    if ($name === '') {
        return 'Rule name is required.';
    }

    Capsule::table(CRMCONNECTOR_AUTOMATION_TABLE)->insert([
        'name' => $name,
        'trigger_event' => $trigger,
        'action_type' => $action,
        'is_enabled' => 'yes',
        'created_at' => Capsule::raw('NOW()'),
    ]);

    crmconnector_log(null, 'add_rule', 'completed', 'Automation rule created: ' . $name);
    return 'Automation rule created successfully.';
}

function crmconnector_add_contact_type()
{
    $name = trim((string) ($_POST['contact_type_name'] ?? ''));
    if ($name === '') {
        return 'Contact type name is required.';
    }

    Capsule::table(CRMCONNECTOR_CONTACT_TYPES_TABLE)->insert([
        'name' => $name,
        'is_active' => 'yes',
        'created_at' => Capsule::raw('NOW()'),
    ]);

    crmconnector_log(null, 'add_contact_type', 'completed', 'Contact type created: ' . $name);
    return 'Contact type created successfully.';
}

function crmconnector_add_label()
{
    $name = trim((string) ($_POST['label_name'] ?? ''));
    $color = trim((string) ($_POST['label_color'] ?? '#007bff'));
    if ($name === '') {
        return 'Label name is required.';
    }

    Capsule::table(CRMCONNECTOR_LABELS_TABLE)->insert([
        'name' => $name,
        'color' => $color,
        'created_at' => Capsule::raw('NOW()'),
    ]);

    crmconnector_log(null, 'add_label', 'completed', 'Label created: ' . $name);
    return 'Label created successfully.';
}

function crmconnector_add_webform()
{
    $name = trim((string) ($_POST['webform_name'] ?? ''));
    $status = trim((string) ($_POST['webform_status'] ?? 'new'));
    $contactTypeId = (int) ($_POST['webform_contact_type_id'] ?? 0);
    if ($name === '') {
        return 'Webform name is required.';
    }

    Capsule::table(CRMCONNECTOR_WEBFORMS_TABLE)->insert([
        'name' => $name,
        'default_status' => $status,
        'contact_type_id' => $contactTypeId > 0 ? $contactTypeId : null,
        'is_active' => 'yes',
        'created_at' => Capsule::raw('NOW()'),
    ]);

    crmconnector_log(null, 'add_webform', 'completed', 'Webform created: ' . $name);
    return 'Webform created successfully.';
}

function crmconnector_export_leads_csv()
{
    $leads = Capsule::table(CRMCONNECTOR_LEADS_TABLE)
        ->orderBy('id', 'desc')
        ->limit(10000)
        ->get();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="crmconnector-leads.csv"');

    $output = fopen('php://output', 'w');
    if ($output === false) {
        exit;
    }

    fputcsv($output, ['id', 'name', 'email', 'status', 'source', 'created_at']);
    foreach ($leads as $lead) {
        fputcsv($output, [
            $lead->id,
            $lead->name,
            $lead->email,
            $lead->status,
            $lead->source,
            $lead->created_at,
        ]);
    }

    fclose($output);
    exit;
}

function crmconnector_import_leads_csv()
{
    if (!isset($_FILES['leads_csv_file']) || !is_uploaded_file($_FILES['leads_csv_file']['tmp_name'])) {
        return 'CSV file is required.';
    }

    $handle = fopen($_FILES['leads_csv_file']['tmp_name'], 'r');
    if ($handle === false) {
        return 'Unable to read uploaded CSV file.';
    }

    $rowIndex = 0;
    $imported = 0;
    while (($row = fgetcsv($handle)) !== false) {
        $rowIndex++;
        if ($rowIndex === 1) {
            continue;
        }

        $name = trim((string) ($row[1] ?? ''));
        $email = trim((string) ($row[2] ?? ''));
        $status = trim((string) ($row[3] ?? 'new'));
        $source = trim((string) ($row[4] ?? 'import'));

        if ($name === '') {
            continue;
        }

        Capsule::table(CRMCONNECTOR_LEADS_TABLE)->insert([
            'name' => $name,
            'email' => $email !== '' ? $email : null,
            'status' => $status !== '' ? $status : 'new',
            'source' => $source !== '' ? $source : 'import',
            'created_at' => Capsule::raw('NOW()'),
            'updated_at' => Capsule::raw('NOW()'),
        ]);

        $imported++;
    }

    fclose($handle);
    crmconnector_log(null, 'import_leads_csv', 'completed', 'Imported leads: ' . $imported);
    return 'Import completed. Added ' . $imported . ' leads.';
}

function crmconnector_process_due_followups()
{
    $dueFollowups = Capsule::table(CRMCONNECTOR_FOLLOWUPS_TABLE)
        ->where('status', 'pending')
        ->whereNotNull('due_at')
        ->where('due_at', '<=', Capsule::raw('NOW()'))
        ->get();

    $processed = 0;
    foreach ($dueFollowups as $followup) {
        Capsule::table(CRMCONNECTOR_FOLLOWUPS_TABLE)
            ->where('id', $followup->id)
            ->update([
                'status' => 'done',
                'updated_at' => Capsule::raw('NOW()'),
            ]);

        $processed++;
        crmconnector_log((int) ($followup->userid ?? 0), 'followup_due', 'completed', 'Follow-up marked done: #' . $followup->id);
    }

    return $processed;
}

function crmconnector_process_automation_rules()
{
    $enabledRules = Capsule::table(CRMCONNECTOR_AUTOMATION_TABLE)
        ->where('is_enabled', 'yes')
        ->get();

    $executed = 0;
    foreach ($enabledRules as $rule) {
        $executed++;
        crmconnector_log(null, 'automation_rule', 'completed', 'Rule checked: ' . $rule->name);
    }

    return $executed;
}

function crmconnector_assign_lead_campaign()
{
    $leadId = (int) ($_POST['assignment_lead_id'] ?? 0);
    $campaignId = (int) ($_POST['assignment_campaign_id'] ?? 0);

    if ($leadId <= 0 || $campaignId <= 0) {
        return 'Lead ID and Campaign ID are required.';
    }

    $leadExists = Capsule::table(CRMCONNECTOR_LEADS_TABLE)->where('id', $leadId)->exists();
    $campaignExists = Capsule::table(CRMCONNECTOR_CAMPAIGNS_TABLE)->where('id', $campaignId)->exists();
    if (!$leadExists || !$campaignExists) {
        return 'Invalid lead or campaign.';
    }

    Capsule::table(CRMCONNECTOR_LEAD_CAMPAIGNS_TABLE)->updateOrInsert(
        [
            'lead_id' => $leadId,
            'campaign_id' => $campaignId,
        ],
        [
            'created_at' => Capsule::raw('NOW()'),
        ]
    );

    crmconnector_log(null, 'assign_lead_campaign', 'completed', 'Lead #' . $leadId . ' assigned to campaign #' . $campaignId);
    return 'Lead assigned to campaign successfully.';
}

function crmconnector_move_deal_stage()
{
    $dealId = (int) ($_POST['move_deal_id'] ?? 0);
    $targetStage = trim((string) ($_POST['move_deal_stage'] ?? ''));
    $allowedStages = crmconnector_get_deal_stages();

    if ($dealId <= 0 || !in_array($targetStage, $allowedStages, true)) {
        return 'Invalid deal move request.';
    }

    $updated = Capsule::table(CRMCONNECTOR_DEALS_TABLE)
        ->where('id', $dealId)
        ->update([
            'stage' => $targetStage,
            'updated_at' => Capsule::raw('NOW()'),
        ]);

    if (!$updated) {
        return 'Deal not found or unchanged.';
    }

    crmconnector_log(null, 'move_deal_stage', 'completed', 'Deal #' . $dealId . ' moved to ' . $targetStage);
    return 'Deal stage updated successfully.';
}

function crmconnector_get_deal_stages()
{
    return ['qualification', 'proposal', 'negotiation', 'won', 'lost'];
}

function crmconnector_render_kanban_board($deals)
{
    $stages = crmconnector_get_deal_stages();
    $dealsByStage = [];
    foreach ($stages as $stage) {
        $dealsByStage[$stage] = [];
    }

    foreach ($deals as $deal) {
        $stage = (string) ($deal->stage ?? 'qualification');
        if (!isset($dealsByStage[$stage])) {
            $dealsByStage[$stage] = [];
        }
        $dealsByStage[$stage][] = $deal;
    }

    echo '<div style="display:flex; gap:12px; overflow-x:auto; margin-bottom:20px;">';
    foreach ($stages as $stage) {
        echo '<div class="panel panel-default crm-kanban-col" data-stage="' . htmlspecialchars($stage) . '" style="min-width:220px; width:220px;">';
        echo '<div class="panel-heading"><strong>' . htmlspecialchars(strtoupper($stage)) . '</strong></div>';
        echo '<div class="panel-body" style="min-height:150px;">';
        foreach ($dealsByStage[$stage] as $deal) {
            echo '<div class="well well-sm crm-kanban-card" draggable="true" data-deal-id="' . (int) $deal->id . '" style="cursor:move;">';
            echo '<div><strong>#' . (int) $deal->id . '</strong> ' . htmlspecialchars((string) $deal->title) . '</div>';
            echo '<div>Amount: ' . htmlspecialchars((string) $deal->amount) . '</div>';
            echo '<div>Lead: ' . htmlspecialchars((string) ($deal->lead_name ?? '-')) . '</div>';
            echo '</div>';
        }
        if (count($dealsByStage[$stage]) === 0) {
            echo '<div class="text-muted">Drop deal here</div>';
        }
        echo '</div></div>';
    }
    echo '</div>';

    echo '<script>';
    echo 'document.querySelectorAll(".crm-kanban-card").forEach(function(card){';
    echo 'card.addEventListener("dragstart",function(e){e.dataTransfer.setData("text/plain", card.getAttribute("data-deal-id"));});';
    echo '});';
    echo 'document.querySelectorAll(".crm-kanban-col .panel-body").forEach(function(col){';
    echo 'col.addEventListener("dragover",function(e){e.preventDefault();});';
    echo 'col.addEventListener("drop",function(e){';
    echo 'e.preventDefault();';
    echo 'var dealId=e.dataTransfer.getData("text/plain");';
    echo 'var stage=col.parentElement.getAttribute("data-stage");';
    echo 'document.getElementById("crmconnector-move-deal-id").value=dealId;';
    echo 'document.getElementById("crmconnector-move-deal-stage").value=stage;';
    echo 'document.getElementById("crmconnector-kanban-form").submit();';
    echo '});';
    echo '});';
    echo '</script>';
}

function crmconnector_save_permission_rule()
{
    $adminId = (int) ($_POST['permission_adminid'] ?? 0);
    $actionKey = trim((string) ($_POST['permission_action_key'] ?? ''));
    $isAllowed = trim((string) ($_POST['permission_is_allowed'] ?? 'yes'));
    $allowedKeys = crmconnector_get_action_keys();

    if ($adminId <= 0 || !in_array($actionKey, $allowedKeys, true)) {
        return 'Invalid permission rule.';
    }

    if ($isAllowed !== 'yes' && $isAllowed !== 'no') {
        return 'Invalid permission value.';
    }

    Capsule::table(CRMCONNECTOR_PERMISSIONS_TABLE)->updateOrInsert(
        [
            'adminid' => $adminId,
            'action_key' => $actionKey,
        ],
        [
            'is_allowed' => $isAllowed,
            'updated_at' => Capsule::raw('NOW()'),
        ]
    );

    crmconnector_log(null, 'save_permission_rule', 'completed', 'Permission rule updated: admin #' . $adminId . ', ' . $actionKey . '=' . $isAllowed);
    return 'Permission rule saved.';
}

function crmconnector_assign_lead_label()
{
    $leadId = (int) ($_POST['label_assignment_lead_id'] ?? 0);
    $labelId = (int) ($_POST['label_assignment_label_id'] ?? 0);

    if ($leadId <= 0 || $labelId <= 0) {
        return 'Lead ID and Label ID are required.';
    }

    $leadExists = Capsule::table(CRMCONNECTOR_LEADS_TABLE)->where('id', $leadId)->exists();
    $labelExists = Capsule::table(CRMCONNECTOR_LABELS_TABLE)->where('id', $labelId)->exists();
    if (!$leadExists || !$labelExists) {
        return 'Invalid lead or label.';
    }

    Capsule::table(CRMCONNECTOR_LEAD_LABELS_TABLE)->where('lead_id', $leadId)->delete();
    Capsule::table(CRMCONNECTOR_LEAD_LABELS_TABLE)->insert([
        'lead_id' => $leadId,
        'label_id' => $labelId,
        'created_at' => Capsule::raw('NOW()'),
    ]);

    crmconnector_log(null, 'assign_lead_label', 'completed', 'Lead #' . $leadId . ' assigned to label #' . $labelId);
    return 'Lead assigned to label successfully.';
}

function crmconnector_move_lead_label()
{
    $leadId = (int) ($_POST['move_lead_id'] ?? 0);
    $labelId = (int) ($_POST['move_label_id'] ?? 0);

    if ($leadId <= 0 || $labelId <= 0) {
        return 'Invalid board move request.';
    }

    $_POST['label_assignment_lead_id'] = $leadId;
    $_POST['label_assignment_label_id'] = $labelId;
    return crmconnector_assign_lead_label();
}

function crmconnector_render_label_board($labels, $leadLabelAssignments)
{
    $leadByLabel = [];
    foreach ($labels as $label) {
        $leadByLabel[(int) $label->id] = [];
    }

    foreach ($leadLabelAssignments as $assignment) {
        $labelId = (int) ($assignment->label_id ?? 0);
        if (!isset($leadByLabel[$labelId])) {
            $leadByLabel[$labelId] = [];
        }
        $leadByLabel[$labelId][] = $assignment;
    }

    echo '<div style="display:flex; gap:12px; overflow-x:auto; margin-bottom:20px;">';
    foreach ($labels as $label) {
        $labelId = (int) $label->id;
        echo '<div class="panel panel-default crm-label-col" data-label-id="' . $labelId . '" style="min-width:230px; width:230px;">';
        echo '<div class="panel-heading" style="border-left:6px solid ' . htmlspecialchars((string) $label->color) . ';"><strong>' . htmlspecialchars((string) $label->name) . '</strong></div>';
        echo '<div class="panel-body" style="min-height:150px;">';
        foreach ($leadByLabel[$labelId] as $assignment) {
            echo '<div class="well well-sm crm-label-card" draggable="true" data-lead-id="' . (int) $assignment->lead_id . '" style="cursor:move;">';
            echo '<div><strong>#' . (int) $assignment->lead_id . '</strong> ' . htmlspecialchars((string) ($assignment->lead_name ?? '-')) . '</div>';
            echo '</div>';
        }
        if (count($leadByLabel[$labelId]) === 0) {
            echo '<div class="text-muted">Drop lead here</div>';
        }
        echo '</div></div>';
    }
    echo '</div>';

    echo '<script>';
    echo 'document.querySelectorAll(".crm-label-card").forEach(function(card){';
    echo 'card.addEventListener("dragstart",function(e){e.dataTransfer.setData("text/plain", card.getAttribute("data-lead-id"));});';
    echo '});';
    echo 'document.querySelectorAll(".crm-label-col .panel-body").forEach(function(col){';
    echo 'col.addEventListener("dragover",function(e){e.preventDefault();});';
    echo 'col.addEventListener("drop",function(e){';
    echo 'e.preventDefault();';
    echo 'var leadId=e.dataTransfer.getData("text/plain");';
    echo 'var labelId=col.parentElement.getAttribute("data-label-id");';
    echo 'document.getElementById("crmconnector-move-lead-id").value=leadId;';
    echo 'document.getElementById("crmconnector-move-label-id").value=labelId;';
    echo 'document.getElementById("crmconnector-label-board-form").submit();';
    echo '});';
    echo '});';
    echo '</script>';
}

function crmconnector_rotate_api_token()
{
    $name = trim((string) ($_POST['api_token_name'] ?? 'default'));
    if ($name === '') {
        $name = 'default';
    }

    $tokenPlain = bin2hex(random_bytes(24));
    $tokenHash = hash('sha256', $tokenPlain);
    $last4 = substr($tokenPlain, -4);
    $ttlDays = (int) crmconnector_get_setting('api_token_ttl_days', '90');
    $expiresAt = $ttlDays > 0 ? date('Y-m-d H:i:s', strtotime('+' . $ttlDays . ' days')) : null;

    Capsule::table(CRMCONNECTOR_API_TOKENS_TABLE)->insert([
        'name' => $name,
        'token_hash' => $tokenHash,
        'last4' => $last4,
        'is_active' => 'yes',
        'created_at' => Capsule::raw('NOW()'),
        'expires_at' => $expiresAt,
        'last_used_at' => null,
    ]);

    crmconnector_log(null, 'rotate_api_token', 'completed', 'Created API token: ' . $name . ' (last4 ' . $last4 . ')');
    return 'New API token created. Copy now (shown once): ' . $tokenPlain;
}

function crmconnector_deactivate_api_token()
{
    $tokenId = (int) ($_POST['api_token_id'] ?? 0);
    if ($tokenId <= 0) {
        return 'Invalid API token ID.';
    }

    $updated = Capsule::table(CRMCONNECTOR_API_TOKENS_TABLE)
        ->where('id', $tokenId)
        ->update([
            'is_active' => 'no',
            'last_used_at' => Capsule::raw('NOW()'),
        ]);

    if (!$updated) {
        return 'API token not found or unchanged.';
    }

    crmconnector_log(null, 'deactivate_api_token', 'completed', 'Deactivated API token #' . $tokenId);
    return 'API token deactivated.';
}

function crmconnector_cleanup_api_rate_limits()
{
    $thresholdKey = date('YmdHi', strtotime('-1 day'));
    $deleted = Capsule::table(CRMCONNECTOR_API_RATE_LIMITS_TABLE)
        ->where('window_key', '<', $thresholdKey)
        ->delete();

    crmconnector_log(null, 'cleanup_api_limits', 'completed', 'Deleted old API rate-limit rows: ' . (int) $deleted);
    return 'Cleanup completed. Deleted ' . (int) $deleted . ' old API rate-limit rows.';
}

function crmconnector_cleanup_old_observability_data()
{
    $deletedWebhooks = Capsule::table(CRMCONNECTOR_WEBHOOK_DELIVERIES_TABLE)
        ->where('created_at', '<', date('Y-m-d H:i:s', strtotime('-30 days')))
        ->delete();

    $deletedAudit = Capsule::table(CRMCONNECTOR_AUDIT_TRAIL_TABLE)
        ->where('created_at', '<', date('Y-m-d H:i:s', strtotime('-90 days')))
        ->delete();

    return [
        'webhooks' => (int) $deletedWebhooks,
        'audit' => (int) $deletedAudit,
    ];
}

function crmconnector_is_webhook_event_enabled($eventName)
{
    $eventsRaw = trim((string) crmconnector_get_setting('webhook_events', ''));
    if ($eventsRaw === '') {
        return false;
    }

    $events = array_map('trim', explode(',', strtolower($eventsRaw)));
    return in_array(strtolower((string) $eventName), $events, true);
}

function crmconnector_dispatch_webhook($eventName, array $payload)
{
    $url = trim((string) crmconnector_get_setting('webhook_url', ''));
    if ($url === '' || !crmconnector_is_webhook_event_enabled($eventName)) {
        return;
    }

    $secret = (string) crmconnector_get_setting('webhook_secret', '');
    $bodyArray = [
        'event' => $eventName,
        'timestamp' => gmdate('c'),
        'payload' => $payload,
    ];
    $body = json_encode($bodyArray);
    if ($body === false) {
        return;
    }

    $signature = hash_hmac('sha256', $body, $secret);
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-CRM-Event: ' . $eventName,
            'X-CRM-Signature: sha256=' . $signature,
        ],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
    ]);

    $responseBody = curl_exec($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    Capsule::table(CRMCONNECTOR_WEBHOOK_DELIVERIES_TABLE)->insert([
        'event_name' => (string) $eventName,
        'status' => $error !== '' ? 'failed' : (($httpCode >= 200 && $httpCode < 300) ? 'sent' : 'non_2xx'),
        'http_code' => $httpCode > 0 ? $httpCode : null,
        'response_body' => $error !== '' ? $error : ((string) $responseBody),
        'created_at' => Capsule::raw('NOW()'),
    ]);
}

function crmconnector_audit_write($actorType, $actorId, $resource, $resourceId, $action, $before, $after)
{
    $beforeJson = $before !== null ? json_encode($before) : null;
    $afterJson = $after !== null ? json_encode($after) : null;

    Capsule::table(CRMCONNECTOR_AUDIT_TRAIL_TABLE)->insert([
        'actor_type' => (string) $actorType,
        'actor_id' => $actorId,
        'resource' => (string) $resource,
        'resource_id' => $resourceId,
        'action' => (string) $action,
        'before_snapshot' => $beforeJson,
        'after_snapshot' => $afterJson,
        'created_at' => Capsule::raw('NOW()'),
    ]);
}

function crmconnector_get_action_keys()
{
    return [
        'sync_single',
        'sync_all',
        'retry_failed_all',
        'retry_selected',
        'add_note',
        'add_lead',
        'add_deal',
        'move_deal_stage',
        'add_followup',
        'add_campaign',
        'assign_lead_campaign',
        'assign_lead_label',
        'move_lead_label',
        'add_rule',
        'add_contact_type',
        'add_label',
        'add_webform',
        'import_leads_csv',
        'save_permission_rule',
        'rotate_api_token',
        'deactivate_api_token',
        'cleanup_api_limits',
    ];
}

function crmconnector_get_permission_rules()
{
    $rows = Capsule::table(CRMCONNECTOR_PERMISSIONS_TABLE)->get();
    $rules = [];
    foreach ($rows as $row) {
        $rules[(int) $row->adminid . '|' . (string) $row->action_key] = (string) $row->is_allowed;
    }

    return $rules;
}

function crmconnector_has_action_access($action)
{
    $action = trim((string) $action);
    if ($action === '') {
        return false;
    }

    $adminId = isset($_SESSION['adminid']) ? (int) $_SESSION['adminid'] : 0;
    if ($adminId <= 0) {
        return false;
    }

    $row = Capsule::table(CRMCONNECTOR_PERMISSIONS_TABLE)
        ->where('adminid', $adminId)
        ->where('action_key', $action)
        ->first();

    if (!$row) {
        return true;
    }

    return ((string) $row->is_allowed) === 'yes';
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

function crmconnector_get_setting($settingName, $defaultValue = '')
{
    $row = Capsule::table('tbladdonmodules')
        ->where('module', 'crmconnector')
        ->where('setting', $settingName)
        ->first();

    if (!$row) {
        return $defaultValue;
    }

    return (string) $row->value;
}

function crmconnector_set_setting($settingName, $settingValue)
{
    Capsule::table('tbladdonmodules')->updateOrInsert(
        [
            'module' => 'crmconnector',
            'setting' => (string) $settingName,
        ],
        [
            'value' => (string) $settingValue,
        ]
    );
}

function crmconnector_has_write_access(array $vars)
{
    if (($vars['restrict_write_admins'] ?? '') !== 'on') {
        return true;
    }

    $adminId = isset($_SESSION['adminid']) ? (int) $_SESSION['adminid'] : 0;
    if ($adminId <= 0) {
        return false;
    }

    $rawList = (string) ($vars['write_admin_ids'] ?? '');
    $listParts = array_filter(array_map('trim', explode(',', $rawList)), function ($value) {
        return $value !== '';
    });

    if (count($listParts) === 0) {
        return false;
    }

    foreach ($listParts as $part) {
        if ((int) $part === $adminId) {
            return true;
        }
    }

    return false;
}
