-- ============================================================
-- RedFive Relay — Users Table
-- Database: animalcontrol (shared database)
-- ============================================================
-- Separate table from existing application tables to allow
-- future database separation if the applications are split.
--
-- Supports both LDAP and local password authentication.
-- Local auth enables external vendor access and backdoor
-- admin accounts when LDAP is unavailable.
-- ============================================================

CREATE TABLE [dbo].[redfive_users] (
    [id]                  INT IDENTITY(1,1) PRIMARY KEY,
    [sUserName]           NVARCHAR(50) NOT NULL,
    [sDisplayName]        NVARCHAR(100) NULL,
    [sHashedPass]         NVARCHAR(255) NULL,         -- NULL for LDAP users; bcrypt hash for local users
    [bIsLDAP]             BIT NOT NULL DEFAULT 1,      -- 1 = authenticate via LDAP, 0 = local password
    [bIsActive]           BIT NOT NULL DEFAULT 1,      -- 1 = active, 0 = disabled
    [iAccess]             INT NOT NULL DEFAULT 1,      -- 0 = disabled, 1 = viewer, 2 = admin
    [dtLastLogin]         DATETIME2(3) NULL,
    [dtCreatedDateTime]   DATETIME2(3) NOT NULL DEFAULT (GETUTCDATE()),

    CONSTRAINT UQ_redfive_users_sUserName UNIQUE ([sUserName])
);

-- Index for login lookups
CREATE INDEX IX_redfive_users_username_active
    ON [dbo].[redfive_users] ([sUserName], [bIsActive]);

-- ============================================================
-- Example: Add an LDAP admin user (authenticates via AD)
-- ============================================================
-- INSERT INTO redfive_users (sUserName, sDisplayName, bIsLDAP, iAccess)
-- VALUES ('jellwood', 'Jonathan Ellwood', 1, 2);

-- ============================================================
-- Example: Add a local (non-LDAP) admin user (backdoor)
-- Generate hash first:  php auth/generate_hash.php
-- ============================================================
-- INSERT INTO redfive_users (sUserName, sDisplayName, sHashedPass, bIsLDAP, iAccess)
-- VALUES ('redfive_admin', 'RedFive Admin', '$2y$10$YOUR_GENERATED_HASH', 0, 2);

-- ============================================================
-- Example: Add an external vendor with viewer access
-- ============================================================
-- INSERT INTO redfive_users (sUserName, sDisplayName, sHashedPass, bIsLDAP, iAccess)
-- VALUES ('vendor_rapidsos', 'RapidSOS Support', '$2y$10$HASH_HERE', 0, 1);
