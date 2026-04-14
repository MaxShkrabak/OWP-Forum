-- 005_comments.sql

IF OBJECT_ID('dbo.Forum_Comments', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Forum_Comments (
        CommentID       INT           IDENTITY(1,1) NOT NULL CONSTRAINT PK_Comments PRIMARY KEY,
        PostID          INT                         NOT NULL CONSTRAINT FK_Comments_Posts  REFERENCES dbo.Forum_Posts(PostID),
        UserID          INT                         NOT NULL CONSTRAINT FK_Comments_Users  REFERENCES dbo.Forum_Users(UserID),
        ParentCommentID INT                         NULL     CONSTRAINT FK_Comments_Parent REFERENCES dbo.Forum_Comments(CommentID),
        Content         NVARCHAR(1000)              NOT NULL,
        TotalScore      INT                         NOT NULL CONSTRAINT DF_Comments_TotalScore DEFAULT (0),
        CreatedAt       DATETIME2(0)                NOT NULL CONSTRAINT DF_Comments_CreatedAt  DEFAULT (SYSUTCDATETIME()),
        UpdatedAt       DATETIME2(0)                NULL,
        IsDeleted       BIT                         NOT NULL CONSTRAINT DF_Comments_IsDeleted  DEFAULT (0),
        DeletedAt       DATETIME2(0)                NULL
    );

    CREATE INDEX IX_Comments_PostID          ON dbo.Forum_Comments (PostID);
    CREATE INDEX IX_Comments_ParentCommentID ON dbo.Forum_Comments (ParentCommentID);
    CREATE INDEX IX_Comments_CreatedAt       ON dbo.Forum_Comments (CreatedAt DESC);
END;
GO

IF OBJECT_ID('dbo.Forum_CommentVotes', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Forum_CommentVotes (
        CommentID INT          NOT NULL,
        UserID    INT          NOT NULL,
        VoteValue SMALLINT     NOT NULL,
        CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_CommentVotes_CreatedAt DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_CommentVotes              PRIMARY KEY (CommentID, UserID),
        CONSTRAINT FK_CommentVotes_Comments     FOREIGN KEY (CommentID) REFERENCES dbo.Forum_Comments(CommentID) ON DELETE CASCADE,
        CONSTRAINT FK_CommentVotes_Users        FOREIGN KEY (UserID)    REFERENCES dbo.Forum_Users(UserID)
    );

    CREATE INDEX IX_CommentVotes_CommentID ON dbo.Forum_CommentVotes (CommentID);
END;
GO

IF OBJECT_ID('dbo.Forum_tr_CommentVotes_SyncScore', 'TR') IS NOT NULL
    DROP TRIGGER dbo.Forum_tr_CommentVotes_SyncScore;
GO

CREATE TRIGGER dbo.Forum_tr_CommentVotes_SyncScore
ON dbo.Forum_CommentVotes
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE c
    SET TotalScore = c.TotalScore + ISNULL(ins.Diff, 0) - ISNULL(del.Diff, 0)
    FROM dbo.Forum_Comments c
    LEFT JOIN (
        SELECT CommentID, SUM(VoteValue) AS Diff
        FROM inserted
        GROUP BY CommentID
    ) ins ON c.CommentID = ins.CommentID
    LEFT JOIN (
        SELECT CommentID, SUM(VoteValue) AS Diff
        FROM deleted
        GROUP BY CommentID
    ) del ON c.CommentID = del.CommentID
    WHERE ins.CommentID IS NOT NULL OR del.CommentID IS NOT NULL;
END;
GO
