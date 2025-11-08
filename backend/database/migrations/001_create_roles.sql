-- Role types table
IF OBJECT_ID('dbo.Roles','U') IS NULL
BEGIN
    CREATE TABLE dbo.Roles
    (
        RoleID INT IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_Roles PRIMARY KEY,
        Name NVARCHAR(50) NOT NULL
            CONSTRAINT UX_Roles_Name UNIQUE
    );
END;
GO


MERGE dbo.Roles AS t
USING (VALUES (N'user'), (N'student'), (N'moderator'), (N'admin')) AS s(Name)
    ON t.Name = s.Name
WHEN NOT MATCHED BY TARGET THEN INSERT (Name) VALUES (s.Name);
GO