-- 009_post_view_count.sql

IF COL_LENGTH('dbo.Forum_Posts', 'ViewCount') IS NULL
BEGIN
    ALTER TABLE dbo.Forum_Posts ADD ViewCount INT NOT NULL
        CONSTRAINT DF_Posts_ViewCount DEFAULT (0);
END;
GO

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
    IF UPDATE(ViewCount) AND NOT (
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

IF OBJECT_ID('dbo.Forum_PostViewDedup', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Forum_PostViewDedup (
        PostID       INT          NOT NULL,
        UserID       INT          NOT NULL,
        LastViewedAt DATETIME2(0) NOT NULL,
        CONSTRAINT PK_PostViewDedup       PRIMARY KEY (PostID, UserID),
        CONSTRAINT FK_PostViewDedup_Posts FOREIGN KEY (PostID) REFERENCES dbo.Forum_Posts(PostID),
        CONSTRAINT FK_PostViewDedup_Users FOREIGN KEY (UserID) REFERENCES dbo.Forum_Users(UserID)
    );
END;
GO
