-- 003_categories_and_tags.sql

IF OBJECT_ID('dbo.Forum_Categories', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Forum_Categories (
        CategoryID        INT           IDENTITY(1,1) NOT NULL CONSTRAINT PK_Categories PRIMARY KEY,
        Name              NVARCHAR(100)               NOT NULL CONSTRAINT UX_Categories_Name UNIQUE,
        UsableByRoleID    INT                         NOT NULL
            CONSTRAINT FK_Categories_Roles            REFERENCES dbo.Forum_Roles(RoleID),
        VisibleFromRoleID INT                         NULL
            CONSTRAINT FK_Categories_VisibleFromRole  REFERENCES dbo.Forum_Roles(RoleID)
    );
END;
GO

-- Table for tags (e.g., Research, Questions, etc.)
IF OBJECT_ID('dbo.Forum_Tags', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Forum_Tags (
        TagID          INT           IDENTITY(1,1) NOT NULL CONSTRAINT PK_Tags PRIMARY KEY,
        Name           NVARCHAR(100)               NOT NULL CONSTRAINT UX_Tags_Name UNIQUE,
        UsableByRoleID INT                         NOT NULL
            CONSTRAINT FK_Tags_Roles REFERENCES dbo.Forum_Roles(RoleID)
    );
END;
GO

-- Seed categories
-- General is admin-only (UsableByRoleID = 4, VisibleFromRoleID = 4): used as a
-- fallback when a category is deleted, not intended for regular user posting.
MERGE dbo.Forum_Categories AS c
USING (VALUES
    ('Announcements & News',  3, NULL),
    ('Wastewater Treatment',  1, NULL),
    ('Water Treatment',       1, NULL),
    ('Wastewater Collection', 1, NULL),
    ('Water Distribution',    1, NULL),
    ('General',               4,    4)
) AS s (Name, UsableByRoleID, VisibleFromRoleID)
    ON c.Name = s.Name
WHEN NOT MATCHED BY TARGET THEN
    INSERT (Name, UsableByRoleID, VisibleFromRoleID) VALUES (s.Name, s.UsableByRoleID, s.VisibleFromRoleID)
WHEN MATCHED THEN
    UPDATE SET UsableByRoleID    = s.UsableByRoleID,
               VisibleFromRoleID = s.VisibleFromRoleID;
GO

-- Seed tags
MERGE dbo.Forum_Tags AS t
USING (VALUES
    ('Official',    3),
    ('Question',    1),
    ('Discussion',  1),
    ('Research',    1),
    ('Networking',  1),
    ('Information', 1),
    ('Events',      1)
) AS s (Name, UsableByRoleID)
    ON t.Name = s.Name
WHEN NOT MATCHED BY TARGET THEN
    INSERT (Name, UsableByRoleID) VALUES (s.Name, s.UsableByRoleID)
WHEN MATCHED THEN
    UPDATE SET UsableByRoleID = s.UsableByRoleID;
GO
