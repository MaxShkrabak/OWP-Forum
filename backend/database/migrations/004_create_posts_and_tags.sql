-- Table for tags such as (Research, questions, etc.)
IF OBJECT_ID('dbo.Tags','U') IS NULL
BEGIN
    CREATE TABLE dbo.Tags
    (
        TagID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Tags PRIMARY KEY,
        Name  NVARCHAR(100) NOT NULL CONSTRAINT UX_Tags_Name UNIQUE,
        useableBy INT NOT NULL
            CONSTRAINT FK_Tags_MinRole FOREIGN KEY REFERENCES dbo.Roles(RoleID)
    );
END;
GO


-- Posts table
IF OBJECT_ID('dbo.Posts','U') IS NULL
BEGIN
    CREATE TABLE dbo.Posts
    (
        PostID      INT IDENTITY(1,1) CONSTRAINT PK_Posts PRIMARY KEY,
        Title       NVARCHAR(200)      NOT NULL,
        CategoryID  INT                NOT NULL
            CONSTRAINT FK_Posts_Categories REFERENCES dbo.Categories(CategoryID),
        AuthorID    INT                NOT NULL
            CONSTRAINT FK_Posts_Users      REFERENCES dbo.Users(User_ID),
        Content     NVARCHAR(MAX)      NOT NULL,
        CreatedAt   DATETIME2(0)       NOT NULL
            CONSTRAINT DF_Posts_CreatedAt DEFAULT (SYSUTCDATETIME()),
        UpdatedAt   DATETIME2(0)       NULL
    );


    CREATE INDEX IX_Posts_CategoryID ON dbo.Posts(CategoryID);
    CREATE INDEX IX_Posts_AuthorID   ON dbo.Posts(AuthorID);
    CREATE INDEX IX_Posts_CreatedAt  ON dbo.Posts(CreatedAt DESC);
END;
GO


/* Auto-stamp UpdatedAt on UPDATEs */
IF OBJECT_ID('dbo.tr_Posts_SetUpdatedAt','TR') IS NOT NULL
    DROP TRIGGER dbo.tr_Posts_SetUpdatedAt;
GO
CREATE TRIGGER dbo.tr_Posts_SetUpdatedAt
ON dbo.Posts
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE p
    SET    UpdatedAt = SYSUTCDATETIME()
    FROM   dbo.Posts p
    JOIN   inserted i ON i.PostID = p.PostID;
END;
GO
