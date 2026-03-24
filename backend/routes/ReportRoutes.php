<?php
use Forum\Controllers\ReportController;

$reportController = new ReportController($makePdo);

$app->get('/api/reports',                [$reportController, 'getReports']);
$app->patch('/api/reports/{id}/resolve', [$reportController, 'resolveReport']);
$app->get('/api/reports/tags',           [$reportController, 'getReportTags']);
$app->post('/api/reports',              [$reportController, 'submitReport']);
