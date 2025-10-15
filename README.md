# Gondwana Rates API

A PHP-based REST API with a simple frontend UI for querying **accommodation rates and availability** from the Gondwana Collection Namibia remote API.

<img width="1648" height="1239" alt="Top" src="https://github.com/user-attachments/assets/d2a8d6e2-49b2-4de2-90f2-19e93bc91393" />


---

## 📌 Features

- **Backend (PHP API)**
  - [`backend/public/index.php`](backend/public/index.php) exposes the `/api` entrypoints (rates + test) with unified CORS handling via [`backend/src/helpers.php`](backend/src/helpers.php).
  - Input validation for dates, occupants, ages, and unit type.
  - Configurable unit mappings (Standard, Deluxe, etc.) via [`backend/src/config.php`](backend/src/config.php).
  - Logs requests/responses to [`backend/logs/`](backend/logs/) (auto-created).

- **Frontend (HTML + JS)**
  - Interactive form to select units, dates, occupants, and ages.
  - Client-side validation and accessibility-focused UI enhancements.
  - Calls the backend API through a resilient base resolution strategy in [`frontend/app.js`](frontend/app.js).
  - Shared assets are sourced exclusively from [`frontend/`](frontend/), eliminating duplicated static files in the backend.

- **Dev & CI/CD**
  - [`./.devcontainer/`](.devcontainer/) for GitHub Codespaces / Docker setup.
  - GitHub Actions workflow with **SonarCloud** QA checks in [`./.github/workflows/`](.github/workflows/).
  - [`test-api.sh`](test-api.sh) script for local endpoint testing.
  - PHPUnit tests in [`backend/tests/`](backend/tests/).

---

## 🏗 Project Structure

```text
gondwana-rates-api/
├── backend/
│   ├── public/           # API front controller + router serving SPA assets
│   ├── src/              # config, helpers, controller, extraction utilities
│   ├── tests/            # PHPUnit tests
│   ├── logs/             # Logs (auto-created)
│   ├── composer.json     # Dependencies
│   └── phpunit.xml       # Test config
├── frontend/             # SPA assets (index.html, app.js, date-utils.js, assets/)
├── .devcontainer/        # Devcontainer + Docker setup
├── .github/workflows/    # SonarCloud pipeline
├── test-api.sh           # Local test script
└── README.md             # Documentation
```

---

> **GitHub Codespaces note:** When the frontend is served on `https://<workspace>-5500.app.github.dev`, it will automatically call the backend through the matching HTTPS tunnel (`https://<workspace>-8000.app.github.dev/api`). No manual API base override is required, though you can still set `localStorage.setItem('API_BASE', '<url>')` for custom targets if needed.

---

## 🚀 Running the Project

### 1. Start the API and frontend shell

```bash
cd backend/public
php -S 0.0.0.0:8000 router.php
```

The built-in PHP server now hosts everything at once:

- API base: <http://localhost:8000/api>
- Test endpoint: <http://localhost:8000/api/test>
- Frontend UI: <http://localhost:8000/index.html>

Run the quick smoke tests from the repository root:

```bash
./test-api.sh
``

### 2. Example API Request

```bash
curl -X POST http://localhost:8000/api/rates \
  -H "Content-Type: application/json" \
  -d '{
    "Unit Name": "Standard Unit",
    "Arrival": "25/01/2024",
    "Departure": "28/01/2024",
    "Occupants": 2,
    "Ages": [34, 9]
  }'
```

## 🧪 Testing

Run PHPUnit tests:

```bash
cd backend
./vendor/bin/phpunit
```

## ⚙️ Configuration

- Unit types are mapped in [`backend/src/config.php`](backend/src/config.php) (`UNIT_TYPE_MAPPING`).
- Remote API URL target is controlled via the `REMOTE_API_URL` constant.
- Environment defaults (timezone, logging, error reporting) are also defined in the same config file.

## 🔐 Security Notes

- CORS is enabled for development and configured centrally in [`backend/src/helpers.php`](backend/src/helpers.php). Tighten the `ALLOWED_ORIGINS` constant before production.
- CSP blocks inline JS; all scripts are loaded as external modules or event listeners.
- Input is validated and sanitized on both the backend and frontend.

## 📦 Deployment

Docker build & run:

```bash
docker build -t gondwana-api -f .devcontainer/Dockerfile .
docker run -it -p 8000:8000 gondwana-api
```

GitHub Codespaces will auto-detect the `.devcontainer/` configuration.
