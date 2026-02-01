<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/api/posts/{id}/vote', function (Request $req, Response $res, array $args) use ($makePdo) {
  try {
    $userId = (int)$req->getAttribute("user_id");
    if (!$userId) {
      $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Not Authenticated']));
      return $res->withStatus(401)->withHeader("Content-Type", "application/json");
    }

    $pdo = $makePdo();
    $postId = (int)$args['id'];

    // myVote: -1, 0, 1
    $stmt = $pdo->prepare("
      SELECT VoteValue
      FROM dbo.PostLikes
      WHERE PostID = :postId AND User_ID = :userId
    ");
    $stmt->execute([':postId' => $postId, ':userId' => $userId]);
    $myVote = $stmt->fetchColumn();
    $myVote = ($myVote === false) ? 0 : (int)$myVote;

    // score can be negative
    $stmt = $pdo->prepare("
      SELECT COALESCE(SUM(VoteValue), 0) AS Score
      FROM dbo.PostLikes
      WHERE PostID = :postId
    ");
    $stmt->execute([':postId' => $postId]);
    $score = (int)$stmt->fetchColumn();

    $res->getBody()->write(json_encode([
      'ok' => true,
      'myVote' => $myVote,
      'score' => $score
    ]));
    return $res->withHeader("Content-Type", "application/json");

  } catch (Throwable $e) {
    $res->getBody()->write(json_encode([
      'ok' => false,
      'error' => 'Server error: ' . $e->getMessage()
    ]));
    return $res->withStatus(500)->withHeader("Content-Type", "application/json");
  }
});

$app->post('/api/posts/{id}/vote', function (Request $req, Response $res, array $args) use ($makePdo) {
  try {
    $userId = (int)$req->getAttribute("user_id");
    if (!$userId) {
      $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Not Authenticated']));
      return $res->withStatus(401)->withHeader("Content-Type", "application/json");
    }

    $pdo = $makePdo();
    $postId = (int)$args['id'];

    $data = $req->getParsedBody() ?? [];
    $action = $data['action'] ?? '';

    if (!in_array($action, ['up', 'down', 'clear'], true)) {
      $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Invalid action']));
      return $res->withStatus(400)->withHeader("Content-Type", "application/json");
    }

    // current vote
    $stmt = $pdo->prepare("
      SELECT VoteValue
      FROM dbo.PostLikes
      WHERE PostID = :postId AND User_ID = :userId
    ");
    $stmt->execute([':postId' => $postId, ':userId' => $userId]);
    $current = $stmt->fetchColumn();
    $current = ($current === false) ? 0 : (int)$current;

    if ($action === 'clear') {
      $del = $pdo->prepare("
        DELETE FROM dbo.PostLikes
        WHERE PostID = :postId AND User_ID = :userId
      ");
      $del->execute([':postId' => $postId, ':userId' => $userId]);
      $newVote = 0;
    } else {
      // requested is 1 or -1
      $requested = ($action === 'up') ? 1 : -1;

      // If same vote clicked again, clear it
      if ($current === $requested) {
        $del = $pdo->prepare("
          DELETE FROM dbo.PostLikes
          WHERE PostID = :postId AND User_ID = :userId
        ");
        $del->execute([':postId' => $postId, ':userId' => $userId]);
        $newVote = 0;
      } else {
        // Try update first
        $upd = $pdo->prepare("
          UPDATE dbo.PostLikes
          SET VoteValue = :voteValue, VotedAt = SYSUTCDATETIME()
          WHERE PostID = :postId AND User_ID = :userId
        ");
        $upd->execute([
          ':voteValue' => $requested,
          ':postId' => $postId,
          ':userId' => $userId
        ]);

        // If no row existed, insert
        if ($upd->rowCount() === 0) {
          $ins = $pdo->prepare("
            INSERT INTO dbo.PostLikes (PostID, User_ID, VoteValue)
            VALUES (:postId, :userId, :voteValue)
          ");
          $ins->execute([
            ':postId' => $postId,
            ':userId' => $userId,
            ':voteValue' => $requested
          ]);
        }

        $newVote = $requested;
      }

    }

    // updated score
    $stmt = $pdo->prepare("
      SELECT COALESCE(SUM(VoteValue), 0) AS Score
      FROM dbo.PostLikes
      WHERE PostID = :postId
    ");
    $stmt->execute([':postId' => $postId]);
    $score = (int)$stmt->fetchColumn();

    $res->getBody()->write(json_encode([
      'ok' => true,
      'myVote' => $newVote,
      'score' => $score
    ]));
    return $res->withHeader("Content-Type", "application/json");

  } catch (Throwable $e) {
    $res->getBody()->write(json_encode([
      'ok' => false,
      'error' => 'Server error: ' . $e->getMessage()
    ]));
    return $res->withStatus(500)->withHeader("Content-Type", "application/json");
  }
});

$app->post('/api/posts/votes/bulk', function (Request $req, Response $res) use ($makePdo) {
  try {
    $userId = (int)$req->getAttribute("user_id");
    if (!$userId) {
      $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Not Authenticated']));
      return $res->withStatus(401)->withHeader("Content-Type", "application/json");
    }

    $raw = $req->getBody()->getContents();
    $data = json_decode($raw, true) ?? [];
    $postIds = $data['postIds'] ?? [];

    if (!is_array($postIds) || count($postIds) === 0) {
      $res->getBody()->write(json_encode(['ok' => true, 'votes' => new stdClass()]));
      return $res->withHeader("Content-Type", "application/json");
    }

    // sanitize + cap
    $postIds = array_values(array_unique(array_map('intval', $postIds)));
    $postIds = array_slice($postIds, 0, 200);

    $pdo = $makePdo();
    $placeholders = implode(',', array_fill(0, count($postIds), '?'));

    // total score per post
    $stmt = $pdo->prepare("
      SELECT PostID, COALESCE(SUM(VoteValue), 0) AS Score
      FROM dbo.PostLikes
      WHERE PostID IN (?,?,?)
      GROUP BY PostID

    ");
    $stmt->execute($postIds);

    $scores = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $scores[(int)$row['PostID']] = (int)$row['Score'];
    }

    // this user's vote per post
    $stmt = $pdo->prepare("
      SELECT PostID, VoteValue
      FROM dbo.PostLikes
      WHERE PostID IN (?,?,?)
        AND User_ID = ?

    ");
    $stmt->execute(array_merge($postIds, [$userId]));

    $myVotes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $myVotes[(int)$row['PostID']] = (int)$row['VoteValue'];
    }

    // build map keyed by postId (easy merge in Vue)
    $votes = [];
    foreach ($postIds as $pid) {
      $votes[$pid] = [
        'score' => $scores[$pid] ?? 0,
        'myVote' => $myVotes[$pid] ?? 0
      ];
    }

    $res->getBody()->write(json_encode(['ok' => true, 'votes' => $votes]));
    return $res->withHeader("Content-Type", "application/json");

  } catch (Throwable $e) {
    $res->getBody()->write(json_encode([
      'ok' => false,
      'error' => 'Server error: ' . $e->getMessage()
    ]));
    return $res->withStatus(500)->withHeader("Content-Type", "application/json");
  }
});

