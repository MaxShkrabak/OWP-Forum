IF OBJECT_ID('dbo.Pinned','U') IS NULL
BEGIN
    CREATE TABLE dbo.Pinned
    (
        PostID    INT NOT NULL
            CONSTRAINT PK_Pinned PRIMARY KEY,
        CreatedAt DATETIME2(0) NOT NULL
            CONSTRAINT DF_Pinned_CreatedAt DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT FK_Pinned_Posts FOREIGN KEY (PostID)
            REFERENCES dbo.Posts(PostID) ON DELETE CASCADE
    );
END;
GO