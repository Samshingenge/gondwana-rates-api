# Gondwana Rates API

A PHP-based REST API with a simple frontend UI for querying **accommodation rates and availability** from the Gondwana Collection Namibia remote API.

<img width="1648" height="1239" alt="Top" src="https://github.com/user-attachments/assets/d2a8d6e2-49b2-4de2-90f2-19e93bc91393" />


---

## 📌 Features

- **Backend (PHP API)**
  - `/api/rates` → Accepts a booking payload, validates it, transforms it to Gondwana API format, and relays rates.
  - `/api/test` → Debug endpoint for validating JSON payloads.
  - Input validation: dates, occupants, ages, unit type.
  - Configurable unit mappings (Standard, Deluxe, etc).
  - Logs requests/responses to `backend/logs/`.

- **Frontend (HTML + JS)**
  - Interactive form to select unit, dates, occupants, and ages.
  - Validates inputs client-side.
  - Calls the backend API and displays formatted results (rates, availability, date range).
  - Styled responsive UI.

- **Dev & CI/CD**
  - `.devcontainer/` for GitHub Codespaces / Docker setup.
  - GitHub Actions workflow with **SonarCloud** QA checks.
  - `test-api.sh` script for local endpoint testing.
  - PHPUnit tests in `backend/tests/`.

---

## 🏗 Project Structure

gondwana-rates-api/
├── backend/
│ ├── public/ # API entrypoints (index.php, .htaccess)
│ ├── src/ # config, helpers, controller
│ ├── tests/ # PHPUnit tests
│ ├── logs/ # Logs (auto-created)
│ ├── composer.json # Dependencies
│ └── phpunit.xml # Test config
├── frontend/ # UI (index.html, test.html)
├── .devcontainer/ # Devcontainer + Docker setup
├── .github/workflows/ # SonarCloud pipeline
├── test-api.sh # Local test script
└── README.md # Documentation


---

## 🚀 Running the Project

### 1. Backend (API)
From repo root:

```bash
cd backend/public
php -S localhost:8000

Now the API is live at:

http://localhost:8000/api

http://localhost:8000/api/test

Run quick tests:
./test-api.sh

2. Frontend (UI)

Serve frontend/ with PHP (recommended since PHP is already installed):
cd frontend
php -S localhost:5500

cd backend/public && php -S localhost:8000 router.php
curl -s "http://localhost:8000/?start=project" killed


Open in browser:
👉 http://localhost:5500/index.html

The frontend will call the backend API at http://localhost:8000/api.

3. Example API Request
curl -X POST http://localhost:8000/api/rates \
  -H "Content-Type: application/json" \
  -d '{
    "Unit Name": "Standard Unit",
    "Arrival": "25/01/2024",
    "Departure": "28/01/2024",
    "Occupants": 2,
    "Ages": [34, 9]
  }'

🧪 Testing

Run PHPUnit tests:
cd backend
./vendor/bin/phpunit

⚙️ Configuration

Unit Types are mapped in src/config.php
.,,

Remote API URL:
https://dev.gondwana-collection.com/Web-Store/Rates/Rates.php


🔐 Security Notes

CORS enabled for development (* origin allowed). Restrict before production.

CSP blocks inline JS; all scripts are external or event listeners.

Input is validated and sanitized both backend and frontend.

📦 Deployment

Docker:
docker build -t gondwana-api -f .devcontainer/Dockerfile .
docker run -it -p 8000:8000 gondwana-api
GitHub Codespaces will auto-detect .devcontainer/.
