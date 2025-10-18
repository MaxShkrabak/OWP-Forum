<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->post('/api/register-new-user', function (Request $req, Response $res) use ($makePdo) {
    try {
        // Grab the data sent from front end
        $data = $req->getParsedBody();
        $first = trim((string)($data['first'] ?? ''));
        $last = trim((string)($data['last'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));

        $pdo = $makePdo();
        
        // Searches the Users table for the provided email from frontend
        $check = $pdo->prepare("SELECT 1 FROM dbo.Users WHERE Email = :email");  
        $check->execute([':email' => $email]);

        // If email already exists return error message (doesn't store user)
        if ($check->fetchColumn()) {
            $res->getBody()->write(json_encode(['ok' => false, 'message' => 'The provided email already exists. Try logging-in instead.']));
            return $res->withStatus(400)->withHeader('Content-Type', 'application/json');
        } else {
            // Store the users details in the database
            $stmt = $pdo->prepare("
                INSERT INTO dbo.USERS (Email, FirstName, LastName, Created)
                VALUES (:email, :first, :last, GETDATE()) 
            ");
            // Executes query above storing the data
            $stmt->execute([
                ":email" => $email,
                ":first" => $first,
                ":last" => $last,
            ]);

            // Return a success message
            $res->getBody()->write(json_encode(['ok' => true, 'message' => 'User registered successfully']));
            return $res->withHeader('Content-Type', 'application/json');
        }
    } catch (Throwable $e) {
        $res->getBody()->write(json_encode(['ok' => false, 'error' => $e->getMessage()]));
        return $res->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});
