
-- Posts table
IF OBJECT_ID('dbo.Posts','U') IS NULL
BEGIN
    CREATE TABLE dbo.Posts
    (
        PostID      INT IDENTITY(1,1) CONSTRAINT PK_Posts PRIMARY KEY,
        Title       NVARCHAR(125)      NOT NULL,
        CategoryID  INT                NOT NULL
            CONSTRAINT FK_Posts_Categories REFERENCES dbo.Categories(CategoryID),
        AuthorID    INT                NOT NULL
            CONSTRAINT FK_Posts_Users      REFERENCES dbo.Users(User_ID),
        TotalScore  INT                NOT NULL      -- Sum of upvotes - downvotes
            CONSTRAINT DF_Posts_TotalVotes  DEFAULT (0),
        Content     NVARCHAR(MAX)      NOT NULL,
        CreatedAt   DATETIME2(0)       NOT NULL
            CONSTRAINT DF_Posts_CreatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedAt   DATETIME2(0)       NULL,
        IsDeleted   BIT                NOT NULL
            CONSTRAINT DF_Posts_IsDeleted  DEFAULT (0),
        DeletedAt   DATETIME2(0)       NULL
    );

    CREATE INDEX IX_Posts_CategoryID ON dbo.Posts(CategoryID);
    CREATE INDEX IX_Posts_AuthorID   ON dbo.Posts(AuthorID);
    CREATE INDEX IX_Posts_CreatedAt  ON dbo.Posts(CreatedAt DESC);
    CREATE INDEX IX_Posts_Active     ON dbo.Posts(PostID) WHERE IsDeleted = 0;
END;
GO

/* Auto-stamp UpdatedAt on UPDATEs */
IF OBJECT_ID('dbo.tr_Posts_SetUpdatedAt','TR') IS NOT NULL
    DROP TRIGGER dbo.tr_Posts_SetUpdatedAt;
GO
CREATE TRIGGER dbo.tr_Posts_SetUpdatedAt
ON dbo.Posts
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE p
    SET    UpdatedAt = SYSUTCDATETIME()
    FROM   dbo.Posts p
    JOIN   inserted i ON i.PostID = p.PostID;
END;
GO

-- This is the many-to-many table since post can have many tags and a tag can be in many posts
IF OBJECT_ID('dbo.PostTags','U') IS NULL
BEGIN
    CREATE TABLE dbo.PostTags
    (
        PostID INT NOT NULL
            CONSTRAINT FK_PostTags_Posts REFERENCES dbo.Posts(PostID) ON DELETE CASCADE,
        TagID  INT NOT NULL
            CONSTRAINT FK_PostTags_Tags  REFERENCES dbo.Tags(TagID) ON DELETE CASCADE,
        CONSTRAINT PK_PostTags PRIMARY KEY (PostID, TagID)
    );
    CREATE INDEX IX_PostTags_TagID ON dbo.PostTags(TagID);
END;
GO

IF OBJECT_ID('dbo.PostVotes','U') IS NULL
BEGIN
    CREATE TABLE dbo.PostVotes (
        PostID    INT NOT NULL,
        User_ID   INT NOT NULL,
        VoteValue SMALLINT NOT NULL, -- (1 for upvote, -1 for downvote)
        CreatedAt DATETIME2(0) NOT NULL
            CONSTRAINT DF_PostVotes_CreatedAt DEFAULT (SYSUTCDATETIME()),
       
        -- One vote per post per user
        CONSTRAINT PK_PostVotes PRIMARY KEY (PostID, User_ID),
       
        CONSTRAINT FK_Votes_Posts FOREIGN KEY (PostID)
            REFERENCES dbo.Posts(PostID) ON DELETE CASCADE,
        CONSTRAINT FK_Votes_Users FOREIGN KEY (User_ID)
            REFERENCES dbo.Users(User_ID)
    );
    CREATE INDEX IX_PostVotes_PostID ON dbo.PostVotes(PostID);
END;
GO

-- Trigger to maintain TotalScore in Posts table
IF OBJECT_ID('dbo.tr_PostVotes_SyncScore','TR') IS NOT NULL
    DROP TRIGGER dbo.tr_PostVotes_SyncScore;
GO
CREATE TRIGGER dbo.tr_PostVotes_SyncScore
ON dbo.PostVotes
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE p
    SET TotalScore = ISNULL((SELECT SUM(VoteValue) FROM dbo.PostVotes WHERE PostID = p.PostID), 0)
    FROM dbo.Posts p
    WHERE p.PostID IN (SELECT PostID FROM inserted UNION SELECT PostID FROM deleted);
END;
GO