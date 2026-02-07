CREATE TABLE Comments (
    CommentID INT IDENTITY(1,1) PRIMARY KEY,

    PostID INT NOT NULL,
    UserID INT NOT NULL,              -- author user id
    ParentCommentID INT NULL,

    MentionedUserID INT NULL,         -- user being mentioned

    Content VARCHAR(255) NOT NULL,

    CreatedAt DATETIME NOT NULL DEFAULT GETDATE(),
    UpdatedAt DATETIME NULL,

    LikeCount INT NOT NULL DEFAULT 0,

    CONSTRAINT FK_Comments_Posts
        FOREIGN KEY (PostID)
        REFERENCES Posts(PostID)
        ON DELETE CASCADE,

    CONSTRAINT FK_Comments_Users
        FOREIGN KEY (UserID)
        REFERENCES Users(User_ID)
        ON DELETE CASCADE,

    CONSTRAINT FK_Comments_Parent
        FOREIGN KEY (ParentCommentID)
        REFERENCES Comments(CommentID)
        ON DELETE NO ACTION,

    -- Avoid multiple cascade paths
    CONSTRAINT FK_Comments_MentionedUser
        FOREIGN KEY (MentionedUserID)
        REFERENCES Users(User_ID)
        ON DELETE NO ACTION
);
GO

-- Indexes
CREATE INDEX IX_Comments_PostID_CreatedAt
    ON Comments(PostID, CreatedAt);
GO

CREATE INDEX IX_Comments_ParentCommentID
    ON Comments(ParentCommentID);
GO

CREATE INDEX IX_Comments_UserID
    ON Comments(UserID);
GO

-- helpful for query mentions often
CREATE INDEX IX_Comments_MentionedUserID
    ON Comments(MentionedUserID);
GO