<?php

use Forum\Controllers\CommentController;

$commentController = new CommentController($makePdo);

$app->post("/api/posts/{postId}/comments", [$commentController, 'createComment']);
$app->get("/api/posts/{postId}/comments", [$commentController, 'getPostComments']);

$app->delete("/api/comments/{id}", [$commentController, 'deleteComment']);
$app->post("/api/comments/{id}/vote", [$commentController, 'vote']);

$app->get("/api/comments/{parentId}/replies", [$commentController, 'getReplies']);
