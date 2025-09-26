-- Emergency Services Common Table
-- Supports data from 911HelpMe, RapidSOS, and other emergency service APIs
CREATE TABLE [dbo].[IncomingAlertData] (
    -- Primary Key & System Fields
    [id] UNIQUEIDENTIFIER NOT NULL DEFAULT (NEWSEQUENTIALID()) PRIMARY KEY,
    [dtCreatedDateTime] DATETIME2(3) NOT NULL DEFAULT (GETUTCDATE()),
    [dtUpdatedDateTime] DATETIME2(3) NULL,
    [sSourceSystem] NVARCHAR(50) NOT NULL, -- '911HelpMe', 'RapidSOS', etc.
    [sSourceId] NVARCHAR(100) NULL, -- Original ID from source system
    [sSourceReferenceNumber] NVARCHAR(100) NULL, -- HM2025-00000, permit numbers, etc.
    
    -- Contact Information
    [sContactFirstName] NVARCHAR(100) NULL,
    [sContactLastName] NVARCHAR(100) NULL,
    [sContactFullName] NVARCHAR(200) NULL,
    [sContactPhone] NVARCHAR(20) NULL,
    [sContactEmail] NVARCHAR(255) NULL,
    [sContactRelationship] NVARCHAR(50) NULL, -- For emergency contacts
    [sContactLanguage] NVARCHAR(50) NULL,
    
    -- Location Information
    [sStreetAddress] NVARCHAR(500) NULL,
    [sApartmentNumber] NVARCHAR(20) NULL,
    [sCity] NVARCHAR(100) NULL,
    [sState] NVARCHAR(50) NULL,
    [sCountry] NVARCHAR(50) NULL,
    [iZipCode] NVARCHAR(20) NULL,
    [sFullAddress] NVARCHAR(1000) NULL, -- Concatenated full address
    [sCrossStreet] NVARCHAR(500) NULL,
    
    -- Geographic Coordinates
    [iLatitude] DECIMAL(10, 8) NULL,
    [iLongitude] DECIMAL(11, 8) NULL,
    [sLocationUncertainty] NVARCHAR(50) NULL, -- Uncertainty in meters
    [sLocationName] NVARCHAR(200) NULL, -- Building/site name
    
    -- Emergency Details
    [sEmergencyType] NVARCHAR(100) NULL, -- FIRE, BURGLARY, ACTIVE_ASSAILANT, OTHER
    [sCallType] NVARCHAR(100) NULL, -- From 911HelpMe
    [sSiteType] NVARCHAR(50) NULL, -- RESIDENTIAL, COMMERCIAL, PERSONAL
    [sAgency] NVARCHAR(100) NULL, -- Animal Control, Fire, Police, etc.
    [sStatus] NVARCHAR(50) NULL, -- ACTIVE, CLOSED, etc.
    
    -- Incident Information
    [sAlarmDescription] NVARCHAR(500) NULL,
    [sZoneDescription] NVARCHAR(200) NULL,
    [sDescription] NVARCHAR(1000) NULL,
    [sComments] NVARCHAR(2000) NULL,
    [sRemarks] NVARCHAR(2000) NULL,
    [sInstructions] NVARCHAR(1000) NULL,
    
    -- Service Provider Information
    [sServiceProviderName] NVARCHAR(200) NULL,
    [sServiceProviderPhone] NVARCHAR(20) NULL,
    [sCentralStationPhone] NVARCHAR(20) NULL,
    [sPremisePhone] NVARCHAR(20) NULL,
    [sSitePhone] NVARCHAR(20) NULL,
    
    -- Timing Information (stored as received from source)
    [sIncidentTimeRaw] NVARCHAR(100) NULL, -- Store exactly as received
    [sSubmittedTimeRaw] NVARCHAR(100) NULL, -- Store exactly as received
    [sClearedTimeRaw] NVARCHAR(100) NULL, -- Store exactly as received
    
    -- Permit and Access Information
    [sPermitNumber] NVARCHAR(100) NULL,
    [sAlarmPermitNumber] NVARCHAR(100) NULL,
    [sLockboxCode] NVARCHAR(100) NULL,
    [sGateCode] NVARCHAR(100) NULL,
    [sHiddenKey] NVARCHAR(200) NULL,
    [sAccessInstructions] NVARCHAR(500) NULL,
    
    -- Additional Details
    [sIsAudible] NVARCHAR(20) NULL, -- Silent, Audible
    [sVisuallyVerified] NVARCHAR(20) NULL,
    [sVialOfLife] NVARCHAR(200) NULL,
    [sAccountOwner] NVARCHAR(200) NULL,
    [sBuildingId] NVARCHAR(100) NULL,
    [sBuildingName] NVARCHAR(200) NULL,
    
    -- Communication Preferences
    [sSpeakWithFirstResponder] NVARCHAR(100) NULL,
    [bContactPermission] BIT NULL,
    [bTextMessage] BIT NULL,
    
    -- Vehicle Information (for personal emergencies)
    [sVehicleMake] NVARCHAR(50) NULL,
    [sVehicleModel] NVARCHAR(50) NULL,
    [sVehicleColor] NVARCHAR(50) NULL,
    [sVehiclePlateNumber] NVARCHAR(20) NULL,
    [sVehiclePlateState] NVARCHAR(10) NULL,
    
    -- Technical Information
    [sClientIp] NVARCHAR(100) NULL,
    [sSourceEventCode] NVARCHAR(50) NULL,
    [sTransmitterId] NVARCHAR(100) NULL,
    [sTransmitterType] NVARCHAR(100) NULL,
    [sFlowData] NVARCHAR(MAX) NULL, -- Store complex flow_data as JSON when needed
    
    -- Flags and Status
    [bHasAttachment] BIT NULL DEFAULT (0),
    [bIsDeleted] BIT NULL DEFAULT (0),
    [dtDeletedDateTime] DATETIME2(3) NULL,
    
    -- Emergency Contacts (JSON array for multiple contacts)
    [sEmergencyContactsJson] NVARCHAR(MAX) NULL,
    
    -- Original Payload (for debugging and data integrity)
    [sOriginalPayloadJson] NVARCHAR(MAX) NULL
);

-- Create indexes for common query patterns
CREATE INDEX [IX_IncomingAlertData_SourceSystem] ON [dbo].[IncomingAlertData] ([sSourceSystem]);
CREATE INDEX [IX_IncomingAlertData_CreatedDateTime] ON [dbo].[IncomingAlertData] ([dtCreatedDateTime]);
CREATE INDEX [IX_IncomingAlertData_Status] ON [dbo].[IncomingAlertData] ([sStatus]);
CREATE INDEX [IX_IncomingAlertData_EmergencyType] ON [dbo].[IncomingAlertData] ([sEmergencyType]);
CREATE INDEX [IX_IncomingAlertData_Agency] ON [dbo].[IncomingAlertData] ([sAgency]);
CREATE INDEX [IX_IncomingAlertData_ContactPhone] ON [dbo].[IncomingAlertData] ([sContactPhone]);
CREATE INDEX [IX_IncomingAlertData_SourceReferenceNumber] ON [dbo].[IncomingAlertData] ([sSourceReferenceNumber]);
CREATE INDEX [IX_IncomingAlertData_Location] ON [dbo].[IncomingAlertData] ([iLatitude], [iLongitude]);

-- Add check constraints for data validation
-- ALTER TABLE [dbo].[IncomingAlertData] 
-- ADD CONSTRAINT [CK_IncomingAlertData_SourceSystem] 
-- CHECK ([SourceSystem] IN ('911HelpMe', 'RapidSOS', 'Other'));

-- Comments for documentation
EXEC sp_addextendedproperty 
    @name = N'MS_Description',
    @value = N'Unified table for emergency service calls from multiple API sources (911HelpMe, RapidSOS, etc.)',
    @level0type = N'SCHEMA', @level0name = N'dbo',
    @level1type = N'TABLE', @level1name = N'IncomingAlertData';


-- ADDED THESE FIELDS AFTER INITIAL DEPLOYMENT
ALTER TABLE [dbo].[IncomingAlertData]
ADD [sCfsNumber] NVARCHAR(50) NULL,
[sCadStatus] NVARCHAR(20) NOT NULL DEFAULT ('PENDING'), -- PENDING, POSTED, FAILED
[dtCadPostedDateTime] DATETIME2(3) NULL,
[sCadErrorMessage] NVARCHAR(1000) NULL,
[iRetryCount] INT NOT NULL DEFAULT (0)

-- Indexes for new fields
CREATE INDEX [IX_IncomingAlertData_sCadStatus] ON [dbo].[IncomingAlertData] ([sCadStatus]);
CREATE INDEX [IX_IncomingAlertData_sCfsNumber] ON [dbo].[IncomingAlertData] ([sCfsNumber]);
CREATE INDEX [IX_IncomingAlertData_dtCadPostedDateTime] ON [dbo].[IncomingAlertData] ([dtCadPostedDateTime]);
CREATE INDEX [IX_IncomingAlertData_RetryCount] ON [dbo].[IncomingAlertData] ([iRetryCount]);
-- Comments for new fields
EXEC sp_addextendedproperty 
    @name = N'MS_Description',
    @value = N'CFS number assigned by CAD system after successful post',
    @level0type = N'SCHEMA', @level0name = N'dbo',
    @level1type = N'TABLE', @level1name = N'IncomingAlertData',
    @level2type = N'COLUMN', @level2name = N'sCfsNumber';    