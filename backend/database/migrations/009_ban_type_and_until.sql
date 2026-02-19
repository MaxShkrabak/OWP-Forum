-- Ban type (permanent vs temporary) and temp ban end date
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('dbo.Users') AND name = 'BanType'
)
BEGIN
    ALTER TABLE dbo.Users
    ADD BanType NVARCHAR(20) NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('dbo.Users') AND name = 'BannedUntil'
)
BEGIN
    ALTER TABLE dbo.Users
    ADD BannedUntil DATETIME2(0) NULL;
END;
GO
