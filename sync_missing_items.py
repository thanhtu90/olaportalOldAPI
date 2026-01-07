#!/usr/bin/env python3
"""
Script to sync missing items by reading Orders_missing_item.csv
and calling the sync-existed-json API endpoint for each order.
"""

import csv
import json
import requests
import time
import sys
from typing import Dict, List, Optional, Tuple
from pathlib import Path

# API Configuration
API_URL = "https://portal.olapay.us/api/v1/signal/sync-existed-json"
ORDER_API_BASE = "https://portal.olapay.us/api/v1/orders/uuid"
TIMEOUT = 30  # seconds
DELAY_BETWEEN_REQUESTS = 0.5  # seconds to wait between API calls
RETRY_INITIAL_DELAY = 2  # seconds for initial retry delay
RETRY_MAX_ATTEMPTS = 3  # maximum retry attempts after sync

# Headers for API requests
HEADERS = {
    "Content-Type": "application/json",
    "Accept": "*/*"
}


def read_csv_file(file_path: str) -> List[Dict[str, str]]:
    """
    Read the CSV file and return a list of dictionaries.
    
    Args:
        file_path: Path to the CSV file
        
    Returns:
        List of dictionaries with keys: vendors_id, uuid, serial
    """
    orders = []
    
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            # CSV has quoted fields, so we need to handle that
            reader = csv.DictReader(f)
            
            for row_num, row in enumerate(reader, start=2):  # Start at 2 because row 1 is header
                # Clean up the values (remove quotes if present)
                vendors_id = row.get('vendors_id', '').strip().strip('"')
                uuid = row.get('uuid', '').strip().strip('"')
                serial = row.get('serial', '').strip().strip('"')
                
                # Skip rows with empty or NULL uuid
                if not uuid or uuid.upper() == 'NULL':
                    print(f"⚠️  Row {row_num}: Skipping row with empty/NULL uuid")
                    continue
                
                # Skip rows with empty vendors_id or serial
                if not vendors_id or not serial:
                    print(f"⚠️  Row {row_num}: Skipping row with missing vendors_id or serial")
                    continue
                
                orders.append({
                    'vendors_id': vendors_id,
                    'uuid': uuid,
                    'serial': serial,
                    'row_num': row_num
                })
    
    except FileNotFoundError:
        print(f"❌ Error: File '{file_path}' not found.")
        sys.exit(1)
    except Exception as e:
        print(f"❌ Error reading CSV file: {e}")
        sys.exit(1)
    
    return orders


def get_order_by_uuid(order_uuid: str) -> Tuple[bool, Optional[Dict], Optional[str]]:
    """
    Get order by UUID from the API.
    
    Args:
        order_uuid: The order UUID
        
    Returns:
        Tuple of (success: bool, order_data: Optional[Dict], error_message: Optional[str])
    """
    url = f"{ORDER_API_BASE}/{order_uuid}"
    
    try:
        response = requests.get(
            url,
            headers=HEADERS,
            timeout=TIMEOUT
        )
        
        if response.status_code == 200:
            try:
                order_data = response.json()
                return True, order_data, None
            except json.JSONDecodeError:
                return False, None, "Invalid JSON response"
        else:
            error_msg = f"HTTP {response.status_code}"
            try:
                error_data = response.json()
                if 'error' in error_data:
                    error_msg = error_data['error']
                elif 'message' in error_data:
                    error_msg = error_data['message']
            except:
                error_msg = f"HTTP {response.status_code}: {response.text[:200]}"
            
            return False, None, error_msg
    
    except requests.exceptions.Timeout:
        return False, None, "Request timeout"
    except requests.exceptions.ConnectionError:
        return False, None, "Connection error"
    except requests.exceptions.RequestException as e:
        return False, None, f"Request error: {str(e)}"
    except Exception as e:
        return False, None, f"Unexpected error: {str(e)}"


def check_order_has_items(order_data: Dict) -> bool:
    """
    Check if order has items.
    
    Args:
        order_data: The order data dictionary from API
        
    Returns:
        True if order has items, False otherwise
    """
    if not order_data:
        return False
    
    # Check for items in various possible locations
    # Common patterns: 'items', 'order_items', 'data.items', etc.
    if 'items' in order_data and order_data['items']:
        return len(order_data['items']) > 0
    
    if 'order_items' in order_data and order_data['order_items']:
        return len(order_data['order_items']) > 0
    
    if 'data' in order_data:
        data = order_data['data']
        if isinstance(data, dict):
            if 'items' in data and data['items']:
                return len(data['items']) > 0
            if 'order_items' in data and data['order_items']:
                return len(data['order_items']) > 0
    
    return False


def check_order_items_with_retry(order_uuid: str, max_attempts: int = RETRY_MAX_ATTEMPTS, 
                                   initial_delay: int = RETRY_INITIAL_DELAY) -> Tuple[bool, Optional[str], int]:
    """
    Check if order has items with exponential backoff retry.
    
    Args:
        order_uuid: The order UUID
        max_attempts: Maximum number of retry attempts
        initial_delay: Initial delay in seconds (will be doubled for each retry)
        
    Returns:
        Tuple of (has_items: bool, error_message: Optional[str], attempts_made: int)
    """
    delay = initial_delay
    
    for attempt in range(1, max_attempts + 1):
        success, order_data, error_msg = get_order_by_uuid(order_uuid)
        
        if success and order_data:
            has_items = check_order_has_items(order_data)
            if has_items:
                return True, None, attempt
            # If no items found, continue to retry
            if attempt < max_attempts:
                print(f"    ⏳ Attempt {attempt}: No items found, retrying in {delay}s...")
                time.sleep(delay)
                delay *= 2  # Exponential backoff
        else:
            # API error occurred
            if attempt < max_attempts:
                print(f"    ⚠️  Attempt {attempt}: {error_msg}, retrying in {delay}s...")
                time.sleep(delay)
                delay *= 2
            else:
                return False, error_msg, attempt
    
    # All attempts exhausted
    return False, f"No items found after {max_attempts} attempts", max_attempts


def call_sync_api(vendor_id: str, order_uuid: str, terminal_serial: str) -> Tuple[bool, Optional[str]]:
    """
    Call the sync-existed-json API endpoint.
    
    Args:
        vendor_id: The vendor ID
        order_uuid: The order UUID
        terminal_serial: The terminal serial number
        
    Returns:
        Tuple of (success: bool, error_message: Optional[str])
    """
    payload = {
        "vendor_id": vendor_id,
        "targeted_terminal_serial": terminal_serial,
        "order_uuids": [order_uuid]
    }
    
    try:
        response = requests.post(
            API_URL,
            headers=HEADERS,
            json=payload,
            timeout=TIMEOUT
        )
        
        if response.status_code == 200:
            return True, None
        else:
            error_msg = f"HTTP {response.status_code}"
            try:
                error_data = response.json()
                if 'error' in error_data:
                    error_msg = error_data['error']
                elif 'message' in error_data:
                    error_msg = error_data['message']
            except:
                error_msg = f"HTTP {response.status_code}: {response.text[:200]}"
            
            return False, error_msg
    
    except requests.exceptions.Timeout:
        return False, "Request timeout"
    except requests.exceptions.ConnectionError:
        return False, "Connection error"
    except requests.exceptions.RequestException as e:
        return False, f"Request error: {str(e)}"
    except Exception as e:
        return False, f"Unexpected error: {str(e)}"


def main():
    """Main function to process the CSV and sync items."""
    csv_file = "Orders_missing_item.csv"
    
    # Check if file exists
    if not Path(csv_file).exists():
        print(f"❌ Error: File '{csv_file}' not found in current directory.")
        sys.exit(1)
    
    print(f"📖 Reading CSV file: {csv_file}")
    orders = read_csv_file(csv_file)
    
    if not orders:
        print("⚠️  No valid orders found in CSV file.")
        sys.exit(0)
    
    print(f"✅ Found {len(orders)} valid orders to process\n")
    
    # Statistics
    success_count = 0
    error_count = 0
    items_fixed_count = 0
    items_still_missing_count = 0
    errors = []
    
    # Process each order
    for idx, order in enumerate(orders, 1):
        vendor_id = order['vendors_id']
        order_uuid = order['uuid']
        terminal_serial = order['serial']
        row_num = order['row_num']
        
        print(f"[{idx}/{len(orders)}] Processing row {row_num}: vendor_id={vendor_id}, uuid={order_uuid[:8]}..., serial={terminal_serial}")
        
        # Check order items BEFORE sync
        print("  📋 Checking order items BEFORE sync...")
        success_before, order_data_before, error_before = get_order_by_uuid(order_uuid)
        
        if success_before and order_data_before:
            has_items_before = check_order_has_items(order_data_before)
            if has_items_before:
                print("  ℹ️  Order already has items, skipping sync")
                success_count += 1
                items_fixed_count += 1
                if idx < len(orders):
                    time.sleep(DELAY_BETWEEN_REQUESTS)
                continue
            else:
                print("  ⚠️  Order has no items (as expected)")
        else:
            print(f"  ⚠️  Could not check order before sync: {error_before}")
        
        # Send sync signal
        print("  🔄 Sending sync signal...")
        success, error_msg = call_sync_api(vendor_id, order_uuid, terminal_serial)
        
        if not success:
            print(f"  ❌ Sync failed: {error_msg}")
            error_count += 1
            errors.append({
                'row': row_num,
                'vendor_id': vendor_id,
                'uuid': order_uuid,
                'serial': terminal_serial,
                'error': f"Sync failed: {error_msg}",
                'stage': 'sync'
            })
            if idx < len(orders):
                time.sleep(DELAY_BETWEEN_REQUESTS)
            continue
        
        print("  ✅ Sync signal sent successfully")
        
        # Check order items AFTER sync with retry
        print("  📋 Checking order items AFTER sync (with retry)...")
        has_items_after, error_after, attempts = check_order_items_with_retry(order_uuid)
        
        if has_items_after:
            print(f"  ✅ Items found after {attempts} attempt(s)")
            success_count += 1
            items_fixed_count += 1
        else:
            print(f"  ❌ Items still missing after {attempts} attempt(s): {error_after}")
            items_still_missing_count += 1
            errors.append({
                'row': row_num,
                'vendor_id': vendor_id,
                'uuid': order_uuid,
                'serial': terminal_serial,
                'error': f"Items still missing: {error_after}",
                'stage': 'verification',
                'attempts': attempts
            })
        
        # Add delay between requests to avoid overwhelming the server
        if idx < len(orders):
            time.sleep(DELAY_BETWEEN_REQUESTS)
    
    # Print summary
    print("\n" + "="*60)
    print("📊 SUMMARY")
    print("="*60)
    print(f"Total orders processed: {len(orders)}")
    print(f"✅ Successful (items found): {items_fixed_count}")
    print(f"❌ Sync failed: {error_count}")
    print(f"⚠️  Items still missing: {items_still_missing_count}")
    
    if errors:
        print(f"\n❌ Failed orders:")
        for error in errors[:10]:  # Show first 10 errors
            print(f"  Row {error['row']}: {error['error']}")
        if len(errors) > 10:
            print(f"  ... and {len(errors) - 10} more errors")
        
        # Optionally save errors to a file
        error_file = "sync_errors.json"
        with open(error_file, 'w') as f:
            json.dump(errors, f, indent=2)
        print(f"\n💾 Full error details saved to: {error_file}")


if __name__ == "__main__":
    main()

