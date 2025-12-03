# RapidSOS to Southern Software CAD Field Mapping

This document maps fields from the RapidSOS webhook payload to the Southern Software Generic CFS Listener API.

## Data Flow

```
RapidSOS Webhook → rapidsos_webhook.php → writeToDB.php → Southern Software CAD API
```

## Field Mappings

### Core Identification Fields

| RapidSOS Field (body.*) | Intermediate Field | Southern Software Field | Notes |
|------------------------|-------------------|------------------------|-------|
| `alert_id` | `rapidsos_alert_id` | `InterfaceRecordID` | Unique alert identifier |
| `source_id` | - | Logged in `CFSNote` | External system ID |
| `incident_time` | `timestamp` | `CFSStartWhen` | Unix timestamp → datetime |

### Location Fields (Geodetic)

| RapidSOS Field | Intermediate Field | Southern Software Field | Notes |
|----------------|-------------------|------------------------|-------|
| `location.geodetic.latitude` | `location.latitude` | `YCoor` | Decimal degrees (WGS84) |
| `location.geodetic.longitude` | `location.longitude` | `XCoor` | Decimal degrees (WGS84) |
| `location.geodetic.uncertainty_radius` | `location.accuracy` | - | Meters |

### Location Fields (Civic Address)

| RapidSOS Field | Intermediate Field | Southern Software Field | Notes |
|----------------|-------------------|------------------------|-------|
| `location.civic.street_1` | `location.address.street_name` | `IncStreetNum` + `IncStreetName` | Parsed into number and name |
| `location.civic.street_2` | `location.address.street_2` | `IncAptLoc` | Apartment/suite |
| `location.civic.city` | `location.address.city` | `IncCommunity` | City name |
| `location.civic.state` | `location.address.state` | - | State abbreviation |
| `location.civic.zip_code` | `location.address.postal_code` | - | Postal code |
| `location.civic.country` | `location.address.country` | - | Country code |
| `location.civic.name` | `location.address.formatted` | - | Location name |

### Emergency Details

| RapidSOS Field | Intermediate Field | Southern Software Field | Notes |
|----------------|-------------------|------------------------|-------|
| `emergency_type.name` | `emergency.type` | `CallTypeAlias` | Maps to call type |
| `emergency_type.display_name` | `emergency.display_name` | - | Human-readable type |
| `description` | `emergency.description` | `Comment` | Short description |
| `description` | `emergency.description` | `CFSNote` | Included in detailed notes |

### Service Provider & Site Information

| RapidSOS Field | Intermediate Field | Southern Software Field | Notes |
|----------------|-------------------|------------------------|-------|
| `service_provider_name` | `service_provider` | `CFSNote` | Included in notes |
| `site_type.name` | `site_type.name` | `CFSNote` | COMMERCIAL, RESIDENTIAL, etc. |
| `site_type.display_name` | `site_type.display_name` | `CFSNote` | Human-readable |

### Status & Disposition

| RapidSOS Field | Intermediate Field | Southern Software Field | Notes |
|----------------|-------------------|------------------------|-------|
| `status.name` | `status.name` | - | DISPATCH_REQUESTED, etc. |
| `status.display_name` | `status.display_name` | - | Human-readable status |

### PSAP & Entity Information

| RapidSOS Field | Intermediate Field | Southern Software Field | Notes |
|----------------|-------------------|------------------------|-------|
| `covering_psap.id` | `covering_psap.id` | `CFSNote` | PSAP identifier |
| `covering_psap.name` | `covering_psap.name` | `CFSNote` | PSAP name |
| `covering_psap.phone` | `covering_psap.phone` | `CFSNote` | PSAP contact |
| `authorized_entity.id` | `authorized_entity.id` | `CFSNote` | Entity identifier |
| `authorized_entity.name` | `authorized_entity.name` | `CFSNote` | Entity name |
| `authorized_entity.phone` | `authorized_entity.phone` | `CFSNote` | Entity contact |

### Timestamps

| RapidSOS Field | Intermediate Field | Southern Software Field | Notes |
|----------------|-------------------|------------------------|-------|
| `created_time` | `created_time` | - | Unix milliseconds → datetime |
| `incident_time` | `timestamp` | `CFSStartWhen` | Preferred timestamp |
| `dispatch_requested_time` | `dispatch_requested_time` | - | When dispatch was requested |
| `last_updated_time` | `last_updated_time` | - | Last update timestamp |
| `sla_expiration_time` | `sla_expiration_time` | - | SLA deadline |

### Additional Metadata

| RapidSOS Field | Intermediate Field | Southern Software Field | Notes |
|----------------|-------------------|------------------------|-------|
| `is_chat_enabled` | `is_chat_enabled` | - | Boolean flag |
| `is_media_enabled` | `is_media_enabled` | - | Boolean flag |
| `supplemental_only` | `supplemental_only` | - | Boolean flag |
| `followers` | `followers` | - | Array of follower IDs |
| `location_history` | `location_history` | - | Array of location updates |

## Emergency Type Mapping

RapidSOS emergency types need to be mapped to Southern Software call types:

| RapidSOS Type | Southern Software CallTypeAlias | Notes |
|---------------|--------------------------------|-------|
| `FIRE` | TBD | Fire alarm/emergency |
| `BURGLARY` | `104 ALARMS - LAW` | Currently default |
| `MEDICAL` | TBD | Medical emergency |
| `PANIC` | TBD | Panic alarm |
| *Others* | `104 ALARMS - LAW` | Default fallback |

## Data Transformations

### Timestamp Conversion

RapidSOS timestamps are Unix milliseconds. Convert to datetime:

```php
$datetime = date('Y-m-d H:i:s', $timestamp / 1000);
```

### Street Address Parsing

RapidSOS provides `street_1` as full street address. Parse into components:

- Extract street number → `IncStreetNum`
- Extract pre-directional (N, S, E, W, etc.) → `IncPreDir`
- Extract street name + type + post-directional → `IncStreetName`

### Coordinates

- RapidSOS uses `latitude`/`longitude` (standard)
- Southern Software uses `YCoor`/`XCoor` (latitude/longitude)
- Both use WGS84 decimal degrees

## CFSNote Format

The `CFSNote` field contains detailed information:

```
Source System: RapidSOS
Emergency Type: FIRE
Service Provider: Honeywell
Site Type: Commercial
Original Description: FIRE ALARM
GPS Coordinates: 32.921242, -80.014879
Map Link: [OpenStreetMap URL]
PSAP: Berkeley_Alerts (ID_Berkeley)
Authorized Entity: NOC-CSP (noc_csp)
```

## Required vs Optional Fields

### Required by Southern Software

- `CallTypeAlias` - Must map emergency type to valid call type
- At minimum one of:
  - Address fields (IncStreetNum, IncStreetName, IncCommunity)
  - OR Coordinates (XCoor, YCoor)

### Recommended

- `InterfaceRecordID` - For tracking
- `Comment` - Brief description
- `CFSNote` - Detailed information
- `CFSStartWhen` - Incident timestamp

### Optional

- Caller information (not typically provided by alarm companies)
- Apartment/location details
- Custom fields

## Notes

- The webhook format uses `event_type` + `body` wrapper
- Legacy formats may not have the `body` wrapper
- All timestamp fields in RapidSOS are Unix milliseconds
- Southern Software expects local timezone datetimes
