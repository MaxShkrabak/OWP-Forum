IF COL_LENGTH('dbo.Comments', 'UpVotes') IS NULL
BEGIN
    ALTER TABLE dbo.Comments
    ADD UpVotes INT NOT NULL CONSTRAINT DF_Comments_UpVotes DEFAULT 0;
END;
GO

IF COL_LENGTH('dbo.Comments', 'DownVotes') IS NULL
BEGIN
    ALTER TABLE dbo.Comments
    ADD DownVotes INT NOT NULL CONSTRAINT DF_Comments_DownVotes DEFAULT 0;
END;
GO

IF OBJECT_ID ('dbo.CommentVotes', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.CommentVotes (
        CommentVoteId INT IDENTITY(1,1) PRIMARY KEY,
        CommentId INT NOT NULL REFERENCES dbo.Comments(CommentId),
        UserId INT NOT NULL REFERENCES dbo.Users(User_ID),
        VoteValue SMALLINT NOT NULL,
        CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        UpdatedAt DATETIME2 NULL,
        CONSTRAINT UQ_CommentVotes_UserId_CommentId UNIQUE (UserId, CommentId),
        CONSTRAINT CK_CommentVotes_VoteValue CHECK (VoteValue IN (1, -1))
    );
    -- Indexes for performance
    CREATE INDEX IX_CommentVotes_CommentId ON dbo.CommentVotes(CommentId);
    CREATE INDEX IX_CommentVotes_UserId ON dbo.CommentVotes(UserId);
END;
GO