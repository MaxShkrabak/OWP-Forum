-- This is the many-to-many table since post can have many tags and a tag can be in many posts
IF OBJECT_ID('dbo.PostTags','U') IS NULL
BEGIN
    CREATE TABLE dbo.PostTags
    (
        PostID INT NOT NULL
            CONSTRAINT FK_PostTags_Posts REFERENCES dbo.Posts(PostID),
        TagID  INT NOT NULL
            CONSTRAINT FK_PostTags_Tags  REFERENCES dbo.Tags(TagID),
        CONSTRAINT PK_PostTags PRIMARY KEY (PostID, TagID)
    );
    CREATE INDEX IX_PostTags_TagID ON dbo.PostTags(TagID);
END;
GO