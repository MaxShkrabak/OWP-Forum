-- 002_users_and_auth.sql

-- Users
IF OBJECT_ID('dbo.Forum_Users', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Forum_Users (
        UserID                     INT           IDENTITY(1,1) NOT NULL CONSTRAINT PK_Users PRIMARY KEY,
        Email                      NVARCHAR(255)               NOT NULL CONSTRAINT UX_Users_Email UNIQUE,
        FirstName                  NVARCHAR(120)               NULL,
        LastName                   NVARCHAR(120)               NULL,
        RoleID                     INT                         NULL
            CONSTRAINT FK_Users_Roles REFERENCES dbo.Forum_Roles(RoleID),
        Avatar                     VARCHAR(100)                NOT NULL CONSTRAINT DF_Users_Avatar DEFAULT ('pfp-0.png'),
        EmailVerified              BIT                         NOT NULL CONSTRAINT DF_Users_EmailVerified DEFAULT (0),
        CreatedAt                  DATETIME2(0)                NOT NULL CONSTRAINT DF_Users_CreatedAt DEFAULT (SYSUTCDATETIME()),
        LastLogin                  DATETIME2(0)                NULL,
        TermsAccepted              BIT                         NOT NULL CONSTRAINT DF_Users_TermsAccepted DEFAULT (0),
        TermsAcceptedAt            DATETIME2(0)                NULL,
        IsBanned                   BIT                         NOT NULL CONSTRAINT DF_Users_IsBanned DEFAULT (0),
        BanType                    NVARCHAR(20)                NULL,
        BannedUntil                DATETIME2(0)                NULL,
        EmailNotificationsEnabled  BIT                         NOT NULL CONSTRAINT DF_Users_EmailNotificationsEnabled DEFAULT (1)
    );
END;
GO

-- OTP codes (single-use)
IF OBJECT_ID('dbo.Forum_OTP_Codes', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Forum_OTP_Codes (
        OtpID     INT           IDENTITY(1,1) NOT NULL CONSTRAINT PK_OtpCodes PRIMARY KEY,
        Email     NVARCHAR(255)               NOT NULL CONSTRAINT FK_OtpCodes_Email REFERENCES dbo.Forum_Users(Email),
        CodeHash  NVARCHAR(64)                NOT NULL, -- SHA-256, not raw code
        ExpiresAt DATETIME2(0)                NOT NULL,
        IsUsed    BIT                         NOT NULL CONSTRAINT DF_OtpCodes_IsUsed DEFAULT (0),
        CreatedAt DATETIME2(0)                NOT NULL CONSTRAINT DF_OtpCodes_CreatedAt DEFAULT (SYSUTCDATETIME())
    );
    CREATE NONCLUSTERED INDEX IX_OtpCodes_Email_Active
        ON dbo.Forum_OTP_Codes (Email, IsUsed, ExpiresAt);
END;
GO

-- Active user sessions
IF OBJECT_ID('dbo.Forum_Sessions', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Forum_Sessions (
        SessionID INT      IDENTITY(1,1) NOT NULL CONSTRAINT PK_Sessions PRIMARY KEY,
        UserID    INT                    NOT NULL CONSTRAINT FK_Sessions_Users REFERENCES dbo.Forum_Users(UserID),
        TokenHash CHAR(64)               NOT NULL,
        ExpiresAt DATETIME2(0)           NOT NULL
    );
    CREATE UNIQUE INDEX UX_Sessions_TokenHash ON dbo.Forum_Sessions (TokenHash);
END;
GO
