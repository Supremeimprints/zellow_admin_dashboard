#!/bin/bash

# Base URL
BASE_URL="http://localhost/zellow_admin/api"

# Login and get token
echo "Testing login..."
TOKEN=$(curl -s -X POST "$BASE_URL/auth/login" \
-H "Content-Type: application/json" \
-d '{"email":"gitau.magana@zellow.com","password":"12345678"}' \
| grep -o '"token":"[^"]*' | cut -d'"' -f4)

echo "Token: $TOKEN"

# Test service request
echo -e "\nTesting service request..."
curl -X POST "$BASE_URL/services/request" \
-H "Content-Type: application/json" \
-H "Authorization: Bearer $TOKEN" \
-d '{"service_type":1,"description":"Test service request"}'

# Test feedback
echo -e "\nTesting feedback..."
curl -X POST "$BASE_URL/feedback" \
-H "Content-Type: application/json" \
-H "Authorization: Bearer $TOKEN" \
-d '{"type":"general","content":"Test feedback","rating":5}'
