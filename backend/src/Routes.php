<?php
use App\Middleware\RateLimitMiddleware;

return function($app, $makePdo) {
    require __DIR__ . '/../routes/PostRoutes.php';
    require __DIR__ . '/../routes/ReportRoutes.php';
    require __DIR__ . '/../routes/UserRoutes.php';
    require __DIR__ . '/../routes/UploadImage.php';
    require __DIR__ . '/../routes/UserProfile.php';
    require __DIR__ . '/../routes/CommentRoutes.php';
    require __DIR__ . '/../routes/AdminRoutes.php';
    require __DIR__ . '/../routes/AdminTagRoutes.php';



    require __DIR__ . '/../routes/TestRoutes.php'; // We can use this for testing API calls (DELETE LATER)
};