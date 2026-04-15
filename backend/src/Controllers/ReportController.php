<?php

namespace Forum\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;
use PDO;
use function Forum\Helpers\json;

class ReportController extends BaseController
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function mapReportRows(array $rows): array
    {
        $reports = [];
        foreach ($rows as $row) {
            $reports[] = [
                'reportId'      => (int)$row['ReportID'],
                'postId'        => (int)($row['PostID'] ?? 0) ?: null,
                'postTitle'     => $row['PostTitle'] ?? null,
                'postAuthor'    => $row['PostAuthor'] ?? null,
                'postAuthorId'  => (int)($row['PostAuthorId'] ?? 0) ?: null,
                'commentId'     => (int)($row['CommentID'] ?? 0) ?: null,
                'parentCommentId' => (int)($row['CommentParentId'] ?? 0) ?: null,
                'commentText'   => $row['CommentText'] ?? null,
                'commentAuthor' => $row['CommentAuthor'] ?? null,
                'source'        => (int)($row['CommentID'] ?? 0) > 0 ? 'Comment' : 'Post',
                'reason'        => $row['Reason'] ?? 'Other',
                'createdAt'     => $row['CreatedAt'],
                'reporter'      => [
                    'id'       => (int)$row['ReporterId'],
                    'fullName' => $row['ReporterName'] ?? null,
                ],
            ];
        }

        return $reports;
    }

    public function getReports(Request $req, Response $res): Response
    {
        try {
            [$err, $pdo] = $this->requireRole(3, $req, $res);
            if ($err !== null) return $err;

            $queryParams = $req->getQueryParams();
            $wantPaginate = array_key_exists('page', $queryParams) || array_key_exists('perPage', $queryParams);

            $sortRaw = strtolower(trim((string)($queryParams['sort'] ?? 'newest')));
            $orderSql = ($sortRaw === 'oldest') ? 'r.CreatedAt ASC' : 'r.CreatedAt DESC';

            $selectCols = "
                SELECT
                    r.ReportID,
                    COALESCE(r.PostID, c.PostID) AS PostID,
                    p.Title AS PostTitle,
                    p.AuthorID AS PostAuthorId,
                    CONCAT(up.FirstName, ' ', up.LastName) AS PostAuthor,
                    r.CommentID,
                    c.ParentCommentID AS CommentParentId,
                    c.Content AS CommentText,
                    c.UserID AS CommentAuthorId,
                    NULLIF(CONCAT(uc.FirstName, ' ', uc.LastName),'') AS CommentAuthor,
                    r.CreatedAt,
                    rt.TagName AS Reason,
                    r.ReporterID AS ReporterId,
                    CONCAT(ur.FirstName, ' ', ur.LastName) AS ReporterName
            ";

            $fromWhere = "
                FROM dbo.Forum_Reports r
                INNER JOIN dbo.Forum_ReportTags rt ON r.ReportTagID = rt.ReportTagID
                LEFT JOIN dbo.Forum_Comments c ON r.CommentID = c.CommentID
                LEFT JOIN dbo.Forum_Posts p ON p.PostID = COALESCE(r.PostID, c.PostID)
                LEFT JOIN dbo.Forum_Users up ON up.UserID = p.AuthorID
                LEFT JOIN dbo.Forum_Users uc ON uc.UserID = c.UserID
                LEFT JOIN dbo.Forum_Users ur ON ur.UserID = r.ReporterID
                WHERE r.IsResolved = 0
            ";

            if (!$wantPaginate) {
                $sql = $selectCols . $fromWhere . " ORDER BY $orderSql";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                return json($res, ['ok' => true, 'reports' => $this->mapReportRows($rows)]);
            }

            $page = (int)($queryParams['page'] ?? 1);
            $perPage = (int)($queryParams['perPage'] ?? 25);
            if ($page < 1) {
                $page = 1;
            }
            $allowedSizes = [5, 10, 25, 50, 100];
            if (!in_array($perPage, $allowedSizes, true)) {
                $perPage = 25;
            }
            $offset = ($page - 1) * $perPage;

            $countSql = "SELECT COUNT(*) AS cnt $fromWhere";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute();
            $total = (int)$countStmt->fetchColumn();

            $sql = $selectCols . $fromWhere . " ORDER BY $orderSql OFFSET $offset ROWS FETCH NEXT $perPage ROWS ONLY";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return json($res, [
                'ok' => true,
                'reports' => $this->mapReportRows($rows),
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
            ]);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return json($res, ['ok' => false, 'error' => 'Internal server error.'], 500);
        }
    }

    public function resolveReport(Request $req, Response $res, array $args): Response
    {
        try {
            [$err, $pdo, $userId] = $this->requireRole(3, $req, $res);
            if ($err !== null) return $err;

            $reportId = (int)$args['id'];

            $stmt = $pdo->prepare("UPDATE dbo.Forum_Reports SET IsResolved = 1, ResolverID = :uid, ResolvedAt = SYSUTCDATETIME() WHERE ReportID = :id AND IsResolved = 0");
            $stmt->execute([':uid' => $userId, ':id' => $reportId]);

            if ($stmt->rowCount() === 0) {
                return json($res, ['ok' => false, 'error' => 'Report not found or already resolved'], 404);
            }

            return json($res, ['ok' => true]);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return json($res, ['ok' => false, 'error' => 'Internal server error.'], 500);
        }
    }

    public function getReportTags(Request $req, Response $res): Response
    {
        try {
            [$err, $pdo] = $this->requireAuth($req, $res);
            if ($err !== null) return $err;

            $sql = "SELECT ReportTagID, TagName FROM dbo.Forum_ReportTags
                    ORDER BY CASE WHEN TagName = 'Other' THEN 1 ELSE 0 END, TagName ASC";

            $tags = array_map(
                fn($r) => ['tagId' => (int)$r['ReportTagID'], 'name' => $r['TagName']],
                $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC)
            );

            return json($res, ['ok' => true, 'tags' => $tags]);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return json($res, ['ok' => false, 'error' => 'Internal server error.'], 500);
        }
    }

    public function submitReport(Request $req, Response $res): Response
    {
        try {
            [$err, $pdo, $userId] = $this->requireAuth($req, $res);
            if ($err !== null) return $err;

            $body = $req->getParsedBody();
            $targetId = $body['id'] ?? null;
            $tagId    = $body['tagID'] ?? null;
            $type     = $body['type'] ?? 'post';

            $banResponse = \Forum\Helpers\checkUserBan($pdo, (int)$userId, $res);
            if ($banResponse) return $banResponse;

            if (!$targetId || !$tagId) {
                return json($res, ['ok' => false, 'error' => 'Missing required fields'], 400);
            }

            $postId    = ($type === 'post') ? $targetId : null;
            $commentId = ($type === 'comment') ? $targetId : null;

            $checkSql = "SELECT TOP 1 ReportID FROM dbo.Forum_Reports
                         WHERE ReporterID = :userId
                         AND COALESCE(PostID, 0) = :postId
                         AND COALESCE(CommentID, 0) = :commentId
                         AND IsResolved = 0";

            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([
                ':userId'    => $userId,
                ':postId'    => (int)$postId,
                ':commentId' => (int)$commentId
            ]);

            if ($checkStmt->fetch()) {
                return json($res, ['ok' => false, 'error' => "You have already reported this $type."], 400);
            }

            $sql = "INSERT INTO dbo.Forum_Reports (ReporterID, PostID, CommentID, ReportTagID)
                    VALUES (:userId, :postId, :commentId, :tagId)";

            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([
                ':userId'    => $userId,
                ':postId'    => $postId,
                ':commentId' => $commentId,
                ':tagId'     => $tagId
            ]);

            return json($res, ['ok' => $success, 'message' => 'Report submitted successfully']);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return json($res, ['ok' => false, 'error' => 'Internal server error.'], 500);
        }
    }
}
