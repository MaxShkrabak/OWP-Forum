<?php
use Forum\Controllers\AuthController;

$authController = new AuthController($makePdo);

$app->get('/api/me', [$authController, 'me']);
$app->post('/api/login', [$authController, 'login']);
$app->post('/api/register-new-user', [$authController, 'register']);
$app->post('/api/verify-email', [$authController, 'verifyEmail']);
$app->post('/api/request-otp', [$authController, 'requestOtp']);
$app->post('/api/logout', [$authController, 'logout']);
