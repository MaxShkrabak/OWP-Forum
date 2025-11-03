/* Add RoleID column */
ALTER TABLE dbo.Users ADD RoleID INT NULL;
GO

/* add any missing roles from Users.Role */
INSERT INTO dbo.Roles (Name)
SELECT DISTINCT u.Role
FROM dbo.Users u
LEFT JOIN dbo.Roles r ON r.Name = u.Role
WHERE u.Role IS NOT NULL AND r.RoleID IS NULL;
GO

/* Update Users.RoleID based on matching role names */
UPDATE u
SET u.RoleID = r.RoleID
FROM dbo.Users u
JOIN dbo.Roles r ON r.Name = u.Role;
GO

/* Drop old Role column */
ALTER TABLE dbo.Users DROP COLUMN Role;
GO
