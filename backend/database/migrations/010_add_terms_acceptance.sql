IF COL_LENGTH('dbo.Users', 'termsAccepted') IS NULL
BEGIN
  ALTER TABLE dbo.Users
  ADD termsAccepted BIT NOT NULL
      CONSTRAINT DF_Users_termsAccepted DEFAULT 0,
      termsAcceptedAt DATETIME NULL;
END;
GO