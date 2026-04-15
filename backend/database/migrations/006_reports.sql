-- 006_reports.sql

IF OBJECT_ID('dbo.Forum_ReportTags', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Forum_ReportTags (
        ReportTagID INT           IDENTITY(1,1) NOT NULL CONSTRAINT PK_ReportTags PRIMARY KEY,
        TagName     NVARCHAR(100)               NOT NULL CONSTRAINT UX_ReportTags_TagName UNIQUE
    );
END;
GO

IF OBJECT_ID('dbo.Forum_Reports', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Forum_Reports (
        ReportID    INT          IDENTITY(1,1) NOT NULL CONSTRAINT PK_Reports PRIMARY KEY,
        ReporterID  INT                        NOT NULL
            CONSTRAINT FK_Reports_Reporter   REFERENCES dbo.Forum_Users(UserID),
        PostID      INT                        NULL
            CONSTRAINT FK_Reports_Posts      REFERENCES dbo.Forum_Posts(PostID),
        CommentID   INT                        NULL
            CONSTRAINT FK_Reports_Comments   REFERENCES dbo.Forum_Comments(CommentID),
        ReportTagID INT                        NOT NULL
            CONSTRAINT FK_Reports_ReportTags REFERENCES dbo.Forum_ReportTags(ReportTagID),
        CreatedAt   DATETIME2(0)               NOT NULL CONSTRAINT DF_Reports_CreatedAt DEFAULT (SYSUTCDATETIME()),
        IsResolved  BIT                        NOT NULL CONSTRAINT DF_Reports_IsResolved DEFAULT (0),
        ResolverID  INT                        NULL
            CONSTRAINT FK_Reports_Resolver   REFERENCES dbo.Forum_Users(UserID),
        ResolvedAt  DATETIME2(0)               NULL,

        CONSTRAINT CK_Reports_Target CHECK (PostID IS NOT NULL OR CommentID IS NOT NULL)
    );
END;
GO

-- Seed report tags
MERGE dbo.Forum_ReportTags AS target
USING (VALUES
    ('Spam'),
    ('Harassment'),
    ('Inappropriate'),
    ('Misinformation')
) AS source (TagName)
    ON target.TagName = source.TagName
WHEN NOT MATCHED BY TARGET THEN
    INSERT (TagName) VALUES (source.TagName);
GO
