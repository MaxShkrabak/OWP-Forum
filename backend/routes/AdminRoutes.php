<?php

use Forum\Controllers\AdminController;

$controller = new AdminController($makePdo);

// Roles
$app->get('/api/admin/roles',                     [$controller, 'listRoles']);

// Users
$app->get('/api/admin/users',                     [$controller, 'getUsers']);
$app->get('/api/admin/users/{id}',                [$controller, 'getUserById']);
$app->patch('/api/admin/users/{id}/role',         [$controller, 'updateRole']);
$app->patch('/api/admin/users/{id}/ban',          [$controller, 'setBan']);

// Posts
$app->patch('/api/admin/posts/{id}/metadata',     [$controller, 'updatePostMetadata']);

// Categories
$app->get('/api/admin/categories',                [$controller, 'listCategories']);
$app->post('/api/admin/categories',               [$controller, 'createCategory']);
$app->patch('/api/admin/categories/{id}',         [$controller, 'updateCategory']);
$app->delete('/api/admin/categories/{id}',        [$controller, 'deleteCategory']);

// Tags
$app->get('/api/admin/tags',                      [$controller, 'listTags']);
$app->post('/api/admin/tags',                     [$controller, 'createTag']);
$app->patch('/api/admin/tags/{id}',               [$controller, 'updateTag']);
$app->delete('/api/admin/tags/{id}',              [$controller, 'deleteTag']);

// Report Tags
$app->get('/api/admin/report-tags',               [$controller, 'listReportTags']);
$app->post('/api/admin/report-tags',              [$controller, 'createReportTag']);
$app->patch('/api/admin/report-tags/{id}',        [$controller, 'updateReportTag']);
$app->delete('/api/admin/report-tags/{id}',       [$controller, 'deleteReportTag']);
