IF OBJECT_ID('dbo.ReportTags', 'U') IS NULL
BEGIN
  CREATE TABLE dbo.ReportTags (
    ReportTagID     INT IDENTITY(1,1) PRIMARY KEY,
    TagName         NVARCHAR(100) NOT NULL UNIQUE
  );
END;

IF OBJECT_ID('dbo.Reports', 'U') IS NULL
BEGIN
  CREATE TABLE dbo.Reports (
    ReportID       INT IDENTITY(1,1) PRIMARY KEY,
    ReportUserID   INT NOT NULL REFERENCES dbo.Users(User_ID),
    PostID         INT REFERENCES dbo.Posts(PostID),
    CommentID      INT NULL,
    ReportTagID    INT NOT NULL REFERENCES dbo.ReportTags(ReportTagID),
    CreatedAt      DATETIME2(0) NOT NULL DEFAULT(SYSUTCDATETIME()),
    Resolved       BIT NOT NULL DEFAULT(0),
    ResolvedBy     INT REFERENCES dbo.Users(User_ID),
    ResolvedAt     DATETIME2(0)
  );
END;

IF OBJECT_ID(N'dbo.Comments', 'U') IS NOT NULL
AND EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'CommentID' AND Object_ID = OBJECT_ID(N'dbo.Reports'))
AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_Reports_Comments')
BEGIN
    ALTER TABLE dbo.Reports
    ADD CONSTRAINT FK_Reports_Comments
    FOREIGN KEY (CommentID) REFERENCES dbo.Comments(CommentID);
END;
GO

-- Seed ReportTags
MERGE dbo.ReportTags AS target
USING (VALUES 
    ('Spam'),
    ('Harassment'),
    ('Inappropriate Content'),
    ('Misinformation'),
    ('Other')
) AS source (TagName)
ON target.TagName = source.TagName
WHEN NOT MATCHED BY TARGET THEN
    INSERT (TagName) VALUES (source.TagName);
GO