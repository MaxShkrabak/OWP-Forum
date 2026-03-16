IF OBJECT_ID ('dbo.Comments', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Comments (
        CommentId       INT IDENTITY(1,1) PRIMARY KEY,
        PostId          INT NOT NULL CONSTRAINT FK_Comments_Posts REFERENCES dbo.Posts(PostID),
        UserId          INT NOT NULL CONSTRAINT FK_Comments_Users REFERENCES dbo.Users(User_ID),
        ParentCommentId INT NULL     CONSTRAINT FK_Comments_Parent REFERENCES dbo.Comments(CommentId),
        Content         NVARCHAR(1000) NOT NULL,
        TotalScore      INT NOT NULL CONSTRAINT DF_Comments_TotalScore DEFAULT 0,
        CreatedAt       DATETIME2 NOT NULL CONSTRAINT DF_Comments_CreatedAt DEFAULT SYSUTCDATETIME(),
        UpdatedAt       DATETIME2(0) NULL,
        IsDeleted       BIT NOT NULL CONSTRAINT DF_Comments_IsDeleted DEFAULT 0,
        DeletedAt       DATETIME2 NULL
    );

    CREATE INDEX IX_Comments_PostId ON dbo.Comments(PostId);
    CREATE INDEX IX_Comments_ParentCommentId ON dbo.Comments(ParentCommentId);
    CREATE INDEX IX_Comments_CreatedAt ON dbo.Comments(CreatedAt DESC);
END;
GO

IF OBJECT_ID ('dbo.CommentVotes', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.CommentVotes (
        CommentId INT NOT NULL,
        UserId    INT NOT NULL,
        VoteValue SMALLINT NOT NULL,
        CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_CommentVotes_CreatedAt DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_CommentVotes PRIMARY KEY (CommentId, UserId),
        CONSTRAINT FK_CommentVotes_Comments FOREIGN KEY (CommentId)
            REFERENCES dbo.Comments(CommentId) ON DELETE CASCADE,
        CONSTRAINT FK_CommentVotes_Users FOREIGN KEY (UserId)
            REFERENCES dbo.Users(User_ID)
    );

    CREATE INDEX IX_CommentVotes_CommentId ON dbo.CommentVotes(CommentId);
END;
GO

IF OBJECT_ID('dbo.tr_CommentVotes_SyncScore','TR') IS NOT NULL
    DROP TRIGGER dbo.tr_CommentVotes_SyncScore;
GO

CREATE TRIGGER dbo.tr_CommentVotes_SyncScore
ON dbo.CommentVotes
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE c
    SET TotalScore = c.TotalScore + ISNULL(ins.Diff, 0) - ISNULL(del.Diff, 0)
    FROM dbo.Comments c
    LEFT JOIN (
        SELECT CommentId, SUM(VoteValue) AS Diff
        FROM inserted
        GROUP BY CommentId
    ) ins ON c.CommentId = ins.CommentId
    LEFT JOIN (
        SELECT CommentId, SUM(VoteValue) AS Diff
        FROM deleted
        GROUP BY CommentId
    ) del ON c.CommentId = del.CommentId
    WHERE ins.CommentId IS NOT NULL OR del.CommentId IS NOT NULL;
END;
GO