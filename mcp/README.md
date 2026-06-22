# Personal CRM MCP Adapter

MCP server that exposes Personal Outreach CRM data as tools and resources for MCP-compatible AI clients (Claude Desktop, Cursor, etc.).

## Setup

```bash
cd mcp
cp .env.example .env
# Edit .env: fill in CRM_BASE_URL and CRM_API_KEY
npm install
npm run build
```

## Running

```bash
node dist/index.js
```

Or in dev mode:
```bash
npm run dev
```

## Claude Desktop config

Add to `~/Library/Application Support/Claude/claude_desktop_config.json` (macOS):

```json
{
  "mcpServers": {
    "personal-crm": {
      "command": "node",
      "args": ["/absolute/path/to/personal-crm/mcp/dist/index.js"],
      "env": {
        "CRM_BASE_URL": "https://your-crm.com/api/gpt/v1",
        "CRM_API_KEY": "pocrm_live_..."
      }
    }
  }
}
```

## Remote access: HTTP / SSE transport (Streamable HTTP)

The same tools/resources can be served over **Streamable HTTP** (MCP spec
`2025-03-26`) so the server can be added through Claude's **"Add custom
connector"** dialog, which requires a remote HTTPS URL. The stdio transport
above is unchanged — `dist/index.js` is still stdio; the HTTP transport is a
separate entry point, `dist/http.js`.

### Run it

```bash
npm run build
npm run start:http       # or: node dist/http.js
```

Environment variables (see `.env.example`):

| Var | Default | Purpose |
|-----|---------|---------|
| `CRM_BASE_URL` | — | Laravel REST API base (e.g. `https://crm.dexdevs.com/api/gpt/v1`) |
| `CRM_API_KEY` | — | Key the adapter uses to call the CRM REST API |
| `MCP_HTTP_PORT` | `3000` | Port the HTTP server binds to |
| `MCP_HTTP_HOST` | `127.0.0.1` | Bind address (keep on loopback behind nginx) |
| `MCP_HTTP_PATH` | `/mcp` | Path the MCP endpoint is served at |
| `MCP_BEARER_TOKEN` | falls back to `CRM_API_KEY` | Bearer token clients must present |

### Authentication

Every request to the MCP endpoint must send:

```
Authorization: Bearer <MCP_BEARER_TOKEN>
```

The check is constant-time. Missing/invalid tokens get `401`. The `/health`
endpoint is intentionally unauthenticated for load-balancer / debugging use.

### Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/mcp` | Client → server JSON-RPC (initialize + all calls) |
| `GET`  | `/mcp` | Server → client SSE notification stream (uses `mcp-session-id`) |
| `DELETE` | `/mcp` | Terminate a session |
| `GET`  | `/health` | Unauthenticated health probe |

### Hosting at `crm.dexdevs.com/mcp` (nginx reverse proxy)

Run `node dist/http.js` bound to loopback (e.g. `127.0.0.1:3000`) under a
process manager (systemd / pm2), and let nginx terminate TLS and proxy `/mcp`:

```nginx
# Inside the existing server { } block for crm.dexdevs.com (TLS already configured)
location /mcp {
    proxy_pass http://127.0.0.1:3000/mcp;
    proxy_http_version 1.1;

    # Required for SSE streaming (GET /mcp):
    proxy_set_header Connection '';
    proxy_buffering off;
    proxy_cache off;
    proxy_read_timeout 3600s;

    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;

    # Pass the bearer token through to the Node app:
    proxy_set_header Authorization $http_authorization;
}

location = /health {
    proxy_pass http://127.0.0.1:3000/health;
}
```

Prefer a subdomain (`mcp.crm.dexdevs.com`)? Use a dedicated server block and
proxy `location /` to `http://127.0.0.1:3000` with the same SSE settings; then
set `MCP_HTTP_PATH=/mcp` (or `/`) to match the URL you advertise.

> Note: keep `MCP_HTTP_HOST=127.0.0.1` so the Node process is never exposed
> directly — only nginx (with TLS) faces the internet.

### Test with curl before connecting

```bash
# 1) Health (no auth) — should return {"status":"ok",...}
curl https://crm.dexdevs.com/health

# 2) Auth gate — no token should return 401
curl -i -X POST https://crm.dexdevs.com/mcp \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"curl","version":"0"}}}'

# 3) Initialize with the bearer token — should return 200 and an
#    "mcp-session-id" response header plus the server capabilities.
curl -i -X POST https://crm.dexdevs.com/mcp \
  -H 'Authorization: Bearer pocrm_live_your_key_here' \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"curl","version":"0"}}}'
```

### Add to Claude (custom connector)

In **Settings → Connectors → Add custom connector**, paste:

```
https://crm.dexdevs.com/mcp
```

When prompted for authentication, supply the bearer token
(`MCP_BEARER_TOKEN`, which defaults to your `CRM_API_KEY`).

## Available Tools

| Tool | Description |
|------|-------------|
| `crm_dashboard_summary` | CRM stats and next actions |
| `crm_search_contacts` | Search contacts |
| `crm_get_contact` | Get contact by ID |
| `crm_create_contact` | Create a new contact |
| `crm_search_opportunities` | Search opportunities |
| `crm_get_opportunity` | Get opportunity by ID |
| `crm_create_opportunity` | Create opportunity (deduplicates) |
| `crm_link_contact_to_opportunity` | Link a contact to an opportunity |
| `crm_add_note` | Append note to contact or opportunity |
| `crm_create_email_draft` | Save draft for review (never sends) |
| `crm_get_email_draft_preview` | Get rendered preview of a draft |
| `crm_create_followup` | Schedule reminder-only follow-up |
| `crm_recent_replies` | Get recent inbound replies |
| `crm_list_documents` | List documents (optionally scoped to contact or opportunity) |
| `crm_get_document` | Get document by ID with version history |
| `crm_ingest_opportunities` | Bulk ingest from external sources |

## Available Resources

| URI | Description |
|-----|-------------|
| `crm://dashboard/summary` | Dashboard summary |
| `crm://opportunities/recent` | Recently updated opportunities |
| `crm://opportunities/due-soon` | Deadlines in next 7 days |
| `crm://contacts/recent` | Recent contacts |
| `crm://followups/due` | Due today or overdue |
| `crm://email-drafts/pending-review` | Drafts awaiting review |

## Security

- The MCP adapter calls the Laravel API — it never touches the database directly.
- API keys are stored in `.env` (never committed).
- Email drafts are **never sent automatically**.
- Suppressed contacts are blocked at the API layer.
