-- ============================================================
-- SQL INSERT statements for order items
-- Order UUID: 7bfbd9d3-e87a-47ab-8f03-5bab56d28911
-- Order Reference: 4053
-- ============================================================
--
-- INSTRUCTIONS:
-- 1. Replace @ORDERS_ID with the actual orders.id from the orders table
--    (Find it using: SELECT id FROM orders WHERE uuid = '7bfbd9d3-e87a-47ab-8f03-5bab56d28911' LIMIT 1;)
-- 2. Replace @AGENTS_ID, @VENDORS_ID, @TERMINALS_ID with actual values
--    (Find them using: SELECT agents_id, vendors_id, terminals_id FROM orders WHERE uuid = '7bfbd9d3-e87a-47ab-8f03-5bab56d28911' LIMIT 1;)
-- 3. For modifiers, replace @PARENT_ITEM_ID_X with the actual ID of the parent item
--    (The parent item ID will be returned after inserting the main item)
--
-- ============================================================

-- ============================================================
-- Main Item 1: can soda
-- Item UUID: 043ba71d-8942-4c0c-ae88-d545f7c25d33
-- ============================================================
INSERT INTO orderItems (
    itemUuid, orderUuid, agents_id, vendors_id, terminals_id,
    group_name, orders_id, cost, description, group_id,
    notes, price, taxable, qty, items_id, discount, orderReference,
    taxamount, itemid, ebt, crv, crv_taxable, itemDiscount,
    status, itemsAddedDateTime, lastMod, kitchenPrint, labelPrint, weight
) VALUES (
    '043ba71d-8942-4c0c-ae88-d545f7c25d33',
    '7bfbd9d3-e87a-47ab-8f03-5bab56d28911',
    @AGENTS_ID,
    @VENDORS_ID,
    @TERMINALS_ID,
    'Soft Drinks',
    @ORDERS_ID,
    2.5,
    'can soda',
    110,
    '',
    2.58,
    1,
    1,
    0,
    0,
    4053,
    10.75,
    1197,
    0,
    0,
    0,
    0,
    0,
    1762107017,
    1762135817,
    0,
    0,
    1
);

-- ============================================================
-- Main Item 2: Horchata Grande
-- Item UUID: 89681118-629d-4d76-bbe8-7bad350f2a92
-- ============================================================
INSERT INTO orderItems (
    itemUuid, orderUuid, agents_id, vendors_id, terminals_id,
    group_name, orders_id, cost, description, group_id,
    notes, price, taxable, qty, items_id, discount, orderReference,
    taxamount, itemid, ebt, crv, crv_taxable, itemDiscount,
    status, itemsAddedDateTime, lastMod, kitchenPrint, labelPrint, weight
) VALUES (
    '89681118-629d-4d76-bbe8-7bad350f2a92',
    '7bfbd9d3-e87a-47ab-8f03-5bab56d28911',
    @AGENTS_ID,
    @VENDORS_ID,
    @TERMINALS_ID,
    'Aguas Frescas',
    @ORDERS_ID,
    5.5,
    'Horchata Grande',
    111,
    '',
    5.67,
    1,
    1,
    0,
    0,
    4053,
    10.75,
    1217,
    0,
    0,
    0,
    0,
    0,
    1762107020,
    1762135820,
    0,
    0,
    1
);

-- ============================================================
-- Main Item 3: torta reg
-- Item UUID: 36b1d359-10c3-4cc1-848c-d5d5a1d7e6d8
-- ============================================================
INSERT INTO orderItems (
    itemUuid, orderUuid, agents_id, vendors_id, terminals_id,
    group_name, orders_id, cost, description, group_id,
    notes, price, taxable, qty, items_id, discount, orderReference,
    taxamount, itemid, ebt, crv, crv_taxable, itemDiscount,
    status, itemsAddedDateTime, lastMod, kitchenPrint, labelPrint, weight
) VALUES (
    '36b1d359-10c3-4cc1-848c-d5d5a1d7e6d8',
    '7bfbd9d3-e87a-47ab-8f03-5bab56d28911',
    @AGENTS_ID,
    @VENDORS_ID,
    @TERMINALS_ID,
    'Burritos Y Tortas',
    @ORDERS_ID,
    12.5,
    'torta reg',
    118,
    '',
    12.88,
    1,
    1,
    0,
    0,
    4053,
    10.75,
    1341,
    0,
    0,
    0,
    0,
    0,
    1762107033,
    1762135833,
    0,
    0,
    1
);

-- NOTE: After executing the above INSERT, use LAST_INSERT_ID() or the returned ID
--       as @PARENT_ITEM_ID_3 for the modifier(s) below

-- Modifier for Item 3: Pollo Asado
INSERT INTO orderItems (
    itemUuid, orderUuid, agents_id, vendors_id, terminals_id,
    group_name, orders_id, cost, description, group_id,
    notes, price, taxable, qty, items_id, discount, orderReference,
    taxamount, itemid, ebt, crv, crv_taxable, itemDiscount,
    status, itemsAddedDateTime, lastMod, kitchenPrint, labelPrint, weight
) VALUES (
    'd056048e-c17c-4d77-967a-cef434666b7a',
    '7bfbd9d3-e87a-47ab-8f03-5bab56d28911',
    @AGENTS_ID,
    @VENDORS_ID,
    @TERMINALS_ID,
    NULL,
    @ORDERS_ID,
    0,
    'Pollo Asado',
    141,
    '',
    0,
    1,
    1,
    @PARENT_ITEM_ID_3,
    0,
    4053,
    0,
    1667,
    0,
    0,
    0,
    0,
    0,
    1762107033,
    1762135833,
    0,
    0,
    1.0
);

-- ============================================================
-- Main Item 4: Consome
-- Item UUID: 06df2bd4-7861-40ac-bf04-6d07c691889d
-- ============================================================
INSERT INTO orderItems (
    itemUuid, orderUuid, agents_id, vendors_id, terminals_id,
    group_name, orders_id, cost, description, group_id,
    notes, price, taxable, qty, items_id, discount, orderReference,
    taxamount, itemid, ebt, crv, crv_taxable, itemDiscount,
    status, itemsAddedDateTime, lastMod, kitchenPrint, labelPrint, weight
) VALUES (
    '06df2bd4-7861-40ac-bf04-6d07c691889d',
    '7bfbd9d3-e87a-47ab-8f03-5bab56d28911',
    @AGENTS_ID,
    @VENDORS_ID,
    @TERMINALS_ID,
    'Tacos Y Quesadilla',
    @ORDERS_ID,
    3,
    'Consome',
    119,
    '',
    3.09,
    1,
    1,
    0,
    0,
    4053,
    10.75,
    1345,
    0,
    0,
    0,
    0,
    0,
    1762107039,
    1762135839,
    0,
    0,
    1
);

-- ============================================================
-- Main Item 5: quesabirria 
-- Item UUID: 819262ce-5a1b-489d-82b6-4d27c532f479
-- ============================================================
INSERT INTO orderItems (
    itemUuid, orderUuid, agents_id, vendors_id, terminals_id,
    group_name, orders_id, cost, description, group_id,
    notes, price, taxable, qty, items_id, discount, orderReference,
    taxamount, itemid, ebt, crv, crv_taxable, itemDiscount,
    status, itemsAddedDateTime, lastMod, kitchenPrint, labelPrint, weight
) VALUES (
    '819262ce-5a1b-489d-82b6-4d27c532f479',
    '7bfbd9d3-e87a-47ab-8f03-5bab56d28911',
    @AGENTS_ID,
    @VENDORS_ID,
    @TERMINALS_ID,
    'Tacos Y Quesadilla',
    @ORDERS_ID,
    5,
    'quesabirria ',
    119,
    '',
    5.15,
    1,
    4,
    0,
    0,
    4053,
    10.75,
    1347,
    0,
    0,
    0,
    0,
    0,
    1762107041,
    1762135841,
    0,
    0,
    1
);

-- ============================================================
-- Summary:
-- Total main items: 5
-- Total modifiers: 1
-- Total INSERT statements: 6
-- ============================================================
