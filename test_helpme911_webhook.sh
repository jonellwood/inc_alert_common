#!/bin/bash
# Test HelpMe911 webhook with sample data

WEBHOOK_URL="https://my.berkeleycountysc.gov/redfive/webhooks/helpme911_webhook.php"

echo "Testing HelpMe911 webhook endpoint..."
echo "URL: $WEBHOOK_URL"
echo ""

# Use the sample data from helpme911Sample.json
curl -X POST "$WEBHOOK_URL" \
  -H "Content-Type: application/json" \
  -d '{
  "id": "558b9601-f8f7-4800-a5c1-3a6263666618",
  "updated": null,
  "created": 1749469732281,
  "createdBy": "alastar.support",
  "deleted": null,
  "isDeleted": false,
  "updatedBy": null,
  "hasAttachment": false,
  "agency": "Animal Control",
  "apartmentNumber": "",
  "callType": "Other",
  "city": "Summerville",
  "clearedTime": null,
  "clientIp": "104.30.177.75, 108.162.238.77",
  "comments": "",
  "contactEmail": "",
  "contactFirstName": "Test",
  "contactLastName": "User",
  "contactPermission": false,
  "contactPhone": "8435551234",
  "latitude": 33.110504012141,
  "longitude": -80.105917966043,
  "referenceNumber": "HM2025-00000",
  "remarks": "TEST ANIMAL CONTROL CALL",
  "state": "South Carolina",
  "status": "ACTIVE",
  "speakWithFirstResponder": "Yes, I want to speak with the first responder on the phone",
  "streetAddress": "318 Decatur Dr",
  "submitted": 1749469732281,
  "textMessage": false,
  "geoPoint": {
    "address": null,
    "degreesLatitude": null,
    "degressLongitude": null,
    "latitude": 33.110504012141,
    "longitude": -80.105917966043,
    "minutesLatitude": null,
    "minutesLongitude": null,
    "readOnly": false,
    "secondsLatitude": null,
    "secondsLongitude": null,
    "selectedType": "DECIMAL",
    "supportDMS": false
  },
  "fullName": "Test User",
  "fullGridAddress": "318 Decatur Dr Summerville South Carolina",
  "fullAddress": "318 Decatur Dr Summerville South Carolina"
}'

echo ""
echo ""
echo "Check logs at:"
echo "  /var/www/myberkeley/redfive/logs/helpme911_webhook_debug.log"
