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