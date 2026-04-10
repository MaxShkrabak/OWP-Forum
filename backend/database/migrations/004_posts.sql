-- 004_posts.sql

-- Posts table
IF OBJECT_ID('dbo.Forum_Posts', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Forum_Posts (
        PostID                        INT           IDENTITY(1,1) NOT NULL CONSTRAINT PK_Posts PRIMARY KEY,
        Title                         NVARCHAR(125)               NOT NULL,
        CategoryID                    INT                         NOT NULL
            CONSTRAINT FK_Posts_Categories REFERENCES dbo.Forum_Categories(CategoryID),
        AuthorID                      INT                         NOT NULL
            CONSTRAINT FK_Posts_Users REFERENCES dbo.Forum_Users(UserID),
        TotalScore                    INT                         NOT NULL
            CONSTRAINT DF_Posts_TotalScore DEFAULT (0),
        Content                       NVARCHAR(MAX)               NOT NULL,
        CreatedAt                     DATETIME2(0)                NOT NULL
            CONSTRAINT DF_Posts_CreatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedAt                     DATETIME2(0)                NULL,
        LastCommentNotificationSentAt DATETIME2(0)                NULL,
        IsDeleted                     BIT                         NOT NULL
            CONSTRAINT DF_Posts_IsDeleted DEFAULT (0),
        DeletedAt                     DATETIME2(0)                NULL,
        IsCommentsDisabled            BIT                         NOT NULL
            CONSTRAINT DF_Posts_IsCommentsDisabled DEFAULT (0)
    );

    CREATE INDEX IX_Posts_CategoryID ON dbo.Forum_Posts (CategoryID);
    CREATE INDEX IX_Posts_AuthorID   ON dbo.Forum_Posts (AuthorID);
    CREATE INDEX IX_Posts_CreatedAt  ON dbo.Forum_Posts (CreatedAt DESC);
    CREATE INDEX IX_Posts_Active     ON dbo.Forum_Posts (PostID) WHERE IsDeleted = 0;
END;
GO

/* Auto-stamp UpdatedAt on content UPDATEs */
IF OBJECT_ID('dbo.Forum_tr_Posts_SetUpdatedAt', 'TR') IS NOT NULL
    DROP TRIGGER dbo.Forum_tr_Posts_SetUpdatedAt;
GO
CREATE TRIGGER dbo.Forum_tr_Posts_SetUpdatedAt
ON dbo.Forum_Posts
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    IF UPDATE(TotalScore) AND NOT (
        UPDATE(Title) OR
        UPDATE(CategoryID) OR
        UPDATE(Content)
    )
        RETURN;
    UPDATE p
    SET    UpdatedAt = SYSUTCDATETIME()
    FROM   dbo.Forum_Posts p
    JOIN   inserted i ON i.PostID = p.PostID;
END;
GO

IF OBJECT_ID('dbo.Forum_PostTags', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Forum_PostTags (
        PostID INT NOT NULL CONSTRAINT FK_PostTags_Posts REFERENCES dbo.Forum_Posts(PostID) ON DELETE CASCADE,
        TagID  INT NOT NULL CONSTRAINT FK_PostTags_Tags  REFERENCES dbo.Forum_Tags(TagID)   ON DELETE CASCADE,
        CONSTRAINT PK_PostTags PRIMARY KEY (PostID, TagID)
    );
    CREATE INDEX IX_PostTags_TagID ON dbo.Forum_PostTags (TagID);
END;
GO

IF OBJECT_ID('dbo.Forum_PostVotes', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Forum_PostVotes (
        PostID    INT          NOT NULL,
        UserID    INT          NOT NULL,
        VoteValue SMALLINT     NOT NULL, -- 1 = upvote, -1 = downvote
        CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_PostVotes_CreatedAt DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT PK_PostVotes        PRIMARY KEY (PostID, UserID),
        CONSTRAINT FK_PostVotes_Posts  FOREIGN KEY (PostID) REFERENCES dbo.Forum_Posts(PostID) ON DELETE CASCADE,
        CONSTRAINT FK_PostVotes_Users  FOREIGN KEY (UserID) REFERENCES dbo.Forum_Users(UserID)
    );
    CREATE INDEX IX_PostVotes_PostID ON dbo.Forum_PostVotes (PostID);
END;
GO

-- Trigger to maintain TotalScore
IF OBJECT_ID('dbo.Forum_tr_PostVotes_SyncScore', 'TR') IS NOT NULL
    DROP TRIGGER dbo.Forum_tr_PostVotes_SyncScore;
GO
CREATE TRIGGER dbo.Forum_tr_PostVotes_SyncScore
ON dbo.Forum_PostVotes
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE p
    SET TotalScore = p.TotalScore + ISNULL(ins.Diff, 0) - ISNULL(del.Diff, 0)
    FROM dbo.Forum_Posts p
    LEFT JOIN (SELECT PostID, SUM(VoteValue) AS Diff FROM inserted GROUP BY PostID) ins
        ON p.PostID = ins.PostID
    LEFT JOIN (SELECT PostID, SUM(VoteValue) AS Diff FROM deleted  GROUP BY PostID) del
        ON p.PostID = del.PostID
    WHERE ins.PostID IS NOT NULL OR del.PostID IS NOT NULL;
END;
GO
