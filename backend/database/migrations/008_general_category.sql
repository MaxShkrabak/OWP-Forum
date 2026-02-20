-- Ensure "General" category exists for moving posts when a category is deleted
IF NOT EXISTS (SELECT 1 FROM dbo.Categories WHERE Name = N'General')
BEGIN
    INSERT INTO dbo.Categories (Name, UsableByRoleID)
    VALUES (N'General', 1);
END;
GO
