-- 008_notifications.sql

IF OBJECT_ID('dbo.Forum_Notifications', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Forum_Notifications (
        NotificationID   INT          IDENTITY(1,1) NOT NULL CONSTRAINT PK_Notifications PRIMARY KEY,
        UserID           INT                        NOT NULL
            CONSTRAINT FK_Notifications_Users REFERENCES dbo.Forum_Users(UserID),
        PostID           INT                        NOT NULL
            CONSTRAINT FK_Notifications_Posts REFERENCES dbo.Forum_Posts(PostID),
        NotificationType NVARCHAR(20)               NOT NULL,
        IsRead           BIT                        NOT NULL CONSTRAINT DF_Notifications_IsRead    DEFAULT (0),
        CreatedAt        DATETIME2(0)               NOT NULL CONSTRAINT DF_Notifications_CreatedAt DEFAULT (SYSUTCDATETIME()),

        CONSTRAINT CK_Notifications_Type
            CHECK (NotificationType IN ('postLike', 'postReply'))
    );
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_Notifications_UserID_IsRead_CreatedAt'
      AND object_id = OBJECT_ID('dbo.Forum_Notifications')
)
BEGIN
    CREATE INDEX IX_Notifications_UserID_IsRead_CreatedAt
        ON dbo.Forum_Notifications (UserID, IsRead, CreatedAt DESC);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_Notifications_PostID_Type'
      AND object_id = OBJECT_ID('dbo.Forum_Notifications')
)
BEGIN
    CREATE INDEX IX_Notifications_PostID_Type
        ON dbo.Forum_Notifications (PostID, NotificationType);
END;
GO

-- Prevent duplicate unread notifications of the same type for the same user/post
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'UX_Notifications_User_Post_Type_Unread'
      AND object_id = OBJECT_ID('dbo.Forum_Notifications')
)
BEGIN
    CREATE UNIQUE INDEX UX_Notifications_User_Post_Type_Unread
        ON dbo.Forum_Notifications (UserID, PostID, NotificationType)
        WHERE IsRead = 0;
END;
GO
