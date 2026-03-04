If COL_LENGTH('dbo.Users', 'EmailNotificationsEnabled') IS NULL
BEGIN
    ALTER TABLE dbo.Users
    ADD EmailNotificationsEnabled BIT NOT NULL
        CONSTRAINT DF_Users_EmailNotificationsEnabled DEFAULT (1);
END
GO

IF COL_LENGTH('dbo.Posts', 'LastCommentNotificationSentAt') IS NULL
BEGIN
    ALTER TABLE dbo.Posts
    ADD LastCommentNotificationSentAt DATETIME2(0) NULL;
END
GO