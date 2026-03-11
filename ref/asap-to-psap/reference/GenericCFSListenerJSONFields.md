# Southern Software CIM - Generic CFS Listener Fields

This document describes the JSON fields used by the Generic CFS (Call for Service) Listener and their definitions.

---

## Field Definitions

| Field Name           | Data Type  | Length | Required | Description                                                                                   |
|----------------------|------------|--------|----------|-----------------------------------------------------------------------------------------------|
| **CFSNumber**        | `nvarchar` | 25     | 0        | Only required if updates are allowed in the future.                                          |
| **InterfaceRecordID** | `nvarchar` | 50     | 0        | ID from the sending system to be logged with the CFS for future reference and use.           |
| **CFSStartWhen**     | `datetime` | 8      | 0        | Defaults to save time if not supplied. In local time zone for the agency.                    |
| **IncAptLoc**        | `nvarchar` | 60     | 0        | Apartment, suite, or fractional part of the street number for the incident location.         |
| **IncCommunity**     | `nvarchar` | 100    | 0        | Incident location community.                                                                 |
| **IncIntersection**  | `nvarchar` | 255    | 0        | Primary and secondary street names (including pre-directionals) for the occurrence location.|
| **IncPreDir**        | `nvarchar` | 2      | 0        | Incident pre-directional: E, N, NE, NW, S, SE, SW, W.                                        |
| **IncStreetName**    | `nvarchar` | 100    | 0        | Incident street name, type, and post-directional: E, N, NE, NW, S, SE, SW, W.               |
| **IncStreetNum**     | `int`      | 4      | 0        | Incident street number.                                                                      |
| **CallerAptLoc**     | `nvarchar` | 60     | 0        | Apartment, suite, or fractional part of the street number for the caller location.          |
| **CallerCommunity**  | `nvarchar` | 100    | 0        | Community for the caller location.                                                           |
| **CallerPreDir**     | `nvarchar` | 2      | 0        | Caller pre-directional: E, N, NE, NW, S, SE, SW, W.                                          |
| **CallerStreetName** | `nvarchar` | 100    | 0        | Caller street name, type, and post-directional: E, N, NE, NW, S, SE, SW, W.                 |
| **CallerStreetNum**  | `int`      | 4      | 0        | Caller street number.                                                                        |
| **CallerName**       | `nvarchar` | 50     | 0        | Name of the caller in format: L, F M or F M L.                                               |
| **CallerPhone**      | `varchar`  | 15     | 0        | Caller phone number (no punctuation).                                                        |
| **CallTypeAlias**    | `nvarchar` | 50     | 1        | Links first to Alias, then CallType, and lastly CallTypeID.                                  |
| **InProgress**       | `bit`      | 1      | 0        | Indicates if the CFS is currently an active situation.                                       |
| **AlarmLevel**       | `tinyint`  | 1      | 0        | Should match the values in your configuration. Can be left blank to default for CallType.    |
| **PriorityAlias**    | `nvarchar` | 50     | 0        | Should match the values in your configuration. Can be left blank to default for CallType.    |
| **HowReceived**      | `nvarchar` | 50     | 0        | Should match the values in your configuration. Can be left blank to default for the interface.|
| **XCoor**            | `float`    | 8      | 0        | Longitude in decimal degrees (WGS84).                                                        |
| **YCoor**            | `float`    | 8      | 0        | Latitude in decimal degrees (WGS84).                                                         |
| **Comment**          | `nvarchar` | 255    | 0        | Caller comment.                                                                              |
| **CFSNote**          | `nvarchar` | max    | 0        | Initial note longer than 255 characters.                                                    |
| **CreateBy**         | `nvarchar` | 50     | 0        | Defaults to the `IF-InterfaceInitID` if not supplied.                                        |

---

**Document last updated:** `12/14/2022 15:27`
