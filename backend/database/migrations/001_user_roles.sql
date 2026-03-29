-- Optional: track schema versions for migrate.php
IF OBJECT_ID('dbo.Forum_SchemaVersions', 'U') IS NULL
BEGIN
  CREATE TABLE dbo.Forum_SchemaVersions (
    Id INT IDENTITY(1,1) PRIMARY KEY,
    ScriptName NVARCHAR(255) NOT NULL,
    AppliedAt  DATETIME2(0)  NOT NULL DEFAULT(SYSDATETIME())
  );
END;

-- Role types table
IF OBJECT_ID('dbo.Forum_Roles','U') IS NULL
BEGIN
    CREATE TABLE dbo.Forum_Roles (
        RoleID INT IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_Roles PRIMARY KEY,
        Name NVARCHAR(50) NOT NULL
            CONSTRAINT UX_Roles_Name UNIQUE
    );
END;
GO

-- Seed role types
MERGE dbo.Forum_Roles AS t
USING (VALUES (N'user'), (N'student'), (N'moderator'), (N'admin')) AS s(Name)
    ON t.Name = s.Name
WHEN NOT MATCHED BY TARGET THEN INSERT (Name) VALUES (s.Name);
GO