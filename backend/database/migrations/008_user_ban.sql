-- Add IsBanned to Users for admin ban feature
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('dbo.Users') AND name = 'IsBanned'
)
BEGIN
    ALTER TABLE dbo.Users
    ADD IsBanned BIT NOT NULL DEFAULT(0);
END;
GO
