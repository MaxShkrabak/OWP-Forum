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