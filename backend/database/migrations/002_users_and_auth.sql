-- Users
IF OBJECT_ID('dbo.Forum_Users', 'U') IS NULL
BEGIN
  CREATE TABLE dbo.Forum_Users (
    User_ID                    INT IDENTITY(1,1) PRIMARY KEY,
    Email                      NVARCHAR(255) NOT NULL UNIQUE,
    FirstName                  NVARCHAR(120) NULL,
    LastName                   NVARCHAR(120) NULL,
    RoleID                     INT NULL FOREIGN KEY REFERENCES dbo.Forum_Roles(RoleID),
    Avatar                     VARCHAR(100) NOT NULL DEFAULT 'pfp-0.png',
    EmailVerified              BIT NOT NULL DEFAULT(0),
    Created                    DATETIME2(0) NOT NULL DEFAULT(SYSUTCDATETIME()),
    LastLogin                  DATETIME2(0) NULL,
    termsAccepted              BIT NOT NULL CONSTRAINT DF_Users_termsAccepted DEFAULT 0,
    termsAcceptedAt            DATETIME NULL,
    IsBanned                   BIT NOT NULL CONSTRAINT DF_Users_IsBanned DEFAULT 0,
    BanType                    NVARCHAR(20) NULL,
    BannedUntil                DATETIME2(0) NULL,
    EmailNotificationsEnabled  BIT NOT NULL CONSTRAINT DF_Users_EmailNotificationsEnabled DEFAULT (1)
  );
END;
GO

-- OTP codes (single-use)
IF OBJECT_ID('dbo.Forum_OTP_Codes', 'U') IS NULL
BEGIN
  CREATE TABLE dbo.Forum_OTP_Codes (
    OTP_ID       INT IDENTITY(1,1) PRIMARY KEY,
    User_ID      INT NOT NULL FOREIGN KEY REFERENCES dbo.Forum_Users(User_ID),
    CodeHash     VARBINARY(64) NOT NULL,              -- store SHA-256, not the raw code
    Expires_At   DATETIME2(0) NOT NULL,
    Is_Used      BIT NOT NULL DEFAULT(0),
    Created_At   DATETIME2(0) NOT NULL DEFAULT(SYSDATETIME())
  );
  CREATE NONCLUSTERED INDEX IX_OTP_User_Active
    ON dbo.Forum_OTP_Codes(User_ID, Is_Used, Expires_At);
END;
GO

-- Active user sessions
IF OBJECT_ID('dbo.Forum_Sessions', 'U') IS NULL
BEGIN
  CREATE TABLE dbo.Forum_Sessions (
    Session_ID   INT IDENTITY(1,1) PRIMARY KEY,
    User_ID      INT NOT NULL FOREIGN KEY REFERENCES dbo.Forum_Users(User_ID),
    Token_Hash   CHAR(64) NOT NULL,
    Expires      DATETIME2 NOT NULL
  );
  CREATE UNIQUE INDEX IX_Sessions_TokenHash ON dbo.Forum_Sessions(Token_Hash);
END;
GO