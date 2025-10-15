#!/bin/bash

echo "🚀 Testing Gondwana Collection API endpoints..."
echo "Make sure PHP server is running: cd backend/public && php -S localhost:8000"
echo ""

# Test 1: Basic GET request
echo "=== Test 1: GET /api ==="
curl -X GET http://localhost:8000/api \
     -H "Content-Type: application/json" \
     -w "\nStatus: %{http_code}\nTime: %{time_total}s\n" \
     -s

echo -e "\n" | head -c 80 | tr ' ' '='

# Test 2: Debug endpoint
echo -e "\n=== Test 2: POST /debug.php ==="
curl -X POST http://localhost:8000/debug.php \
     -H "Content-Type: application/json" \
     -d '{"test":"data","number":123}' \
     -w "\nStatus: %{http_code}\nTime: %{time_total}s\n" \
     -s

echo -e "\n" | head -c 80 | tr ' ' '='

# Test 3: Test endpoint
echo -e "\n=== Test 3: POST /api/test ==="
curl -X POST http://localhost:8000/api/test \
     -H "Content-Type: application/json" \
     -d '{"test":"data","number":123}' \
     -w "\nStatus: %{http_code}\nTime: %{time_total}s\n" \
     -s

echo -e "\n" | head -c 80 | tr ' ' '='

# Test 4: Rates endpoint with sample data
echo -e "\n=== Test 4: POST /api/rates ==="
curl -X POST http://localhost:8000/api/rates \
     -H "Content-Type: application/json" \
     -d '{
       "Unit Name": "Standard Unit",
       "Arrival": "25/01/2024", 
       "Departure": "28/01/2024",
       "Occupants": 2,
       "Ages": [25, 30]
     }' \
     -w "\nStatus: %{http_code}\nTime: %{time_total}s\n" \
     -s

echo -e "\n" | head -c 80 | tr ' ' '='
echo -e "\n✅ Tests completed! Check backend/logs/ for detailed logs."
echo "💡 To make script executable: chmod +x test-api.sh"
echo "🏃 To run: ./test-api.sh"