IF OBJECT_ID('dbo.Categories', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Categories
    (
        CategoryID INT IDENTITY(1,1) PRIMARY KEY,
        Name NVARCHAR(100) NOT NULL CONSTRAINT UX_Categories_Name UNIQUE,
        UsableByRoleID INT NOT NULL
            CONSTRAINT FK_Categories_Roles
            FOREIGN KEY REFERENCES dbo.Roles(RoleID)
    );
END;
GO

-- Table for tags such as (Research, questions, etc.)
IF OBJECT_ID('dbo.Tags','U') IS NULL
BEGIN
    CREATE TABLE dbo.Tags
    (
        TagID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Tags PRIMARY KEY,
        Name  NVARCHAR(100) NOT NULL CONSTRAINT UX_Tags_Name UNIQUE,
        UsableByRoleID INT NOT NULL
            CONSTRAINT FK_Tags_MinRole FOREIGN KEY REFERENCES dbo.Roles(RoleID)
    );
END;
GO

-- Seed categories (MERGE so already-applied or re-run is safe)
MERGE dbo.Categories AS c
USING (VALUES
  ('Announcements & News',  3), -- Mods and Admins
  ('Wastewater Treatment',  1),
  ('Water Treatment',       1),
  ('Wastewater Collection', 1),
  ('Water Distribution',    1),
  ('General',               1)
) AS s(Name, UsableByRoleID)
    ON c.Name = s.Name
WHEN NOT MATCHED BY TARGET THEN
    INSERT (Name, UsableByRoleID) VALUES (s.Name, s.UsableByRoleID)
WHEN MATCHED THEN
    UPDATE SET UsableByRoleID = s.UsableByRoleID;
GO

-- Seed Tags
MERGE dbo.Tags AS t
USING (VALUES 
    ('Official',    3),   -- Mods and Admins only
    ('Question',    1),
    ('Discussion',  1),
    ('Research',    1),
    ('Networking',  1),
    ('Information', 1),
    ('Events',      1)
) AS s(Name, UsableByRoleID)
    ON t.Name = s.Name
WHEN NOT MATCHED BY TARGET THEN 
    INSERT (Name, UsableByRoleID) VALUES (s.Name, s.UsableByRoleID)
WHEN MATCHED THEN 
    UPDATE SET UsableByRoleID = s.UsableByRoleID;
GO