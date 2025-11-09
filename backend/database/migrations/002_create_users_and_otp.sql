-- Users
IF OBJECT_ID('dbo.Users', 'U') IS NULL
BEGIN
  CREATE TABLE dbo.Users (
    User_ID        INT IDENTITY(1,1) PRIMARY KEY,
    Email          NVARCHAR(255) NOT NULL UNIQUE,
    FirstName      NVARCHAR(120) NULL,
    LastName       NVARCHAR(120) NULL,
    RoleID         INT NULL FOREIGN KEY REFERENCES dbo.Roles(RoleID),
    Avatar         VARCHAR(100) NOT NULL DEFAULT'guest-user-icon.png',
    EmailVerified  BIT NOT NULL DEFAULT(0),
    Created        DATETIME2(0) NOT NULL DEFAULT(SYSDATETIME()),
    LastLogin      DATETIME2(0) NULL
  );
END;

-- OTP codes (single-use)
IF OBJECT_ID('dbo.OTP_Codes', 'U') IS NULL
BEGIN
  CREATE TABLE dbo.OTP_Codes (
    OTP_ID       INT IDENTITY(1,1) PRIMARY KEY,
    User_ID      INT NOT NULL FOREIGN KEY REFERENCES dbo.Users(User_ID),
    CodeHash     VARBINARY(64) NOT NULL,              -- store SHA-256, not the raw code
    Expires_At   DATETIME2(0) NOT NULL,
    Is_Used      BIT NOT NULL DEFAULT(0),
    Created_At   DATETIME2(0) NOT NULL DEFAULT(SYSDATETIME())
  );
  CREATE NONCLUSTERED INDEX IX_OTP_User_Active
    ON dbo.OTP_Codes(User_ID, Is_Used, Expires_At);
END;

-- Table for storing user sessions
IF OBJECT_ID('dbo.Sessions', 'U') IS NULL
BEGIN
  CREATE TABLE dbo.Sessions (
    Session_ID   INT IDENTITY(1,1) PRIMARY KEY,
    User_ID      INT NOT NULL FOREIGN KEY REFERENCES dbo.Users(User_ID),
    Token_Hash   CHAR(64) NOT NULL,
    Expires      DATETIME2 NOT NULL
  );

  CREATE UNIQUE INDEX IX_Sessions_TokenHash ON dbo.Sessions(Token_Hash);
END;

-- Optional: track schema versions for migrate.php
IF OBJECT_ID('dbo.SchemaVersions', 'U') IS NULL
BEGIN
  CREATE TABLE dbo.SchemaVersions (
    Id INT IDENTITY(1,1) PRIMARY KEY,
    ScriptName NVARCHAR(255) NOT NULL,
    AppliedAt  DATETIME2(0)  NOT NULL DEFAULT(SYSDATETIME())
  );
END;