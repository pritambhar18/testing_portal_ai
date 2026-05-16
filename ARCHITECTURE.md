# QA Testing Portal Architecture

The active application is a Node.js and Express portal. PHP code has been archived under `legacy/` and is no longer part of the runtime path.

## Request Flow

```text
Browser
  |
  v
Express server.js
  |-- GET  /                    -> frontend/index.html dashboard
  |-- POST /api/login           -> JWT authentication
  |-- POST /api/run-automation  -> 88startech Playwright order flow
  |-- GET  /api/reports         -> report list
  |-- GET  /api/quick-check     -> Playwright form validation checks
  |-- GET  /reports/:id         -> single report redirect
  |
  v
MySQL / PlanetScale
  |-- admin
  |-- users
  |-- test_reports
  |-- automation_logs
```

## Runtime Modules

```text
server.js
  |
  |-- api/auth.js
  |     |-- POST /api/login
  |     |-- JWT creation and verification
  |
  |-- api/get-test-reports.js
  |     |-- GET /api/reports
  |     |-- GET /api/get-test-reports
  |
  |-- api/save-test-report.js
  |     |-- POST /api/save-test-report
  |
  |-- order_placement/run-order-flow.mjs
  |     |-- 88startech order automation
  |     |-- screenshots, HTML report, PDF/HTML artifact
  |
  |-- automation/form-functional-checks.mjs
        |-- quick form checks
        |-- screenshot capture
        |-- JSON result output
```

## Database

Connection settings are loaded from `.env` through `config/db.js`.

Use either:

```text
DATABASE_URL=mysql://...
```

or:

```text
DB_HOST=
DB_USER=
DB_PASSWORD=
DB_NAME=
DB_SSL=true
```

The canonical schema is in `database_schema.sql`.

## Active Folders

```text
api/                  Node API handlers
automation/           Quick-check Playwright automation
config/               Node MySQL connection
frontend/             Dashboard HTML/CSS/JS
order_placement/      88startech Playwright order flow
reports/              Generated report artifacts
uploads/              Uploaded files and order-flow reports
legacy/               Archived PHP/deployment/old runtime code
```

## Authentication

The PHP session layer has been replaced with bearer-token JWT authentication.

Protected endpoints expect:

```text
Authorization: Bearer <token>
```

The dashboard stores the token in browser local storage under `qa_portal_token`.

## Deployment

The app is deployable to Node hosts such as Render, Railway, Fly.io, or Heroku-compatible platforms.

Required production settings:

```text
PORT
JWT_SECRET
DATABASE_URL
DB_SSL=true
```
