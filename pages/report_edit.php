<?php

use App\Services\SessionService;
use App\Services\HttpService;

/**
 * Report Edit Page — Application SST DREETS BFC
 *
 * Edit form for own reports. Only the declarant can edit, and only
 * if the report state is 'nouveau' or 'en_cours'.
 * URL: index.php?page=report_edit&uuid={report_uuid}
 */
$uuid = $_GET['uuid'] ?? '';
$report = fetchReportOrRedirect($uuid);

// Access control: only the declarant can edit
$user = SessionService::getInstance()->getUserSession();
$userIdStr = $user['id'] ?? '0';
$userId = (int) $userIdStr;

requireReportOwnership($report, $userId, $uuid, 'modifier');
requireReportEditable($report, $uuid, 'modifié');

$pageTitle = 'Modifier le signalement — ' . $report->reference;

$type = $report->type;
$action = new HttpService()->url('report_edit', ['uuid' => $uuid]);

// Prepare variables for the shared form template
$isEdit = true;
$sites = [];  // Not used in edit mode (site dropdown is hidden)
$formErrors = getFormErrors();
$formData = getFormData();

require __DIR__ . '/../templates/report_form.php';
