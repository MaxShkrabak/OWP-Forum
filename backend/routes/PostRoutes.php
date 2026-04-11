<?php
use Forum\Controllers\PostController;

$postController = new PostController($makePdo);

// READ ENDPOINTS

$app->get('/api/posts',                    [$postController, 'getPosts']);
$app->get('/api/posts/search',             [$postController, 'searchPosts']);
$app->get('/api/posts/pinned',             [$postController, 'getPinnedPosts']);
$app->get('/api/get-post/{id}',            [$postController, 'getPost']);
$app->get('/api/categories/{id}/posts',    [$postController, 'getCategoryPosts']);
$app->get('/api/verify/categories',        [$postController, 'getVerifyCategories']);
$app->get('/api/tags',                     [$postController, 'getTags']);
$app->get('/api/tags/filter',              [$postController, 'getTagsFilter']);

// WRITE/MUTATION ENDPOINTS

$app->post('/api/create-post',             [$postController, 'createPost']);
$app->post('/api/posts/{id}/vote',         [$postController, 'voteOnPost']);
$app->post('/api/posts/{id}/pin',          [$postController, 'pinPost']);
$app->delete('/api/posts/{id}',            [$postController, 'delPost']);
$app->put('/api/posts/{id}',               [$postController, 'editPost']);