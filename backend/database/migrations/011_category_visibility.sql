IF OBJECT_ID('dbo.Forum_CategoryVisibility', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Forum_CategoryVisibility
    (
        CategoryVisibilityID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_CategoryVisibility PRIMARY KEY,
        CategoryID INT NOT NULL
            CONSTRAINT UQ_CategoryVisibility_CategoryID UNIQUE
            CONSTRAINT FK_CategoryVisibility_Categories
            FOREIGN KEY REFERENCES dbo.Forum_Categories(CategoryID)
            ON DELETE CASCADE,
        VisibleFromRoleID INT NOT NULL
            CONSTRAINT FK_CategoryVisibility_Roles
            FOREIGN KEY REFERENCES dbo.Forum_Roles(RoleID),
        CreatedAt DATETIME2 NOT NULL CONSTRAINT DF_CategoryVisibility_CreatedAt DEFAULT SYSUTCDATETIME(),
        UpdatedAt DATETIME2 NOT NULL CONSTRAINT DF_CategoryVisibility_UpdatedAt DEFAULT SYSUTCDATETIME()
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_CategoryVisibility_VisibleFromRoleID'
)
BEGIN
    ALTER TABLE dbo.Forum_CategoryVisibility
    ADD CONSTRAINT CK_CategoryVisibility_VisibleFromRoleID
    CHECK (VisibleFromRoleID IN (1, 2, 3, 4));
END;
GO