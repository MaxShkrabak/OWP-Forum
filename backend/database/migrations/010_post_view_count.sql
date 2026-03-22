
IF COL_LENGTH('dbo.Posts', 'ViewCount') IS NULL
BEGIN
    ALTER TABLE dbo.Posts ADD ViewCount INT NOT NULL
        CONSTRAINT DF_Posts_ViewCount DEFAULT (0);
END;
GO

/* Do not bump UpdatedAt when only view counts change (same pattern as TotalScore). */
IF OBJECT_ID('dbo.tr_Posts_SetUpdatedAt','TR') IS NOT NULL
    DROP TRIGGER dbo.tr_Posts_SetUpdatedAt;
GO
CREATE TRIGGER dbo.tr_Posts_SetUpdatedAt
ON dbo.Posts
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    IF UPDATE (TotalScore) AND NOT (
        UPDATE(Title) OR
        UPDATE(CategoryID) OR
        UPDATE(Content)
    )
        RETURN;
    IF UPDATE (ViewCount) AND NOT (
        UPDATE(Title) OR
        UPDATE(CategoryID) OR
        UPDATE(Content)
    )
        RETURN;
    UPDATE p
    SET    UpdatedAt = SYSUTCDATETIME()
    FROM   dbo.Posts p
    JOIN   inserted i ON i.PostID = p.PostID;
END;
GO
