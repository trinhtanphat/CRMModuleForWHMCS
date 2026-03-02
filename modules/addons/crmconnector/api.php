<?php

if (!defined('WHMCS')) {
    require_once __DIR__ . '/../../../init.php';
}

require_once __DIR__ . '/crmconnector.php';

use Illuminate\Database\Capsule\Manager as Capsule;

crmconnector_api_handle_request();

function crmconnector_api_handle_request()
{
    header('Content-Type: application/json; charset=utf-8');

    $token = crmconnector_api_extract_token();
    if ($token === '') {
        crmconnector_api_response(401, ['success' => false, 'message' => 'Unauthorized']);
    }

    $tokenMeta = crmconnector_api_validate_token($token);
    if (!$tokenMeta) {
        crmconnector_api_response(401, ['success' => false, 'message' => 'Unauthorized']);
    }

    $rateLimitPerMin = (int) crmconnector_get_setting('api_rate_limit_per_min', '60');
    if ($rateLimitPerMin <= 0) {
        $rateLimitPerMin = 60;
    }

    if (!crmconnector_api_rate_limit_allow($token, $rateLimitPerMin)) {
        crmconnector_api_response(429, ['success' => false, 'message' => 'Rate limit exceeded']);
    }

    $resource = trim((string) ($_GET['resource'] ?? ''));
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $id = (int) ($_GET['id'] ?? 0);

    if (!in_array($resource, ['leads', 'deals', 'campaigns', 'notes', 'followups', 'labels'], true)) {
        crmconnector_api_response(400, ['success' => false, 'message' => 'Invalid resource']);
    }

    $payload = crmconnector_api_payload();

    if ($method === 'GET') {
        $data = crmconnector_api_get_resource($resource, $id);
        crmconnector_log(null, 'api_get_' . $resource, 'completed', 'API GET ' . $resource);
        crmconnector_api_response(200, ['success' => true, 'data' => $data]);
    }

    if ($method === 'POST') {
        $created = crmconnector_api_create_resource($resource, $payload, $tokenMeta);
        crmconnector_log(null, 'api_post_' . $resource, 'completed', 'API POST ' . $resource);
        crmconnector_api_response(201, ['success' => true, 'data' => $created]);
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        if ($id <= 0) {
            crmconnector_api_response(400, ['success' => false, 'message' => 'ID is required for update']);
        }

        $updated = crmconnector_api_update_resource($resource, $id, $payload, $tokenMeta);
        crmconnector_log(null, 'api_put_' . $resource, 'completed', 'API PUT ' . $resource . ' #' . $id);
        crmconnector_api_response(200, ['success' => true, 'data' => $updated]);
    }

    if ($method === 'DELETE') {
        if ($id <= 0) {
            crmconnector_api_response(400, ['success' => false, 'message' => 'ID is required for delete']);
        }

        $deleted = crmconnector_api_delete_resource($resource, $id, $tokenMeta);
        crmconnector_log(null, 'api_delete_' . $resource, 'completed', 'API DELETE ' . $resource . ' #' . $id);
        crmconnector_api_response(200, ['success' => true, 'deleted' => $deleted]);
    }

    crmconnector_api_response(405, ['success' => false, 'message' => 'Method not allowed']);
}

function crmconnector_api_table_for_resource($resource)
{
    if ($resource === 'leads') {
        return CRMCONNECTOR_LEADS_TABLE;
    }

    if ($resource === 'deals') {
        return CRMCONNECTOR_DEALS_TABLE;
    }

    if ($resource === 'notes') {
        return CRMCONNECTOR_NOTES_TABLE;
    }

    if ($resource === 'followups') {
        return CRMCONNECTOR_FOLLOWUPS_TABLE;
    }

    if ($resource === 'labels') {
        return CRMCONNECTOR_LABELS_TABLE;
    }

    return CRMCONNECTOR_CAMPAIGNS_TABLE;
}

function crmconnector_api_get_resource($resource, $id)
{
    $table = crmconnector_api_table_for_resource($resource);
    $query = Capsule::table($table);

    if ($id > 0) {
        $row = $query->where('id', $id)->first();
        if (!$row) {
            crmconnector_api_response(404, ['success' => false, 'message' => 'Resource not found']);
        }

        return $row;
    }

    $allowedSort = crmconnector_api_allowed_sort_columns($resource);
    $sortBy = trim((string) ($_GET['sort_by'] ?? 'id'));
    if (!in_array($sortBy, $allowedSort, true)) {
        $sortBy = 'id';
    }

    $sortDir = strtolower(trim((string) ($_GET['sort_dir'] ?? 'desc')));
    if ($sortDir !== 'asc' && $sortDir !== 'desc') {
        $sortDir = 'desc';
    }

    $query = crmconnector_api_apply_filters($query, $resource);
    $query->orderBy($sortBy, $sortDir);

    $defaultPerPage = (int) crmconnector_get_setting('api_default_per_page', '25');
    if ($defaultPerPage <= 0) {
        $defaultPerPage = 25;
    }

    $perPage = (int) ($_GET['per_page'] ?? $defaultPerPage);
    if ($perPage <= 0) {
        $perPage = $defaultPerPage;
    }
    if ($perPage > 200) {
        $perPage = 200;
    }

    $page = (int) ($_GET['page'] ?? 1);
    if ($page <= 0) {
        $page = 1;
    }

    $total = (int) (clone $query)->count();
    $items = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

    return [
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => (int) ceil($total / $perPage),
        ],
        'sort' => [
            'by' => $sortBy,
            'dir' => $sortDir,
        ],
    ];
}

function crmconnector_api_create_resource($resource, array $payload, $tokenMeta)
{
    $table = crmconnector_api_table_for_resource($resource);
    $record = crmconnector_api_normalize_payload($resource, $payload, false);
    Capsule::table($table)->insert($record);

    $id = (int) Capsule::table($table)->orderBy('id', 'desc')->value('id');
    $created = Capsule::table($table)->where('id', $id)->first();

    crmconnector_audit_write('api', (int) ($tokenMeta->id ?? 0), $resource, $id, 'create', null, $created);
    crmconnector_dispatch_webhook('api_created', [
        'resource' => $resource,
        'resource_id' => $id,
        'actor_token_id' => (int) ($tokenMeta->id ?? 0),
    ]);

    return $created;
}

function crmconnector_api_update_resource($resource, $id, array $payload, $tokenMeta)
{
    $table = crmconnector_api_table_for_resource($resource);
    $before = Capsule::table($table)->where('id', (int) $id)->first();
    if (!$before) {
        crmconnector_api_response(404, ['success' => false, 'message' => 'Resource not found']);
    }

    $record = crmconnector_api_normalize_payload($resource, $payload, true);
    if (count($record) === 0) {
        crmconnector_api_response(400, ['success' => false, 'message' => 'No valid fields to update']);
    }

    Capsule::table($table)->where('id', (int) $id)->update($record);
    $after = Capsule::table($table)->where('id', (int) $id)->first();

    crmconnector_audit_write('api', (int) ($tokenMeta->id ?? 0), $resource, (int) $id, 'update', $before, $after);
    crmconnector_dispatch_webhook('api_updated', [
        'resource' => $resource,
        'resource_id' => (int) $id,
        'actor_token_id' => (int) ($tokenMeta->id ?? 0),
    ]);

    return $after;
}

function crmconnector_api_delete_resource($resource, $id, $tokenMeta)
{
    $table = crmconnector_api_table_for_resource($resource);
    $before = Capsule::table($table)->where('id', (int) $id)->first();
    if (!$before) {
        crmconnector_api_response(404, ['success' => false, 'message' => 'Resource not found']);
    }

    $deleted = (bool) Capsule::table($table)->where('id', (int) $id)->delete();
    if ($deleted) {
        crmconnector_audit_write('api', (int) ($tokenMeta->id ?? 0), $resource, (int) $id, 'delete', $before, null);
        crmconnector_dispatch_webhook('api_deleted', [
            'resource' => $resource,
            'resource_id' => (int) $id,
            'actor_token_id' => (int) ($tokenMeta->id ?? 0),
        ]);
    }

    return $deleted;
}

function crmconnector_api_normalize_payload($resource, array $payload, $isUpdate)
{
    $now = Capsule::raw('NOW()');
    $record = [];

    if ($resource === 'leads') {
        if (!$isUpdate || array_key_exists('name', $payload)) {
            $name = trim((string) ($payload['name'] ?? ''));
            if ($name === '' && !$isUpdate) {
                crmconnector_api_response(422, ['success' => false, 'message' => 'Lead name is required']);
            }
            if ($name !== '') {
                $record['name'] = $name;
            }
        }

        if (array_key_exists('email', $payload)) {
            $email = trim((string) ($payload['email'] ?? ''));
            $record['email'] = $email !== '' ? $email : null;
        } elseif (!$isUpdate) {
            $record['email'] = null;
        }

        if (array_key_exists('status', $payload)) {
            $record['status'] = trim((string) $payload['status']) !== '' ? (string) $payload['status'] : 'new';
        } elseif (!$isUpdate) {
            $record['status'] = 'new';
        }

        if (array_key_exists('source', $payload)) {
            $record['source'] = trim((string) $payload['source']) !== '' ? (string) $payload['source'] : 'api';
        } elseif (!$isUpdate) {
            $record['source'] = 'api';
        }
    }

    if ($resource === 'deals') {
        if (!$isUpdate || array_key_exists('title', $payload)) {
            $title = trim((string) ($payload['title'] ?? ''));
            if ($title === '' && !$isUpdate) {
                crmconnector_api_response(422, ['success' => false, 'message' => 'Deal title is required']);
            }
            if ($title !== '') {
                $record['title'] = $title;
            }
        }

        if (array_key_exists('lead_id', $payload)) {
            $leadId = (int) $payload['lead_id'];
            $record['lead_id'] = $leadId > 0 ? $leadId : null;
        } elseif (!$isUpdate) {
            $record['lead_id'] = null;
        }

        if (array_key_exists('amount', $payload)) {
            $record['amount'] = (float) $payload['amount'];
        } elseif (!$isUpdate) {
            $record['amount'] = 0;
        }

        if (array_key_exists('stage', $payload)) {
            $record['stage'] = trim((string) $payload['stage']) !== '' ? (string) $payload['stage'] : 'qualification';
        } elseif (!$isUpdate) {
            $record['stage'] = 'qualification';
        }

        if (array_key_exists('expected_close_at', $payload)) {
            $expected = trim((string) $payload['expected_close_at']);
            $record['expected_close_at'] = $expected !== '' ? $expected : null;
        } elseif (!$isUpdate) {
            $record['expected_close_at'] = null;
        }
    }

    if ($resource === 'campaigns') {
        if (!$isUpdate || array_key_exists('name', $payload)) {
            $name = trim((string) ($payload['name'] ?? ''));
            if ($name === '' && !$isUpdate) {
                crmconnector_api_response(422, ['success' => false, 'message' => 'Campaign name is required']);
            }
            if ($name !== '') {
                $record['name'] = $name;
            }
        }

        if (array_key_exists('status', $payload)) {
            $record['status'] = trim((string) $payload['status']) !== '' ? (string) $payload['status'] : 'active';
        } elseif (!$isUpdate) {
            $record['status'] = 'active';
        }

        if (array_key_exists('description', $payload)) {
            $description = trim((string) $payload['description']);
            $record['description'] = $description !== '' ? $description : null;
        } elseif (!$isUpdate) {
            $record['description'] = null;
        }
    }

    if ($resource === 'notes') {
        if (!$isUpdate || array_key_exists('userid', $payload)) {
            $userId = (int) ($payload['userid'] ?? 0);
            if ($userId <= 0 && !$isUpdate) {
                crmconnector_api_response(422, ['success' => false, 'message' => 'Note userid is required']);
            }
            if ($userId > 0) {
                $record['userid'] = $userId;
            }
        }

        if (!$isUpdate || array_key_exists('note', $payload)) {
            $noteContent = trim((string) ($payload['note'] ?? ''));
            if ($noteContent === '' && !$isUpdate) {
                crmconnector_api_response(422, ['success' => false, 'message' => 'Note content is required']);
            }
            if ($noteContent !== '') {
                $record['note'] = $noteContent;
            }
        }

        if (array_key_exists('adminid', $payload)) {
            $adminId = (int) $payload['adminid'];
            $record['adminid'] = $adminId > 0 ? $adminId : null;
        } elseif (!$isUpdate) {
            $record['adminid'] = null;
        }
    }

    if ($resource === 'followups') {
        if (!$isUpdate || array_key_exists('title', $payload)) {
            $title = trim((string) ($payload['title'] ?? ''));
            if ($title === '' && !$isUpdate) {
                crmconnector_api_response(422, ['success' => false, 'message' => 'Followup title is required']);
            }
            if ($title !== '') {
                $record['title'] = $title;
            }
        }

        if (array_key_exists('userid', $payload)) {
            $userId = (int) $payload['userid'];
            $record['userid'] = $userId > 0 ? $userId : null;
        } elseif (!$isUpdate) {
            $record['userid'] = null;
        }

        if (array_key_exists('lead_id', $payload)) {
            $leadId = (int) $payload['lead_id'];
            $record['lead_id'] = $leadId > 0 ? $leadId : null;
        } elseif (!$isUpdate) {
            $record['lead_id'] = null;
        }

        if (array_key_exists('channel', $payload)) {
            $channel = trim((string) $payload['channel']);
            $record['channel'] = $channel !== '' ? $channel : 'email';
        } elseif (!$isUpdate) {
            $record['channel'] = 'email';
        }

        if (array_key_exists('status', $payload)) {
            $status = trim((string) $payload['status']);
            $record['status'] = $status !== '' ? $status : 'pending';
        } elseif (!$isUpdate) {
            $record['status'] = 'pending';
        }

        if (array_key_exists('due_at', $payload)) {
            $dueAt = trim((string) $payload['due_at']);
            $record['due_at'] = $dueAt !== '' ? $dueAt : null;
        } elseif (!$isUpdate) {
            $record['due_at'] = null;
        }
    }

    if ($resource === 'labels') {
        if (!$isUpdate || array_key_exists('name', $payload)) {
            $name = trim((string) ($payload['name'] ?? ''));
            if ($name === '' && !$isUpdate) {
                crmconnector_api_response(422, ['success' => false, 'message' => 'Label name is required']);
            }
            if ($name !== '') {
                $record['name'] = $name;
            }
        }

        if (array_key_exists('color', $payload)) {
            $color = trim((string) $payload['color']);
            $record['color'] = $color !== '' ? $color : '#007bff';
        } elseif (!$isUpdate) {
            $record['color'] = '#007bff';
        }
    }

    if ($isUpdate) {
        $record['updated_at'] = $now;
    } else {
        $record['created_at'] = $now;
        $record['updated_at'] = $now;
    }

    return $record;
}

function crmconnector_api_extract_token()
{
    $headerToken = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headerToken = (string) $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $headerToken = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if ($headerToken !== '' && stripos($headerToken, 'Bearer ') === 0) {
        return trim(substr($headerToken, 7));
    }

    if (isset($_SERVER['HTTP_X_CRM_TOKEN'])) {
        return trim((string) $_SERVER['HTTP_X_CRM_TOKEN']);
    }

    return trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
}

function crmconnector_api_validate_token($token)
{
    $token = trim((string) $token);
    if ($token === '') {
        return null;
    }

    $tokenHash = hash('sha256', $token);
    $now = date('Y-m-d H:i:s');
    $row = Capsule::table(CRMCONNECTOR_API_TOKENS_TABLE)
        ->where('token_hash', $tokenHash)
        ->where('is_active', 'yes')
        ->first();

    if ($row) {
        if (!empty($row->expires_at) && (string) $row->expires_at < $now) {
            return null;
        }

        Capsule::table(CRMCONNECTOR_API_TOKENS_TABLE)
            ->where('id', (int) $row->id)
            ->update(['last_used_at' => Capsule::raw('NOW()')]);

        return $row;
    }

    $configuredToken = trim((string) crmconnector_get_setting('api_token', ''));
    if ($configuredToken !== '' && hash_equals($configuredToken, $token)) {
        return (object) ['id' => 0, 'name' => 'legacy-setting-token'];
    }

    return null;
}

function crmconnector_api_payload()
{
    $payload = [];

    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (strpos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode((string) $raw, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }

    if (count($payload) === 0) {
        $payload = $_POST;
    }

    if (count($payload) === 0) {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            parse_str($raw, $parsed);
            if (is_array($parsed)) {
                $payload = $parsed;
            }
        }
    }

    return is_array($payload) ? $payload : [];
}

function crmconnector_api_allowed_sort_columns($resource)
{
    $common = ['id', 'created_at', 'updated_at'];

    if ($resource === 'leads') {
        return array_merge($common, ['name', 'email', 'status', 'source']);
    }

    if ($resource === 'deals') {
        return array_merge($common, ['title', 'amount', 'stage', 'lead_id', 'expected_close_at']);
    }

    if ($resource === 'campaigns') {
        return array_merge($common, ['name', 'status', 'starts_at', 'ends_at']);
    }

    if ($resource === 'notes') {
        return ['id', 'userid', 'adminid', 'created_at', 'updated_at'];
    }

    if ($resource === 'followups') {
        return array_merge($common, ['title', 'status', 'channel', 'due_at', 'userid', 'lead_id']);
    }

    return ['id', 'name', 'color', 'created_at'];
}

function crmconnector_api_apply_filters($query, $resource)
{
    $q = trim((string) ($_GET['q'] ?? ''));

    if ($resource === 'leads') {
        if (isset($_GET['status']) && trim((string) $_GET['status']) !== '') {
            $query->where('status', trim((string) $_GET['status']));
        }
        if (isset($_GET['source']) && trim((string) $_GET['source']) !== '') {
            $query->where('source', trim((string) $_GET['source']));
        }
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', '%' . $q . '%')->orWhere('email', 'like', '%' . $q . '%');
            });
        }
    }

    if ($resource === 'deals') {
        if (isset($_GET['stage']) && trim((string) $_GET['stage']) !== '') {
            $query->where('stage', trim((string) $_GET['stage']));
        }
        if (isset($_GET['lead_id']) && (int) $_GET['lead_id'] > 0) {
            $query->where('lead_id', (int) $_GET['lead_id']);
        }
        if ($q !== '') {
            $query->where('title', 'like', '%' . $q . '%');
        }
    }

    if ($resource === 'campaigns') {
        if (isset($_GET['status']) && trim((string) $_GET['status']) !== '') {
            $query->where('status', trim((string) $_GET['status']));
        }
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', '%' . $q . '%')->orWhere('description', 'like', '%' . $q . '%');
            });
        }
    }

    if ($resource === 'notes') {
        if (isset($_GET['userid']) && (int) $_GET['userid'] > 0) {
            $query->where('userid', (int) $_GET['userid']);
        }
        if ($q !== '') {
            $query->where('note', 'like', '%' . $q . '%');
        }
    }

    if ($resource === 'followups') {
        if (isset($_GET['status']) && trim((string) $_GET['status']) !== '') {
            $query->where('status', trim((string) $_GET['status']));
        }
        if (isset($_GET['userid']) && (int) $_GET['userid'] > 0) {
            $query->where('userid', (int) $_GET['userid']);
        }
        if ($q !== '') {
            $query->where('title', 'like', '%' . $q . '%');
        }
    }

    if ($resource === 'labels' && $q !== '') {
        $query->where('name', 'like', '%' . $q . '%');
    }

    return $query;
}

function crmconnector_api_rate_limit_allow($token, $limitPerMin)
{
    $tokenHash = hash('sha256', (string) $token);
    $ipAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $windowKey = date('YmdHi');

    $row = Capsule::table(CRMCONNECTOR_API_RATE_LIMITS_TABLE)
        ->where('token_hash', $tokenHash)
        ->where('ip_address', $ipAddress)
        ->where('window_key', $windowKey)
        ->first();

    if (!$row) {
        Capsule::table(CRMCONNECTOR_API_RATE_LIMITS_TABLE)->insert([
            'token_hash' => $tokenHash,
            'ip_address' => $ipAddress,
            'window_key' => $windowKey,
            'request_count' => 1,
            'updated_at' => Capsule::raw('NOW()'),
        ]);
        return true;
    }

    $currentCount = (int) ($row->request_count ?? 0);
    if ($currentCount >= $limitPerMin) {
        return false;
    }

    Capsule::table(CRMCONNECTOR_API_RATE_LIMITS_TABLE)
        ->where('id', (int) $row->id)
        ->update([
            'request_count' => $currentCount + 1,
            'updated_at' => Capsule::raw('NOW()'),
        ]);

    return true;
}

function crmconnector_api_response($statusCode, array $payload)
{
    http_response_code((int) $statusCode);
    echo json_encode($payload);
    exit;
}
