import express, { type Request, type Response, type NextFunction } from 'express';
import { createHmac, randomBytes, randomUUID, timingSafeEqual } from 'node:crypto';
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
const EXTERNAL_URL = stripTrailingSlash(process.env.MCP_EXTERNAL_URL ?? `http://${HOST}:${PORT}${MCP_PATH}`);
const ISSUER_URL = stripTrailingSlash(process.env.MCP_OAUTH_ISSUER_URL ?? new URL('/', EXTERNAL_URL).origin);
const RESOURCE_METADATA_URL = `${new URL(EXTERNAL_URL).origin}/.well-known/oauth-protected-resource${new URL(EXTERNAL_URL).pathname}`;
const SCOPES = ['crm:read', 'crm:write'];

// Bearer token that gates access to the MCP endpoint. Defaults to the CRM API
// key so there is a single secret to manage, but can be overridden with a
// dedicated token via MCP_BEARER_TOKEN.
const BEARER_TOKEN = process.env.MCP_BEARER_TOKEN ?? process.env.CRM_API_KEY ?? '';
const OAUTH_CLIENT_ID = process.env.MCP_OAUTH_CLIENT_ID ?? '';
const OAUTH_CLIENT_SECRET = process.env.MCP_OAUTH_CLIENT_SECRET ?? '';
const TOKEN_SIGNING_SECRET = process.env.MCP_OAUTH_TOKEN_SECRET ?? BEARER_TOKEN;
const ACCESS_TOKEN_TTL_SECONDS = Number(process.env.MCP_OAUTH_ACCESS_TOKEN_TTL_SECONDS ?? 60 * 60 * 24);
const REFRESH_TOKEN_TTL_SECONDS = Number(process.env.MCP_OAUTH_REFRESH_TOKEN_TTL_SECONDS ?? 60 * 60 * 24 * 30);

if (!BEARER_TOKEN && (!OAUTH_CLIENT_ID || !OAUTH_CLIENT_SECRET || !TOKEN_SIGNING_SECRET)) {
  console.error('ERROR: set MCP_BEARER_TOKEN/CRM_API_KEY or MCP_OAUTH_CLIENT_ID + MCP_OAUTH_CLIENT_SECRET + MCP_OAUTH_TOKEN_SECRET.');
  process.exit(1);
}

// ---------------------------------------------------------------------------
// Minimal OAuth 2.1 authorization-code support for remote MCP clients.
//
// This is a single-owner connector: OAuth grants access to this MCP adapter,
// while the adapter itself calls the CRM API using CRM_API_KEY. Tokens are
// stateless HMAC-signed values so service restarts do not invalidate Claude's
// connection. Authorization codes are short-lived and in-memory.
// ---------------------------------------------------------------------------
type AuthCode = {
  clientId: string;
  redirectUri: string;
  codeChallenge?: string;
  codeChallengeMethod?: string;
  scope: string;
  expiresAt: number;
};

type SignedTokenPayload = {
  typ: 'access' | 'refresh';
  client_id: string;
  scope: string;
  iat: number;
  exp: number;
};

const authCodes = new Map<string, AuthCode>();

function requireBearer(req: Request, res: Response, next: NextFunction): void {
  const header = req.headers.authorization ?? '';
  const match = header.match(/^Bearer\s+(.+)$/i);
  const presented = match?.[1]?.trim() ?? '';

  if (presented && isValidBearer(presented)) {
    next();
    return;
  }

  if (presented && verifySignedToken(presented, 'access')) {
    next();
    return;
  }

  res.status(401)
    .set('WWW-Authenticate', `Bearer realm="personal-crm-mcp", resource_metadata="${RESOURCE_METADATA_URL}", scope="${SCOPES.join(' ')}"`)
    .json({
      jsonrpc: '2.0',
      error: { code: -32001, message: 'Unauthorized: missing or invalid bearer token' },
      id: null,
    });
}

function isValidBearer(presented: string): boolean {
  return Boolean(BEARER_TOKEN) && safeEqual(presented, BEARER_TOKEN);
}

function safeEqual(a: string, b: string): boolean {
  const ab = Buffer.from(a);
  const bb = Buffer.from(b);
  if (ab.length !== bb.length) return false;
  return timingSafeEqual(ab, bb);
}

function requireOAuthClient(req: Request, res: Response): boolean {
  if (!OAUTH_CLIENT_ID || !OAUTH_CLIENT_SECRET) {
    oauthError(res, 400, 'invalid_request', 'OAuth client credentials are not configured on this server.');
    return false;
  }

  const body = req.body as Record<string, unknown>;
  let clientId = typeof body.client_id === 'string' ? body.client_id : '';
  let clientSecret = typeof body.client_secret === 'string' ? body.client_secret : '';

  const basic = parseBasicAuth(req.headers.authorization);
  if (basic) {
    clientId = basic.clientId;
    clientSecret = basic.clientSecret;
  }

  if (!safeEqual(clientId, OAUTH_CLIENT_ID) || !safeEqual(clientSecret, OAUTH_CLIENT_SECRET)) {
    oauthError(res, 401, 'invalid_client', 'Invalid OAuth client credentials.');
    return false;
  }

  return true;
}

function parseBasicAuth(header: string | undefined): { clientId: string; clientSecret: string } | null {
  const match = header?.match(/^Basic\s+(.+)$/i);
  if (!match) return null;

  const decoded = Buffer.from(match[1], 'base64').toString('utf8');
  const separator = decoded.indexOf(':');
  if (separator === -1) return null;

  return {
    clientId: decodeURIComponent(decoded.slice(0, separator)),
    clientSecret: decodeURIComponent(decoded.slice(separator + 1)),
  };
}

function signToken(payload: SignedTokenPayload): string {
  const encoded = Buffer.from(JSON.stringify(payload), 'utf8').toString('base64url');
  const sig = createHmac('sha256', TOKEN_SIGNING_SECRET).update(encoded).digest('base64url');
  return `mcp_${encoded}.${sig}`;
}

function verifySignedToken(token: string, type: SignedTokenPayload['typ']): SignedTokenPayload | null {
  if (!token.startsWith('mcp_')) return null;

  const parts = token.slice(4).split('.');
  if (parts.length !== 2) return null;

  const [encoded, signature] = parts;
  const expected = createHmac('sha256', TOKEN_SIGNING_SECRET).update(encoded).digest('base64url');
  if (!safeEqual(signature, expected)) return null;

  try {
    const payload = JSON.parse(Buffer.from(encoded, 'base64url').toString('utf8')) as SignedTokenPayload;
    if (payload.typ !== type || payload.exp < nowSeconds()) return null;
    return payload;
  } catch {
    return null;
  }
}

function issueTokens(clientId: string, scope: string): Record<string, string | number> {
  const now = nowSeconds();
  const accessToken = signToken({
    typ: 'access',
    client_id: clientId,
    scope,
    iat: now,
    exp: now + ACCESS_TOKEN_TTL_SECONDS,
  });
  const refreshToken = signToken({
    typ: 'refresh',
    client_id: clientId,
    scope,
    iat: now,
    exp: now + REFRESH_TOKEN_TTL_SECONDS,
  });

  return {
    access_token: accessToken,
    token_type: 'Bearer',
    expires_in: ACCESS_TOKEN_TTL_SECONDS,
    refresh_token: refreshToken,
    scope,
  };
}

function oauthError(res: Response, status: number, error: string, description: string): void {
  res.status(status).json({ error, error_description: description });
}

function randomSecret(bytes = 32): string {
  return randomBytes(bytes).toString('base64url');
}

function getQueryString(req: Request, key: string): string {
  const value = req.query[key];
  return Array.isArray(value) ? String(value[0] ?? '') : String(value ?? '');
}

function nowSeconds(): number {
  return Math.floor(Date.now() / 1000);
}

function stripTrailingSlash(value: string): string {
  return value.replace(/\/+$/, '');
}

async function sha256Base64Url(value: string): Promise<string> {
  const hash = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(value));
  return Buffer.from(hash).toString('base64url');
}

function metadataResponse(): Record<string, unknown> {
  return {
    issuer: ISSUER_URL,
    authorization_endpoint: `${ISSUER_URL}/oauth/authorize`,
    token_endpoint: `${ISSUER_URL}/oauth/token`,
    revocation_endpoint: `${ISSUER_URL}/oauth/revoke`,
    response_types_supported: ['code'],
    response_modes_supported: ['query'],
    grant_types_supported: ['authorization_code', 'refresh_token'],
    token_endpoint_auth_methods_supported: ['client_secret_basic', 'client_secret_post'],
    code_challenge_methods_supported: ['S256', 'plain'],
    scopes_supported: SCOPES,
  };
}

function protectedResourceResponse(): Record<string, unknown> {
  return {
    resource: EXTERNAL_URL,
    authorization_servers: [ISSUER_URL],
    scopes_supported: SCOPES,
    bearer_methods_supported: ['header'],
    resource_name: 'Personal Outreach CRM MCP',
  };
}

// ---------------------------------------------------------------------------
// Express app
// ---------------------------------------------------------------------------
const transports: Record<string, StreamableHTTPServerTransport> = {};

const app = express();
app.use(express.urlencoded({ extended: false }));
app.use(express.json({ limit: '4mb' }));

// OAuth / MCP discovery ------------------------------------------------------
app.get('/.well-known/oauth-protected-resource', (_req: Request, res: Response) => {
  res.json(protectedResourceResponse());
});

app.get('/.well-known/oauth-protected-resource/*path', (_req: Request, res: Response) => {
  res.json(protectedResourceResponse());
});

app.get('/.well-known/oauth-authorization-server', (_req: Request, res: Response) => {
  res.json(metadataResponse());
});

app.get('/.well-known/oauth-authorization-server/*path', (_req: Request, res: Response) => {
  res.json(metadataResponse());
});

app.get('/.well-known/openid-configuration', (_req: Request, res: Response) => {
  res.json({
    ...metadataResponse(),
    jwks_uri: `${ISSUER_URL}/oauth/jwks`,
    subject_types_supported: ['public'],
    id_token_signing_alg_values_supported: ['none'],
  });
});

app.get('/oauth/jwks', (_req: Request, res: Response) => {
  res.json({ keys: [] });
});

app.get('/oauth/authorize', (req: Request, res: Response) => {
  const responseType = getQueryString(req, 'response_type');
  const clientId = getQueryString(req, 'client_id');
  const redirectUri = getQueryString(req, 'redirect_uri');
  const state = getQueryString(req, 'state');
  const scope = getQueryString(req, 'scope') || SCOPES.join(' ');
  const codeChallenge = getQueryString(req, 'code_challenge');
  const codeChallengeMethod = getQueryString(req, 'code_challenge_method') || (codeChallenge ? 'plain' : undefined);

  if (responseType !== 'code') {
    oauthError(res, 400, 'unsupported_response_type', 'Only authorization code flow is supported.');
    return;
  }
  if (!OAUTH_CLIENT_ID || !safeEqual(clientId, OAUTH_CLIENT_ID)) {
    oauthError(res, 400, 'invalid_client', 'Unknown OAuth client.');
    return;
  }
  if (!redirectUri || !/^https?:\/\//i.test(redirectUri)) {
    oauthError(res, 400, 'invalid_request', 'A valid redirect_uri is required.');
    return;
  }

  const code = randomSecret(32);
  authCodes.set(code, {
    clientId,
    redirectUri,
    codeChallenge: codeChallenge || undefined,
    codeChallengeMethod,
    scope,
    expiresAt: Date.now() + 5 * 60 * 1000,
  });

  const redirect = new URL(redirectUri);
  redirect.searchParams.set('code', code);
  if (state) redirect.searchParams.set('state', state);
  res.redirect(302, redirect.toString());
});

app.post('/oauth/token', async (req: Request, res: Response) => {
  if (!requireOAuthClient(req, res)) return;

  const body = req.body as Record<string, unknown>;
  const grantType = typeof body.grant_type === 'string' ? body.grant_type : '';

  if (grantType === 'refresh_token') {
    const refreshToken = typeof body.refresh_token === 'string' ? body.refresh_token : '';
    const refreshPayload = verifySignedToken(refreshToken, 'refresh');
    if (!refreshPayload) {
      oauthError(res, 400, 'invalid_grant', 'Invalid refresh token.');
      return;
    }
    res.json(issueTokens(refreshPayload.client_id, refreshPayload.scope));
    return;
  }

  if (grantType !== 'authorization_code') {
    oauthError(res, 400, 'unsupported_grant_type', 'Only authorization_code and refresh_token grants are supported.');
    return;
  }

  const code = typeof body.code === 'string' ? body.code : '';
  const redirectUri = typeof body.redirect_uri === 'string' ? body.redirect_uri : '';
  const codeVerifier = typeof body.code_verifier === 'string' ? body.code_verifier : '';
  const authCode = authCodes.get(code);

  if (!authCode || authCode.expiresAt < Date.now()) {
    authCodes.delete(code);
    oauthError(res, 400, 'invalid_grant', 'Invalid or expired authorization code.');
    return;
  }
  if (authCode.redirectUri !== redirectUri) {
    oauthError(res, 400, 'invalid_grant', 'redirect_uri does not match the authorization request.');
    return;
  }
  if (authCode.codeChallenge) {
    if (!codeVerifier) {
      oauthError(res, 400, 'invalid_grant', 'code_verifier is required.');
      return;
    }
    const expected = authCode.codeChallengeMethod === 'S256'
      ? await sha256Base64Url(codeVerifier)
      : codeVerifier;
    if (!safeEqual(expected, authCode.codeChallenge)) {
      oauthError(res, 400, 'invalid_grant', 'Invalid PKCE code_verifier.');
      return;
    }
  }

  authCodes.delete(code);
  res.json(issueTokens(authCode.clientId, authCode.scope));
});

app.post('/oauth/revoke', (req: Request, res: Response) => {
  if (!requireOAuthClient(req, res)) return;
  // Access and refresh tokens are stateless; short TTL handles expiry.
  res.status(200).send('');
});

// Health check (unauthenticated, for load balancers / debugging) -------------
app.get('/health', (_req: Request, res: Response) => {
  res.json({
    status: 'ok',
    transport: 'streamable-http',
    protocol: '2025-03-26',
    sessions: Object.keys(transports).length,
    uptime_s: Math.round(process.uptime()),
    auth: OAUTH_CLIENT_ID ? 'oauth' : 'bearer',
  });
});

// ---------------------------------------------------------------------------
// Session registry: one transport + Server per active session
// ---------------------------------------------------------------------------

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
