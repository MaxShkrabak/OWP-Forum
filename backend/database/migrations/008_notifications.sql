-- 008_notifications.sql

-- Create notifications table
IF OBJECT_ID('dbo.Forum_Notifications', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Forum_Notifications (
        NotificationID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        UserID INT NOT NULL,
        PostID INT NOT NULL,
        [Type] NVARCHAR(20) NOT NULL,
        IsRead BIT NOT NULL CONSTRAINT DF_Notifications_IsRead DEFAULT (0),
        CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_Notifications_CreatedAt DEFAULT SYSUTCDATETIME(),

        CONSTRAINT FK_Notifications_User
            FOREIGN KEY (UserID) REFERENCES dbo.Forum_Users(User_ID),

        CONSTRAINT FK_Notifications_Post
            FOREIGN KEY (PostID) REFERENCES dbo.Forum_Posts(PostID),

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
      AND object_id = OBJECT_ID('dbo.Forum_Notifications')
)
BEGIN
    CREATE INDEX IX_Notifications_UserID_IsRead_CreatedAt
        ON dbo.Forum_Notifications (UserID, IsRead, CreatedAt DESC);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_Notifications_PostID_Type'
      AND object_id = OBJECT_ID('dbo.Forum_Notifications')
)
BEGIN
    CREATE INDEX IX_Notifications_PostID_Type
        ON dbo.Forum_Notifications (PostID, [Type]);
END
GO

-- Prevent duplicate unread notifications of the same type for the same user/post
IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'UX_Notifications_User_Post_Type_Unread'
      AND object_id = OBJECT_ID('dbo.Forum_Notifications')
)
BEGIN
    CREATE UNIQUE INDEX UX_Notifications_User_Post_Type_Unread
        ON dbo.Forum_Notifications (UserID, PostID, [Type])
        WHERE IsRead = 0;
END
GO