<?php

use Forum\Controllers\AdminTagController;

$controller = new AdminTagController($makePdo);

$app->get('/api/admin/tags',              [$controller, 'listTags']);
$app->post('/api/admin/tags',              [$controller, 'createTag']);
$app->patch('/api/admin/tags/{id}',         [$controller, 'updateTag']);
$app->delete('/api/admin/tags/{id}',         [$controller, 'deleteTag']);

$app->get('/api/admin/report-tags',       [$controller, 'listReportTags']);
$app->post('/api/admin/report-tags',       [$controller, 'createReportTag']);
$app->patch('/api/admin/report-tags/{id}',  [$controller, 'updateReportTag']);
$app->delete('/api/admin/report-tags/{id}',  [$controller, 'deleteReportTag']);
