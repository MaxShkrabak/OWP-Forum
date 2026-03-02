If COL_LENGTH('dbo.Users', 'EmailNotiificationsEnabled') IS NULL
BEGIN
    ALTER TABLE dbo.Users
    ADD EmailNotiificationsEnabled BIT NOT NULL
        CONSTRAINT DF_Users_EmailNotiificationsEnabled DEFAULT (1);
END
GO

IF COL_LENGTH('dbo.Posts', 'LastCommentEmailSent') IS NULL
BEGIN
    ALTER TABLE dbo.Posts
    ADD LastCommentEmailSent DATETIME2(0) NULL;
END
GO