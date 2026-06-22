import express, { type Request, type Response, type NextFunction } from 'express';
import { randomUUID, timingSafeEqual } from 'node:crypto';
import { StreamableHTTPServerTransport } from '@modelcontextprotocol/sdk/server/streamableHttp.js';
import { isInitializeRequest } from '@modelcontextprotocol/sdk/types.js';
import { createCrmServer } from './server.js';

// ---------------------------------------------------------------------------
// HTTP / SSE entry point (Streamable HTTP transport, MCP spec 2025-03-26)
//
// Exposes the same MCP tools/resources as the stdio server over HTTP so the
// server can be reached remotely (e.g. from Claude's "Add custom connector"
// dialog, which requires a remote HTTPS URL). The stdio transport in index.ts
// is untouched — this is an additional, independent entry point.
// ---------------------------------------------------------------------------

const PORT = Number(process.env.MCP_HTTP_PORT ?? 3000);
const HOST = process.env.MCP_HTTP_HOST ?? '127.0.0.1';
const MCP_PATH = process.env.MCP_HTTP_PATH ?? '/mcp';

// Bearer token that gates access to the MCP endpoint. Defaults to the CRM API
// key so there is a single secret to manage, but can be overridden with a
// dedicated token via MCP_BEARER_TOKEN.
const BEARER_TOKEN = process.env.MCP_BEARER_TOKEN ?? process.env.CRM_API_KEY ?? '';

if (!BEARER_TOKEN) {
  console.error('ERROR: MCP_BEARER_TOKEN (or CRM_API_KEY) must be set for the HTTP transport.');
  process.exit(1);
}

// ---------------------------------------------------------------------------
// Auth middleware: constant-time bearer-token check
// ---------------------------------------------------------------------------
function requireBearer(req: Request, res: Response, next: NextFunction): void {
  const header = req.headers.authorization ?? '';
  const match = header.match(/^Bearer\s+(.+)$/i);
  const presented = match?.[1]?.trim() ?? '';

  if (!presented || !safeEqual(presented, BEARER_TOKEN)) {
    res.status(401)
      .set('WWW-Authenticate', 'Bearer realm="personal-crm-mcp"')
      .json({
        jsonrpc: '2.0',
        error: { code: -32001, message: 'Unauthorized: missing or invalid bearer token' },
        id: null,
      });
    return;
  }
  next();
}

function safeEqual(a: string, b: string): boolean {
  const ab = Buffer.from(a);
  const bb = Buffer.from(b);
  if (ab.length !== bb.length) return false;
  return timingSafeEqual(ab, bb);
}

// ---------------------------------------------------------------------------
// Session registry: one transport + Server per active session
// ---------------------------------------------------------------------------
const transports: Record<string, StreamableHTTPServerTransport> = {};

const app = express();
app.use(express.json({ limit: '4mb' }));

// Health check (unauthenticated, for load balancers / debugging) -------------
app.get('/health', (_req: Request, res: Response) => {
  res.json({
    status: 'ok',
    transport: 'streamable-http',
    protocol: '2025-03-26',
    sessions: Object.keys(transports).length,
    uptime_s: Math.round(process.uptime()),
  });
});

// POST /mcp — client -> server messages (incl. initialization) ---------------
app.post(MCP_PATH, requireBearer, async (req: Request, res: Response) => {
  const sessionId = req.headers['mcp-session-id'] as string | undefined;
  let transport: StreamableHTTPServerTransport;

  if (sessionId && transports[sessionId]) {
    // Existing session.
    transport = transports[sessionId];
  } else if (!sessionId && isInitializeRequest(req.body)) {
    // New initialization request — spin up a fresh transport + Server.
    transport = new StreamableHTTPServerTransport({
      sessionIdGenerator: () => randomUUID(),
      onsessioninitialized: (sid) => {
        transports[sid] = transport;
      },
      // Optional hardening against DNS-rebinding for browser-based clients.
      // Disabled by default; enable + set allowedHosts when fronting directly.
      enableDnsRebindingProtection: false,
    });

    transport.onclose = () => {
      if (transport.sessionId) {
        delete transports[transport.sessionId];
      }
    };

    const server = createCrmServer();
    await server.connect(transport);
  } else {
    res.status(400).json({
      jsonrpc: '2.0',
      error: { code: -32000, message: 'Bad Request: no valid session ID provided' },
      id: null,
    });
    return;
  }

  await transport.handleRequest(req, res, req.body);
});

// GET /mcp — server -> client notifications over SSE -------------------------
app.get(MCP_PATH, requireBearer, handleSessionRequest);

// DELETE /mcp — explicit session termination --------------------------------
app.delete(MCP_PATH, requireBearer, handleSessionRequest);

async function handleSessionRequest(req: Request, res: Response): Promise<void> {
  const sessionId = req.headers['mcp-session-id'] as string | undefined;
  if (!sessionId || !transports[sessionId]) {
    res.status(400).send('Invalid or missing session ID');
    return;
  }
  await transports[sessionId].handleRequest(req, res);
}

app.listen(PORT, HOST, () => {
  console.error(`Personal CRM MCP server (Streamable HTTP) listening on http://${HOST}:${PORT}${MCP_PATH}`);
  console.error(`Health check: http://${HOST}:${PORT}/health`);
});
