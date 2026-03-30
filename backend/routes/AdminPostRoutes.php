<?php

use Forum\Controllers\AdminPostController;

$controller = new AdminPostController($makePdo);

$app->patch('/api/admin/posts/{id}/soft-delete', [$controller, 'softDelete']);
$app->patch('/api/admin/posts/{id}/metadata',    [$controller, 'updateMetadata']);
