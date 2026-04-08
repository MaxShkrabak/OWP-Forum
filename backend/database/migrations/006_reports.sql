-- 006_reports.sql

IF OBJECT_ID('dbo.Forum_ReportTags', 'U') IS NULL
BEGIN
  CREATE TABLE dbo.Forum_ReportTags (
    ReportTagID     INT IDENTITY(1,1) PRIMARY KEY,
    TagName         NVARCHAR(100) NOT NULL UNIQUE
  );
END;

IF OBJECT_ID('dbo.Forum_Reports', 'U') IS NULL
BEGIN
  CREATE TABLE dbo.Forum_Reports (
    ReportID       INT IDENTITY(1,1) PRIMARY KEY,
    ReportUserID   INT NOT NULL REFERENCES dbo.Forum_Users(User_ID),
    PostID         INT REFERENCES dbo.Forum_Posts(PostID),
    CommentID      INT NULL
        CONSTRAINT FK_Reports_Comments REFERENCES dbo.Forum_Comments(CommentId),
    ReportTagID    INT NOT NULL REFERENCES dbo.Forum_ReportTags(ReportTagID),
    CreatedAt      DATETIME2(0) NOT NULL DEFAULT(SYSUTCDATETIME()),
    Resolved       BIT NOT NULL DEFAULT(0),
    ResolvedBy     INT REFERENCES dbo.Forum_Users(User_ID),
    ResolvedAt     DATETIME2(0)
  );
END;
GO

-- Seed ReportTags
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