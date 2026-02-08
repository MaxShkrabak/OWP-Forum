CREATE TABLE dbo.PostFollows (
    PostID    INT NOT NULL,
    User_ID   INT NOT NULL,
    CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),

    CONSTRAINT PK_PostFollows PRIMARY KEY (PostID, User_ID),
    CONSTRAINT FK_PostFollows_Posts
        FOREIGN KEY (PostID) REFERENCES dbo.Posts(PostID),
    CONSTRAINT FK_PostFollows_Users
        FOREIGN KEY (User_ID) REFERENCES dbo.Users(User_ID)
);

CREATE INDEX IX_PostFollows_User
    ON dbo.PostFollows (User_ID);