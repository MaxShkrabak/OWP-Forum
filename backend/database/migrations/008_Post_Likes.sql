CREATE TABLE dbo.PostLikes (
  PostID    INT NOT NULL,
  User_ID   INT NOT NULL,
  VoteValue SMALLINT NOT NULL,
  VotedAt   DATETIME2(0) NOT NULL
    CONSTRAINT DF_PostLikes_VotedAt DEFAULT SYSUTCDATETIME(),

  CONSTRAINT PK_PostLikes PRIMARY KEY (PostID, User_ID),
  CONSTRAINT FK_PostLikes_Posts FOREIGN KEY (PostID)
    REFERENCES dbo.Posts(PostID) ON DELETE CASCADE,
  CONSTRAINT FK_PostLikes_Users FOREIGN KEY (User_ID)
    REFERENCES dbo.Users(User_ID) ON DELETE CASCADE,
  CONSTRAINT CK_PostLikes_VoteValue CHECK (VoteValue IN (-1, 1))
);

CREATE INDEX IX_PostLikes_PostID ON dbo.PostLikes(PostID);
CREATE INDEX IX_PostLikes_UserID ON dbo.PostLikes(User_ID);
