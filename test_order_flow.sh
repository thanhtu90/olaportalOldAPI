#!/bin/bash

# Function to generate a UUID
generate_uuid() {
    uuidgen | tr '[:upper:]' '[:lower:]'
}

# Generate new UUID
NEW_UUID=$(generate_uuid)
echo "Generated UUID: $NEW_UUID"

# Create temporary files for payloads
PAYLOAD1_FILE="payload1.json"
PAYLOAD2_FILE="payload2.json"

# Payload 1 with the new UUID
cat > "$PAYLOAD1_FILE" << 'EOL'
{"json":"{\"hasInventory\":true,\"payments\":\"[]\",\"items\":\"[{\\\"item\\\":{\\\"amountOnHand\\\":81,\\\"cost\\\":0.0,\\\"crv\\\":0.0,\\\"crv_taxable\\\":0,\\\"description\\\":\\\"Test Item number 2\\\",\\\"ebt\\\":0,\\\"group\\\":44,\\\"iUUID\\\":\\\"bb52c983-02d9-4861-a415-185663eaf2d2\\\",\\\"id\\\":221,\\\"kitchenPrint\\\":0,\\\"labelPrint\\\":0,\\\"largeImage\\\":\\\"#009cef\\\",\\\"lastMod\\\":1738399190,\\\"manualPrice\\\":0,\\\"notes\\\":\\\"DuyTestItemNumber2\\\",\\\"price\\\":20.0,\\\"smallImage\\\":\\\"\\\",\\\"taxRate\\\":20.0,\\\"taxable\\\":0,\\\"upc\\\":\\\"\\\",\\\"weighted\\\":0},\\\"mods\\\":[],\\\"orderItem\\\":{\\\"cost\\\":20.0,\\\"crv\\\":0.0,\\\"crv_taxable\\\":0,\\\"description\\\":\\\"Test Item number 2\\\",\\\"discount\\\":0.0,\\\"ebt\\\":0,\\\"group\\\":44,\\\"iUUID\\\":\\\"bb52c983-02d9-4861-a415-185663eaf2d2\\\",\\\"id\\\":213,\\\"itemAddedDateTime\\\":\\\"Feb 4, 2025 23:34:52 PM\\\",\\\"itemDiscount\\\":0.0,\\\"itemId\\\":221,\\\"kitchenPrint\\\":0,\\\"labelPrint\\\":0,\\\"lastMod\\\":1738629292,\\\"notes\\\":\\\"\\\",\\\"oUUID\\\":\\\"27c91fd1-5550-46b7-ba4b-8c907117b3a9\\\",\\\"orderReference\\\":92,\\\"price\\\":20.0,\\\"qty\\\":1,\\\"status\\\":\\\"READY_TO_PAY\\\",\\\"taxAmount\\\":20.0,\\\"taxable\\\":0,\\\"weight\\\":1.0},\\\"orderModsItems\\\":[]}]\",\"orders\":\"[{\\\"employeeId\\\":0,\\\"employeePIN\\\":\\\"NONE\\\",\\\"id\\\":92,\\\"lastMod\\\":1738629307,\\\"notes\\\":\\\"\\\",\\\"oUUID\\\":\\\"27c91fd1-5550-46b7-ba4b-8c907117b3a9\\\",\\\"orderDate\\\":\\\"Feb 4, 2025 23:34:52 PM\\\",\\\"orderName\\\":\\\"\\\",\\\"status\\\":\\\"PAID\\\",\\\"subTotal\\\":20.0,\\\"tax\\\":0.0,\\\"total\\\":20.0}]\",\"groups\":\"[{\\\"description\\\":\\\"test\\\",\\\"gUUID\\\":\\\"17fb10ab-4819-4437-ba1a-c6ae3a348b91\\\",\\\"groupType\\\":\\\"PARENT_GROUP\\\",\\\"id\\\":44,\\\"lastMod\\\":1738398996,\\\"notes\\\":\\\"\\\"}]\",\"itemdata\":\"[{\\\"amountOnHand\\\":81,\\\"cost\\\":0.0,\\\"crv\\\":0.0,\\\"crv_taxable\\\":0,\\\"description\\\":\\\"Test Item number 2\\\",\\\"ebt\\\":0,\\\"group\\\":44,\\\"iUUID\\\":\\\"bb52c983-02d9-4861-a415-185663eaf2d2\\\",\\\"id\\\":221,\\\"kitchenPrint\\\":0,\\\"labelPrint\\\":0,\\\"largeImage\\\":\\\"#009cef\\\",\\\"lastMod\\\":1738399190,\\\"manualPrice\\\":0,\\\"notes\\\":\\\"DuyTestItemNumber2\\\",\\\"price\\\":20.0,\\\"smallImage\\\":\\\"\\\",\\\"taxRate\\\":20.0,\\\"taxable\\\":0,\\\"upc\\\":\\\"\\\",\\\"weighted\\\":0}]\",\"termId\":\"\\\"\\\"\"}","serial":"A127CP220600180"}
EOL

# Payload 2 with the new UUID
cat > "$PAYLOAD2_FILE" << 'EOL'
{"json":"{\"hasInventory\":true,\"payments\":\"[{\\\"amtPaid\\\":20.0,\\\"employeeId\\\":0,\\\"employeePIN\\\":\\\"NONE\\\",\\\"id\\\":54,\\\"lastMod\\\":1738629307,\\\"oUUID\\\":\\\"27c91fd1-5550-46b7-ba4b-8c907117b3a9\\\",\\\"olapayApprovalId\\\":\\\"\\\",\\\"orderID\\\":\\\"S-CSH-8-092\\\",\\\"orderReference\\\":\\\"92\\\",\\\"pUUID\\\":\\\"abca5852-2f4d-452f-a241-6a0165887ff5\\\",\\\"payDate\\\":\\\"Feb 4, 2025 4:35:07 PM\\\",\\\"refNumber\\\":\\\"CASH\\\",\\\"refund\\\":0.0,\\\"status\\\":\\\"PAID\\\",\\\"techfee\\\":0.0,\\\"tips\\\":0.0,\\\"total\\\":20.0}]\",\"items\":\"[{\\\"item\\\":{\\\"amountOnHand\\\":81,\\\"cost\\\":0.0,\\\"crv\\\":0.0,\\\"crv_taxable\\\":0,\\\"description\\\":\\\"Test Item number 2\\\",\\\"ebt\\\":0,\\\"group\\\":44,\\\"iUUID\\\":\\\"bb52c983-02d9-4861-a415-185663eaf2d2\\\",\\\"id\\\":221,\\\"kitchenPrint\\\":0,\\\"labelPrint\\\":0,\\\"largeImage\\\":\\\"#009cef\\\",\\\"lastMod\\\":1738399190,\\\"manualPrice\\\":0,\\\"notes\\\":\\\"DuyTestItemNumber2\\\",\\\"price\\\":20.0,\\\"smallImage\\\":\\\"\\\",\\\"taxRate\\\":20.0,\\\"taxable\\\":0,\\\"upc\\\":\\\"\\\",\\\"weighted\\\":0},\\\"mods\\\":[],\\\"orderItem\\\":{\\\"cost\\\":20.0,\\\"crv\\\":0.0,\\\"crv_taxable\\\":0,\\\"description\\\":\\\"Test Item number 2\\\",\\\"discount\\\":0.0,\\\"ebt\\\":0,\\\"group\\\":44,\\\"iUUID\\\":\\\"bb52c983-02d9-4861-a415-185663eaf2d2\\\",\\\"id\\\":213,\\\"itemAddedDateTime\\\":\\\"Feb 4, 2025 23:34:52 PM\\\",\\\"itemDiscount\\\":0.0,\\\"itemId\\\":221,\\\"kitchenPrint\\\":0,\\\"labelPrint\\\":0,\\\"lastMod\\\":1738629292,\\\"notes\\\":\\\"\\\",\\\"oUUID\\\":\\\"27c91fd1-5550-46b7-ba4b-8c907117b3a9\\\",\\\"orderReference\\\":92,\\\"price\\\":20.0,\\\"qty\\\":1,\\\"status\\\":\\\"READY_TO_PAY\\\",\\\"taxAmount\\\":20.0,\\\"taxable\\\":0,\\\"weight\\\":1.0},\\\"orderModsItems\\\":[]}]\",\"orders\":\"[{\\\"employeeId\\\":0,\\\"employeePIN\\\":\\\"NONE\\\",\\\"id\\\":92,\\\"lastMod\\\":1738629307,\\\"notes\\\":\\\"\\\",\\\"oUUID\\\":\\\"27c91fd1-5550-46b7-ba4b-8c907117b3a9\\\",\\\"orderDate\\\":\\\"Feb 4, 2025 23:34:52 PM\\\",\\\"orderName\\\":\\\"\\\",\\\"status\\\":\\\"PAID\\\",\\\"subTotal\\\":20.0,\\\"tax\\\":0.0,\\\"total\\\":20.0}]\",\"groups\":\"[{\\\"description\\\":\\\"test\\\",\\\"gUUID\\\":\\\"17fb10ab-4819-4437-ba1a-c6ae3a348b91\\\",\\\"groupType\\\":\\\"PARENT_GROUP\\\",\\\"id\\\":44,\\\"lastMod\\\":1738398996,\\\"notes\\\":\\\"\\\"}]\",\"itemdata\":\"[{\\\"amountOnHand\\\":81,\\\"cost\\\":0.0,\\\"crv\\\":0.0,\\\"crv_taxable\\\":0,\\\"description\\\":\\\"Test Item number 2\\\",\\\"ebt\\\":0,\\\"group\\\":44,\\\"iUUID\\\":\\\"bb52c983-02d9-4861-a415-185663eaf2d2\\\",\\\"id\\\":221,\\\"kitchenPrint\\\":0,\\\"labelPrint\\\":0,\\\"largeImage\\\":\\\"#009cef\\\",\\\"lastMod\\\":1738399190,\\\"manualPrice\\\":0,\\\"notes\\\":\\\"DuyTestItemNumber2\\\",\\\"price\\\":20.0,\\\"smallImage\\\":\\\"\\\",\\\"taxRate\\\":20.0,\\\"taxable\\\":0,\\\"upc\\\":\\\"\\\",\\\"weighted\\\":0}]\",\"termId\":\"\\\"\\\"\"}","serial":"A127CP220600180"}
EOL

# Replace the UUID in both payloads
OLD_UUID="27c91fd1-5550-46b7-ba4b-8c907117b3a9"
sed -i '' "s/$OLD_UUID/$NEW_UUID/g" "$PAYLOAD1_FILE"
sed -i '' "s/$OLD_UUID/$NEW_UUID/g" "$PAYLOAD2_FILE"

# API endpoint
API_URL="http://poslite.teamsable.com/api/json.php"

echo "Sending first payload..."
curl -X POST -H "Content-Type: application/json" -d @"$PAYLOAD1_FILE" "$API_URL"

echo -e "\nWaiting 5 seconds..."
sleep 5

echo "Sending second payload..."
curl -X POST -H "Content-Type: application/json" -d @"$PAYLOAD2_FILE" "$API_URL"

# Clean up temporary files
rm -f "$PAYLOAD1_FILE" "$PAYLOAD2_FILE"

echo -e "\nDone!" 