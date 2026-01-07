#!/usr/bin/env python3
"""
Script to convert sample-content.json to a payload format accepted by json.php API
"""

import json
import os

def create_api_payload(input_file, serial, output_file=None, compact=True):
    """
    Convert JSON file to API payload format for json.php
    
    Args:
        input_file (str): Path to the input JSON file
        serial (str): Serial number for the payload
        output_file (str, optional): Path to save the output. If None, prints to console
        compact (bool): Whether to use compact JSON format
    """
    try:
        # Check if input file exists
        if not os.path.exists(input_file):
            print(f"Error: File '{input_file}' not found")
            return
        
        # Read and parse the JSON file
        with open(input_file, 'r', encoding='utf-8') as f:
            data = json.load(f)
        
        # Convert to JSON string (compact for API payload)
        json_string = json.dumps(data, separators=(',', ':'), ensure_ascii=False)
        
        # Create API payload
        api_payload = {
            "serial": serial,
            "json": json_string
        }
        
        # Format the payload
        if compact:
            payload_json = json.dumps(api_payload, separators=(',', ':'), ensure_ascii=False)
        else:
            payload_json = json.dumps(api_payload, indent=2, ensure_ascii=False)
        
        if output_file:
            # Save to output file
            with open(output_file, 'w', encoding='utf-8') as f:
                f.write(payload_json)
            print(f"API payload saved to '{output_file}'")
        else:
            # Print to console
            print("API Payload for json.php:")
            print(payload_json)
        
        return payload_json
        
    except json.JSONDecodeError as e:
        print(f"Error: Invalid JSON format in '{input_file}': {e}")
    except Exception as e:
        print(f"Error: {e}")

def convert_json_to_string(input_file, output_file=None, indent=2):
    """
    Convert JSON file to a formatted JSON string (legacy function)
    
    Args:
        input_file (str): Path to the input JSON file
        output_file (str, optional): Path to save the output. If None, prints to console
        indent (int): Indentation level for formatting
    """
    try:
        # Check if input file exists
        if not os.path.exists(input_file):
            print(f"Error: File '{input_file}' not found")
            return
        
        # Read and parse the JSON file
        with open(input_file, 'r', encoding='utf-8') as f:
            data = json.load(f)
        
        # Convert to formatted JSON string
        json_string = json.dumps(data, indent=indent, ensure_ascii=False)
        
        if output_file:
            # Save to output file
            with open(output_file, 'w', encoding='utf-8') as f:
                f.write(json_string)
            print(f"JSON string saved to '{output_file}'")
        else:
            # Print to console
            print("Formatted JSON string:")
            print(json_string)
        
        return json_string
        
    except json.JSONDecodeError as e:
        print(f"Error: Invalid JSON format in '{input_file}': {e}")
    except Exception as e:
        print(f"Error: {e}")

def main():
    """Main function"""
    input_file = "sample-content.json"
    serial = "BTSB12P3120400048"
    
    # Primary function: Create API payload for json.php
    print("Creating API payload for json.php...")
    print("="*60)
    create_api_payload(input_file, serial)
    
    print("\n" + "="*60 + "\n")
    
    # Save API payload to file
    api_payload_file = "api_payload.json"
    print(f"Saving API payload to '{api_payload_file}'...")
    create_api_payload(input_file, serial, api_payload_file, compact=False)
    
    # Save compact version for actual API calls
    compact_payload_file = "api_payload_compact.json"
    print(f"Saving compact API payload to '{compact_payload_file}'...")
    create_api_payload(input_file, serial, compact_payload_file, compact=True)
    
    print("\n" + "="*60 + "\n")
    
    # Legacy: Show original JSON formatting
    print("Original JSON content (formatted):")
    convert_json_to_string(input_file)

def test_api_call(api_url="http://localhost/json.php", payload_file="api_payload_compact.json"):
    """
    Test function to demonstrate how to make API call with the generated payload
    
    Args:
        api_url (str): URL of the json.php API endpoint
        payload_file (str): Path to the compact payload file
    """
    try:
        import requests
        
        # Read the compact payload
        with open(payload_file, 'r', encoding='utf-8') as f:
            payload = f.read()
        
        print(f"Making API call to: {api_url}")
        print("Payload preview (first 100 chars):")
        print(payload[:100] + "..." if len(payload) > 100 else payload)
        
        # Note: Uncomment below lines to actually make the API call
        # headers = {'Content-Type': 'application/json'}
        # response = requests.post(api_url, data=payload, headers=headers)
        # print(f"Response status: {response.status_code}")
        # print(f"Response: {response.text}")
        
        print("\nTo make the actual API call, uncomment the requests code in test_api_call function")
        
    except ImportError:
        print("requests library not installed. Install with: pip install requests")
    except Exception as e:
        print(f"Error in test function: {e}")

if __name__ == "__main__":
    main()
    
    print("\n" + "="*60 + "\n")
    print("Test API call function (demonstration only):")
    test_api_call()