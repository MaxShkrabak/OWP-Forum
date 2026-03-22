<?php
use Forum\Controllers\UserController;

$userController = new UserController($makePdo);

$app->post('/api/user/avatar', [$userController, 'updateAvatar']);
$app->get('/api/user/notifications', [$userController, 'getNotifications']);
$app->post('/api/user/notifications/read', [$userController, 'markNotificationsRead']);
$app->get('/api/user/notification-settings', [$userController, 'getNotificationSettings']);
$app->post('/api/user/notification-settings', [$userController, 'updateNotificationSettings']);
$app->post('/api/accept-terms', [$userController, 'acceptTerms']);
$app->get('/api/profile/{uid}', [$userController, 'getProfile']);
$app->get('/api/profile/{uid}/stats', [$userController, 'getProfileStats']);
$app->get('/api/profile/{uid}/posts', [$userController, 'getProfilePosts']);
$app->get('/api/profile/{uid}/liked-posts', [$userController, 'getProfileLikedPosts']);
