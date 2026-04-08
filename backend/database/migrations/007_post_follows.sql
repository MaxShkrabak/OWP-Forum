IF OBJECT_ID('dbo.Forum_PostFollows', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Forum_PostFollows (
        PostID    INT NOT NULL,
        User_ID   INT NOT NULL,
        CreatedAt DATETIME2(0) NOT NULL DEFAULT SYSUTCDATETIME(),

        CONSTRAINT PK_PostFollows PRIMARY KEY (PostID, User_ID),
        CONSTRAINT FK_PostFollows_Posts
            FOREIGN KEY (PostID) REFERENCES dbo.Forum_Posts(PostID),
        CONSTRAINT FK_PostFollows_Users
            FOREIGN KEY (User_ID) REFERENCES dbo.Forum_Users(User_ID)
    );

    CREATE INDEX IX_PostFollows_User
        ON dbo.Forum_PostFollows (User_ID);
END;
GO