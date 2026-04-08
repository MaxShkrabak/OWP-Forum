IF COL_LENGTH('dbo.Forum_Categories', 'VisibleFromRoleID') IS NULL
BEGIN
    ALTER TABLE dbo.Forum_Categories
    ADD VisibleFromRoleID INT NULL
        CONSTRAINT FK_Categories_VisibleFromRole
        FOREIGN KEY REFERENCES dbo.Forum_Roles(RoleID);
END;
GO

UPDATE c
SET c.VisibleFromRoleID = v.VisibleFromRoleID
FROM dbo.Forum_Categories c
LEFT JOIN dbo.Forum_CategoryVisibility v
    ON v.CategoryID = c.CategoryID
WHERE c.VisibleFromRoleID IS NULL;
GO

IF OBJECT_ID('dbo.Forum_CategoryVisibility', 'U') IS NOT NULL
BEGIN
    DROP TABLE dbo.Forum_CategoryVisibility;
END;
GO