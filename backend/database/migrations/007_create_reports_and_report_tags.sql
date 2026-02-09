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
    CommentID      INT REFERENCES dbo.Comments(CommentID),
    ReportTagID    INT NOT NULL REFERENCES dbo.ReportTags(ReportTagID),
    CreatedAt      DATETIME2(0) NOT NULL DEFAULT(SYSDATETIME()),
    Resolved       BIT NOT NULL DEFAULT(0),
    ResolvedBy     INT REFERENCES dbo.Users(User_ID),
    ResolvedAt     DATETIME2(0)
  );
END;