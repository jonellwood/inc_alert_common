import sys
import requests
import json

def reverse_geocode(lon, lat):
    """
    Reverse geocode the given longitude and latitude and return address details in JSON format.
    """
    if not lon or not lat:
        raise ValueError("Missing longitude or latitude")

    url = "https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/reverseGeocode"
    params = {
        "location": "{},{}".format(lon, lat),
        "f": "json"
    }

    res = requests.get(url, params=params)
    data = res.json()

    if 'address' in data:
        address_fields = data['address']
        street = address_fields.get('Address', 'N/A')
        city = address_fields.get('City', 'N/A').title()
        state = address_fields.get('Region', 'N/A').title()
        zip_code = address_fields.get('Postal', 'N/A')

        return json.dumps({
            "street": street,
            "city": city,
            "state": state,
            "zip": zip_code
        })
    else:
        return json.dumps({"error": "No address found."})

def get_address_from_xy(lat, lon):
    # Call the reverse_geocode function and return the result
    return reverse_geocode(lon, lat)

if __name__ == "__main__":
    if len(sys.argv) != 3:
        print(json.dumps({"error": "Latitude and longitude required"}))
        sys.exit(1)
    lat = sys.argv[1]
    lon = sys.argv[2]
    result = get_address_from_xy(lat, lon)
    print(result)