<?php

if (!defined('WHMCS')) {
    require_once __DIR__ . '/../../../init.php';
}

require_once __DIR__ . '/crmconnector.php';

use Illuminate\Database\Capsule\Manager as Capsule;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$token = trim((string) ($_POST['token'] ?? ''));
$formId = (int) ($_POST['form_id'] ?? 0);
$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$source = trim((string) ($_POST['source'] ?? 'webform'));

$configToken = crmconnector_get_setting('webform_token', '');
if ($configToken === '' || !hash_equals($configToken, $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

if ($name === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Name is required']);
    exit;
}

$form = null;
if ($formId > 0) {
    $form = Capsule::table(CRMCONNECTOR_WEBFORMS_TABLE)
        ->where('id', $formId)
        ->where('is_active', 'yes')
        ->first();
}

$status = $form->default_status ?? 'new';

Capsule::table(CRMCONNECTOR_LEADS_TABLE)->insert([
    'name' => $name,
    'email' => $email !== '' ? $email : null,
    'status' => $status,
    'source' => $source,
    'created_at' => Capsule::raw('NOW()'),
    'updated_at' => Capsule::raw('NOW()'),
]);

crmconnector_log(null, 'webform_submit', 'completed', 'Lead created from webform: ' . $name);

echo json_encode(['success' => true, 'message' => 'Lead created']);
