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
    $configuredToken = trim((string) crmconnector_get_setting('api_token', ''));
    if ($configuredToken === '') {
        crmconnector_api_response(503, ['success' => false, 'message' => 'API token is not configured']);
    }

    if ($token === '' || !hash_equals($configuredToken, $token)) {
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

    if (!in_array($resource, ['leads', 'deals', 'campaigns'], true)) {
        crmconnector_api_response(400, ['success' => false, 'message' => 'Invalid resource']);
    }

    $payload = crmconnector_api_payload();

    if ($method === 'GET') {
        $data = crmconnector_api_get_resource($resource, $id);
        crmconnector_log(null, 'api_get_' . $resource, 'completed', 'API GET ' . $resource);
        crmconnector_api_response(200, ['success' => true, 'data' => $data]);
    }

    if ($method === 'POST') {
        $created = crmconnector_api_create_resource($resource, $payload);
        crmconnector_log(null, 'api_post_' . $resource, 'completed', 'API POST ' . $resource);
        crmconnector_api_response(201, ['success' => true, 'data' => $created]);
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        if ($id <= 0) {
            crmconnector_api_response(400, ['success' => false, 'message' => 'ID is required for update']);
        }

        $updated = crmconnector_api_update_resource($resource, $id, $payload);
        crmconnector_log(null, 'api_put_' . $resource, 'completed', 'API PUT ' . $resource . ' #' . $id);
        crmconnector_api_response(200, ['success' => true, 'data' => $updated]);
    }

    if ($method === 'DELETE') {
        if ($id <= 0) {
            crmconnector_api_response(400, ['success' => false, 'message' => 'ID is required for delete']);
        }

        $deleted = crmconnector_api_delete_resource($resource, $id);
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

    return CRMCONNECTOR_CAMPAIGNS_TABLE;
}

function crmconnector_api_get_resource($resource, $id)
{
    $table = crmconnector_api_table_for_resource($resource);
    $query = Capsule::table($table)->orderBy('id', 'desc');

    if ($id > 0) {
        $row = $query->where('id', $id)->first();
        if (!$row) {
            crmconnector_api_response(404, ['success' => false, 'message' => 'Resource not found']);
        }

        return $row;
    }

    return $query->limit(200)->get();
}

function crmconnector_api_create_resource($resource, array $payload)
{
    $table = crmconnector_api_table_for_resource($resource);
    $record = crmconnector_api_normalize_payload($resource, $payload, false);
    Capsule::table($table)->insert($record);

    $id = (int) Capsule::table($table)->orderBy('id', 'desc')->value('id');
    return Capsule::table($table)->where('id', $id)->first();
}

function crmconnector_api_update_resource($resource, $id, array $payload)
{
    $table = crmconnector_api_table_for_resource($resource);
    $exists = Capsule::table($table)->where('id', (int) $id)->exists();
    if (!$exists) {
        crmconnector_api_response(404, ['success' => false, 'message' => 'Resource not found']);
    }

    $record = crmconnector_api_normalize_payload($resource, $payload, true);
    if (count($record) === 0) {
        crmconnector_api_response(400, ['success' => false, 'message' => 'No valid fields to update']);
    }

    Capsule::table($table)->where('id', (int) $id)->update($record);
    return Capsule::table($table)->where('id', (int) $id)->first();
}

function crmconnector_api_delete_resource($resource, $id)
{
    $table = crmconnector_api_table_for_resource($resource);
    return (bool) Capsule::table($table)->where('id', (int) $id)->delete();
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
