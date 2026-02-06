IF OBJECT_ID ('dbo.Comments', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Comments (
        CommentId INT IDENTITY(1,1) PRIMARY KEY,
        PostId INT NOT NULL REFERENCES dbo.Posts(PostID),
        userID INT NOT NULL REFERENCES dbo.Users(User_ID),
        ParentCommentId INT NULL REFERENCES dbo.Comments(CommentId),
        Content NVARCHAR(1000) NOT NULL,
        CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        IsDeleted BIT NOT NULL DEFAULT 0,
        DeletedAt DATETIME2 NULL
    );
    -- Indexes for performance
    CREATE INDEX IX_Comments_PostId ON dbo.Comments(PostId);
    CREATE INDEX IX_Comments_ParentCommentId ON dbo.Comments(ParentCommentId);
    CREATE INDEX IX_Comments_CreatedAt ON dbo.Comments(CreatedAt DESC);
END;
GO