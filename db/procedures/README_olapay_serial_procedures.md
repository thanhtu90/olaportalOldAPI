# OlaPay Serial Deletion Procedures

Delete `jsonOlaPay` and `unique_olapay_transactions` records by terminal serial number with optional date range.

## Procedures

### 1. `preview_olapay_by_serial(serial, lastmod_from, lastmod_to)`
Preview-only procedure that shows what would be deleted. **NEVER deletes data.**

### 2. `delete_olapay_by_serial(serial, lastmod_from, lastmod_to, confirm)`
Deletes records with explicit confirmation required.

## Installation

```sql
SOURCE db/procedures/preview_olapay_by_serial.sql;
SOURCE db/procedures/delete_olapay_by_serial.sql;
```

## Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `p_serial` | VARCHAR(255) | Yes | Terminal serial number |
| `p_lastmod_from` | BIGINT | No | Start timestamp (NULL = no lower bound) |
| `p_lastmod_to` | BIGINT | No | End timestamp (NULL = no upper bound) |
| `p_confirm` | VARCHAR(10) | No | 'YES' to confirm deletion (delete procedure only) |

> ⚠️ **NOTE**: If BOTH `p_lastmod_from` AND `p_lastmod_to` are NULL, **ALL records** for that serial will be deleted!

## Usage Examples

### Preview all records for a serial
```sql
-- Preview all records for serial 'ABC123'
CALL preview_olapay_by_serial('ABC123', NULL, NULL);
```

### Preview with date range
```sql
-- Preview records between specific timestamps
CALL preview_olapay_by_serial('ABC123', 1703980800, 1704067200);

-- Using date conversion
CALL preview_olapay_by_serial('ABC123', 
    UNIX_TIMESTAMP('2024-12-31 00:00:00'), 
    UNIX_TIMESTAMP('2024-12-31 23:59:59'));
```

### Delete ALL records for a serial (no date filter)
```sql
-- Step 1: Preview first (shows ALL records that will be deleted)
CALL delete_olapay_by_serial('ABC123', NULL, NULL, 'NO');

-- Step 2: Confirm deletion of ALL records
CALL delete_olapay_by_serial('ABC123', NULL, NULL, 'YES');
```

> ⚠️ **WARNING**: When both date parameters are NULL, ALL records for the serial are deleted!

### Delete with date range
```sql
-- Delete records from start date to end date
CALL delete_olapay_by_serial('ABC123', 
    UNIX_TIMESTAMP('2025-01-01 00:00:00'), 
    UNIX_TIMESTAMP('2025-01-31 23:59:59'),
    'YES');
```

### Delete from a specific date onwards (no end date)
```sql
-- Delete all records from 2025-01-01 onwards
CALL delete_olapay_by_serial('ABC123', 
    UNIX_TIMESTAMP('2025-01-01 00:00:00'), 
    NULL,
    'YES');
```

### Delete up to a specific date (no start date)
```sql
-- Delete all records up to 2024-12-31
CALL delete_olapay_by_serial('ABC123', 
    NULL, 
    UNIX_TIMESTAMP('2024-12-31 23:59:59'),
    'YES');
```

## What Gets Deleted

| Table | Criteria |
|-------|----------|
| `jsonOlaPay` | `serial = ? AND lastmod >= ? AND lastmod <= ?` |
| `unique_olapay_transactions` | `serial = ? AND lastmod >= ? AND lastmod <= ?` |

## Example Output

### Preview Output
```
status: PREVIEW
serial: ABC123
lastmod_from: 1703980800
lastmod_to: 1704067200
lastmod_from_readable: 2024-12-31 00:00:00
lastmod_to_readable: 2024-12-31 23:59:59
jsonOlaPay_records: 150
jsonOlaPay_earliest: 2024-12-31 08:15:22
jsonOlaPay_latest: 2024-12-31 22:45:11
unique_olapay_records: 145
unique_olapay_earliest: 2024-12-31 08:15:22
unique_olapay_latest: 2024-12-31 22:45:11
total_records: 295
message: This is a preview. No data will be deleted.
delete_command: CALL delete_olapay_by_serial('ABC123', 1703980800, 1704067200, 'YES');
```

### Success Output
```
status: SUCCESS
serial: ABC123
lastmod_from: 1703980800
lastmod_to: 1704067200
lastmod_from_readable: 2024-12-31 00:00:00
lastmod_to_readable: 2024-12-31 23:59:59
jsonOlaPay_deleted: 150
unique_olapay_deleted: 145
total_records_deleted: 295
message: OlaPay records deleted successfully
```

## Timestamp Conversion Helpers

```sql
-- Convert date to timestamp
SELECT UNIX_TIMESTAMP('2025-01-15 00:00:00') as start_timestamp;
SELECT UNIX_TIMESTAMP('2025-01-15 23:59:59') as end_timestamp;

-- Convert timestamp to date
SELECT FROM_UNIXTIME(1705276800) as readable_date;
```

## Safety Features

- ✅ **Preview mode by default** - must explicitly confirm with 'YES'
- ✅ **Input validation** - serial number required
- ✅ **Transaction-based** - atomic delete, rollback on error
- ✅ **Detailed reporting** - shows exactly what was/will be deleted
- ✅ **Date range support** - optional start AND/OR end timestamp

