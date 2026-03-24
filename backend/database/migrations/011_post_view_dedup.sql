/* Per-user last view time for post view counts (cooldown; see PostRoutes get-post). */
IF OBJECT_ID('dbo.PostViewDedup', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.PostViewDedup (
        PostID INT NOT NULL,
        UserID INT NOT NULL,
        LastViewedAt DATETIME2 NOT NULL,
        CONSTRAINT PK_PostViewDedup PRIMARY KEY (PostID, UserID),
        CONSTRAINT FK_PostViewDedup_Posts FOREIGN KEY (PostID) REFERENCES dbo.Posts(PostID)
    );
END;
GO
