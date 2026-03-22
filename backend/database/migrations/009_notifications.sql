-- 009_notifications.sql

-- Add new notification preference columns to dbo.Users if they do not exist
IF COL_LENGTH('dbo.Users', 'PushNotificationsEnabled') IS NULL
BEGIN
    ALTER TABLE dbo.Users
    ADD PushNotificationsEnabled BIT NOT NULL
        CONSTRAINT DF_Users_PushNotificationsEnabled DEFAULT (1);
END
GO

IF COL_LENGTH('dbo.Users', 'PostLikeNotificationsEnabled') IS NULL
BEGIN
    ALTER TABLE dbo.Users
    ADD PostLikeNotificationsEnabled BIT NOT NULL
        CONSTRAINT DF_Users_PostLikeNotificationsEnabled DEFAULT (1);
END
GO

IF COL_LENGTH('dbo.Users', 'PostReplyNotificationsEnabled') IS NULL
BEGIN
    ALTER TABLE dbo.Users
    ADD PostReplyNotificationsEnabled BIT NOT NULL
        CONSTRAINT DF_Users_PostReplyNotificationsEnabled DEFAULT (1);
END
GO

-- Create notifications table
IF OBJECT_ID('dbo.Notifications', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Notifications (
        NotificationID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        UserID INT NOT NULL,
        PostID INT NOT NULL,
        [Type] NVARCHAR(20) NOT NULL,
        IsRead BIT NOT NULL CONSTRAINT DF_Notifications_IsRead DEFAULT (0),
        CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_Notifications_CreatedAt DEFAULT SYSUTCDATETIME(),

        CONSTRAINT FK_Notifications_User
            FOREIGN KEY (UserID) REFERENCES dbo.Users(User_ID),

        CONSTRAINT FK_Notifications_Post
            FOREIGN KEY (PostID) REFERENCES dbo.Posts(PostID),

        CONSTRAINT CK_Notifications_Type
            CHECK ([Type] IN ('postLike', 'postReply'))
    );
END
GO

-- Helpful indexes
IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_Notifications_UserID_IsRead_CreatedAt'
      AND object_id = OBJECT_ID('dbo.Notifications')
)
BEGIN
    CREATE INDEX IX_Notifications_UserID_IsRead_CreatedAt
        ON dbo.Notifications (UserID, IsRead, CreatedAt DESC);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_Notifications_PostID_Type'
      AND object_id = OBJECT_ID('dbo.Notifications')
)
BEGIN
    CREATE INDEX IX_Notifications_PostID_Type
        ON dbo.Notifications (PostID, [Type]);
END
GO

-- Prevent duplicate unread notifications of the same type for the same user/post
IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'UX_Notifications_User_Post_Type_Unread'
      AND object_id = OBJECT_ID('dbo.Notifications')
)
BEGIN
    CREATE UNIQUE INDEX UX_Notifications_User_Post_Type_Unread
        ON dbo.Notifications (UserID, PostID, [Type])
        WHERE IsRead = 0;
END
GO