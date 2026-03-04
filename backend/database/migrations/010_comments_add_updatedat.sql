IF COL_LENGTH('dbo.Comments', 'UpdatedAt') IS NULL
BEGIN
    ALTER TABLE dbo.Comments
        ADD UpdatedAt DATETIME2(0) NULL;
END;
GO

