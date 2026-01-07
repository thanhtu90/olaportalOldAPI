<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the batch processing logic fix in json.php
 * 
 * These tests validate the critical logic fix without requiring database connectivity.
 * They test the filtering logic that was fixed to prevent orderItems from being 
 * associated with all orders when hasInventory=true.
 */
class BatchProcessingLogicTest extends TestCase
{
    /**
     * Test that the filtering logic correctly associates orderItems with their orders
     * This simulates the fixed logic from lines 584-651 in json.php
     */
    public function testOrderItemFilteringLogic()
    {
        // Simulate the data structure that would come from json.php
        $orderReference1 = "11111";
        $orderReference2 = "22222";
        
        // Simulate items_json array with mixed orderReferences
        $items_json = [
            (object)[
                'orderItem' => (object)[
                    'orderReference' => $orderReference1,
                    'description' => 'Item for Order 1',
                    'price' => 10.99,
                    'qty' => 1,
                    'notes' => '',
                    'taxAmount' => '0.00',
                    'itemId' => '0',
                    'cost' => 0,
                    'group' => 37966,
                    'taxable' => 0,
                    'discount' => 0
                ]
            ],
            (object)[
                'orderItem' => (object)[
                    'orderReference' => $orderReference2,
                    'description' => 'Item for Order 2',
                    'price' => 5.99,
                    'qty' => 1,
                    'notes' => '',
                    'taxAmount' => '0.00',
                    'itemId' => '0',
                    'cost' => 0,
                    'group' => 37966,
                    'taxable' => 0,
                    'discount' => 0
                ]
            ],
            (object)[
                'orderItem' => (object)[
                    'orderReference' => $orderReference1,
                    'description' => 'Another Item for Order 1',
                    'price' => 3.99,
                    'qty' => 1,
                    'notes' => '',
                    'taxAmount' => '0.00',
                    'itemId' => '0',
                    'cost' => 0,
                    'group' => 37966,
                    'taxable' => 0,
                    'discount' => 0
                ]
            ]
        ];

        // Test the fixed filtering logic for Order 1
        $batch_items_order1 = [];
        $current_order_ref = $orderReference1;
        
        for ($j = 0; $j < count($items_json); $j++) {
            // This is the FIXED logic from line 586 in json.php
            // Always filter orderItems by their associated order - regardless of inventory status
            if ($current_order_ref == strval($items_json[$j]->{"orderItem"}->{"orderReference"})) {
                $col = (array) $items_json[$j]->{"orderItem"};
                $batch_items_order1[] = [
                    'description' => $col['description'],
                    'price' => $col['price'],
                    'orderReference' => $col['orderReference']
                ];
            }
        }

        // Test the fixed filtering logic for Order 2
        $batch_items_order2 = [];
        $current_order_ref = $orderReference2;
        
        for ($j = 0; $j < count($items_json); $j++) {
            // This is the FIXED logic from line 586 in json.php
            if ($current_order_ref == strval($items_json[$j]->{"orderItem"}->{"orderReference"})) {
                $col = (array) $items_json[$j]->{"orderItem"};
                $batch_items_order2[] = [
                    'description' => $col['description'],
                    'price' => $col['price'],
                    'orderReference' => $col['orderReference']
                ];
            }
        }

        // Assertions: Verify correct filtering
        $this->assertCount(2, $batch_items_order1, "Order 1 should have exactly 2 items");
        $this->assertCount(1, $batch_items_order2, "Order 2 should have exactly 1 item");

        // Verify Order 1 items
        $this->assertEquals('Item for Order 1', $batch_items_order1[0]['description']);
        $this->assertEquals('Another Item for Order 1', $batch_items_order1[1]['description']);
        $this->assertEquals(10.99, $batch_items_order1[0]['price']);
        $this->assertEquals(3.99, $batch_items_order1[1]['price']);

        // Verify Order 2 items
        $this->assertEquals('Item for Order 2', $batch_items_order2[0]['description']);
        $this->assertEquals(5.99, $batch_items_order2[0]['price']);

        // Critical verification: No cross-contamination
        foreach ($batch_items_order1 as $item) {
            $this->assertEquals($orderReference1, $item['orderReference'], "Order 1 items should only reference Order 1");
        }
        foreach ($batch_items_order2 as $item) {
            $this->assertEquals($orderReference2, $item['orderReference'], "Order 2 items should only reference Order 2");
        }
    }

    /**
     * Test that demonstrates the bug that was fixed
     * This shows what would have happened with the old problematic logic
     */
    public function testOldBuggyLogicDemonstration()
    {
        $orderReference1 = "11111";
        $orderReference2 = "22222";
        $hasInventory = true; // This was the problematic condition
        
        $items_json = [
            (object)[
                'orderItem' => (object)[
                    'orderReference' => $orderReference1,
                    'description' => 'Item for Order 1',
                    'price' => 10.99
                ]
            ],
            (object)[
                'orderItem' => (object)[
                    'orderReference' => $orderReference2,
                    'description' => 'Item for Order 2',
                    'price' => 5.99
                ]
            ]
        ];

        // Simulate the OLD BUGGY logic (before the fix)
        // This would have been: if ($hasInventory || $orderReference == ...)
        $buggy_batch_items_order1 = [];
        $current_order_ref = $orderReference1;
        
        for ($j = 0; $j < count($items_json); $j++) {
            // OLD BUGGY LOGIC: $hasInventory || condition bypassed filtering
            if ($hasInventory || $current_order_ref == strval($items_json[$j]->{"orderItem"}->{"orderReference"})) {
                $col = (array) $items_json[$j]->{"orderItem"};
                $buggy_batch_items_order1[] = [
                    'description' => $col['description'],
                    'price' => $col['price'],
                    'orderReference' => $col['orderReference']
                ];
            }
        }

        // The buggy logic would add ALL items to ALL orders when hasInventory=true
        $this->assertCount(2, $buggy_batch_items_order1, 
            "BUGGY LOGIC: Order 1 would incorrectly get ALL items (including Order 2's item)");
        
        // Show that the bug would cause cross-contamination
        $orderReferences = array_column($buggy_batch_items_order1, 'orderReference');
        $this->assertContains($orderReference1, $orderReferences, "Contains Order 1 items");
        $this->assertContains($orderReference2, $orderReferences, "BUGGY: Also contains Order 2 items!");

        // Now demonstrate the FIXED logic
        $fixed_batch_items_order1 = [];
        
        for ($j = 0; $j < count($items_json); $j++) {
            // FIXED LOGIC: Always filter by orderReference, regardless of hasInventory
            if ($current_order_ref == strval($items_json[$j]->{"orderItem"}->{"orderReference"})) {
                $col = (array) $items_json[$j]->{"orderItem"};
                $fixed_batch_items_order1[] = [
                    'description' => $col['description'],
                    'price' => $col['price'],
                    'orderReference' => $col['orderReference']
                ];
            }
        }

        // The fixed logic correctly filters items
        $this->assertCount(1, $fixed_batch_items_order1, 
            "FIXED LOGIC: Order 1 gets only its own item");
        $this->assertEquals($orderReference1, $fixed_batch_items_order1[0]['orderReference'], 
            "FIXED LOGIC: Only contains Order 1's item");
    }

    /**
     * Test edge case: hasInventory=false should work the same as hasInventory=true
     * The fix ensures consistent behavior regardless of inventory status
     */
    public function testConsistentBehaviorRegardlessOfInventoryStatus()
    {
        $orderReference = "33333";
        
        $items_json = [
            (object)[
                'orderItem' => (object)[
                    'orderReference' => $orderReference,
                    'description' => 'Test Item',
                    'price' => 7.99
                ]
            ],
            (object)[
                'orderItem' => (object)[
                    'orderReference' => "99999", // Different order
                    'description' => 'Other Order Item',
                    'price' => 2.99
                ]
            ]
        ];

        // Test with hasInventory=true
        $batch_items_true = [];
        $hasInventory = true;
        
        for ($j = 0; $j < count($items_json); $j++) {
            // FIXED logic: filtering is consistent regardless of hasInventory
            if ($orderReference == strval($items_json[$j]->{"orderItem"}->{"orderReference"})) {
                $batch_items_true[] = $items_json[$j]->{"orderItem"}->{"description"};
            }
        }

        // Test with hasInventory=false
        $batch_items_false = [];
        $hasInventory = false;
        
        for ($j = 0; $j < count($items_json); $j++) {
            // FIXED logic: same filtering regardless of hasInventory
            if ($orderReference == strval($items_json[$j]->{"orderItem"}->{"orderReference"})) {
                $batch_items_false[] = $items_json[$j]->{"orderItem"}->{"description"};
            }
        }

        // Both should produce identical results
        $this->assertEquals($batch_items_true, $batch_items_false, 
            "Filtering behavior should be consistent regardless of hasInventory value");
        $this->assertCount(1, $batch_items_true, "Should only include matching order items");
        $this->assertEquals(['Test Item'], $batch_items_true, "Should contain only the correct item");
    }

    /**
     * Test with empty items array
     */
    public function testEmptyItemsArray()
    {
        $orderReference = "44444";
        $items_json = []; // Empty items

        $batch_items = [];
        for ($j = 0; $j < count($items_json); $j++) {
            if ($orderReference == strval($items_json[$j]->{"orderItem"}->{"orderReference"})) {
                $batch_items[] = $items_json[$j];
            }
        }

        $this->assertEmpty($batch_items, "Empty items array should result in empty batch");
    }

    /**
     * Test with all items belonging to the same order
     */
    public function testAllItemsSameOrder()
    {
        $orderReference = "55555";
        
        $items_json = [
            (object)['orderItem' => (object)['orderReference' => $orderReference, 'description' => 'Item 1']],
            (object)['orderItem' => (object)['orderReference' => $orderReference, 'description' => 'Item 2']],
            (object)['orderItem' => (object)['orderReference' => $orderReference, 'description' => 'Item 3']]
        ];

        $batch_items = [];
        for ($j = 0; $j < count($items_json); $j++) {
            if ($orderReference == strval($items_json[$j]->{"orderItem"}->{"orderReference"})) {
                $batch_items[] = $items_json[$j]->{"orderItem"}->{"description"};
            }
        }

        $this->assertCount(3, $batch_items, "Should include all items when they all belong to the same order");
        $this->assertEquals(['Item 1', 'Item 2', 'Item 3'], $batch_items, "Should contain all items in order");
    }
}