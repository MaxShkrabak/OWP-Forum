<?php

use Forum\Controllers\AdminController;

$controller = new AdminController($makePdo);

$app->get('/api/admin/ping',                  [$controller, 'ping']);
$app->get('/api/admin/me',                    [$controller, 'me']);
$app->get('/api/admin/users',                 [$controller, 'getUsers']);
$app->patch('/api/admin/users/{id}/role',       [$controller, 'updateRole']);
$app->patch('/api/admin/users/{id}/ban',        [$controller, 'setBan']);
$app->get('/api/admin/users/{id}',            [$controller, 'getUserById']);
$app->get('/api/admin/reports',               [$controller, 'getReports']);
$app->get('/api/admin/categories',            [$controller, 'listCategories']);
$app->post('/api/admin/categories',            [$controller, 'createCategory']);
$app->patch('/api/admin/categories/{id}',       [$controller, 'updateCategory']);
$app->delete('/api/admin/categories/{id}',       [$controller, 'deleteCategory']);
