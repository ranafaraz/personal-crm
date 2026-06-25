// ---------------------------------------------------------------------------
// CRM HTTP client for the MCP server.
//
// Adds three behaviours on top of a thin fetch wrapper, per the connector spec:
//   1. Built-in pacing  — all writes (POST/PATCH/DELETE) are serialized through
//      a single queue and automatically retried while honoring the server's
//      Retry-After header on 429, so the agent never has to hand-pace.
//   2. Idempotency       — a write may carry an idempotency key; a repeated call
//      with the same key returns the first call's result instead of issuing a
//      second request, preventing the duplicate-draft problem retries caused.
//   3. Env aliasing      — reads POCRM_* first (the names used in the connector
//      docs / .env.example), falling back to the legacy CRM_* names.
// ---------------------------------------------------------------------------

const BASE_URL = (process.env.POCRM_BASE_URL ?? process.env.CRM_BASE_URL ?? '').replace(/\/$/, '');
const API_KEY  = process.env.POCRM_API_KEY  ?? process.env.CRM_API_KEY  ?? '';

if (!BASE_URL || !API_KEY) {
  console.error('ERROR: POCRM_BASE_URL and POCRM_API_KEY (or legacy CRM_BASE_URL/CRM_API_KEY) must be set in environment.');
  process.exit(1);
}

// Derived sibling API bases (same host, different version prefix).
const SOCIAL_BASE    = BASE_URL.replace(/\/api\/gpt\/v1$/, '/api/social/v1');
const PROPOSALS_BASE = BASE_URL.replace(/\/api\/gpt\/v1$/, '/api/proposals/v1');

const MAX_RETRIES        = 4;
const DEFAULT_BACKOFF_MS = 1000;
const IDEMPOTENCY_TTL_MS = 10 * 60 * 1000; // remember keys for 10 minutes

type WriteOpts = { idempotencyKey?: string };

const sleep = (ms: number) => new Promise<void>(r => setTimeout(r, ms));

// Serializes all writes: each enqueues onto the tail of this promise chain so
// at most one write is in flight at a time. Reads are not serialized.
let writeChain: Promise<unknown> = Promise.resolve();

// Idempotency cache: key -> { result, expires }. A repeated write with a seen
// key short-circuits to the stored result.
const idempotencyCache = new Map<string, { result: unknown; expires: number }>();

function cacheGet(key: string): unknown | undefined {
  const hit = idempotencyCache.get(key);
  if (!hit) return undefined;
  if (hit.expires < Date.now()) {
    idempotencyCache.delete(key);
    return undefined;
  }
  return hit.result;
}

function cachePut(key: string, result: unknown): void {
  idempotencyCache.set(key, { result, expires: Date.now() + IDEMPOTENCY_TTL_MS });
}

// Core fetch with automatic Retry-After honoring on 429.
async function rawFetch(url: string, options: RequestInit, extraHeaders: Record<string, string> = {}): Promise<unknown> {
  let attempt = 0;

  while (true) {
    const res = await fetch(url, {
      ...options,
      headers: {
        'X-Api-Key': API_KEY,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...extraHeaders,
        ...(options.headers ?? {}),
      },
    });

    if (res.status === 429 && attempt < MAX_RETRIES) {
      const retryAfter = Number(res.headers.get('Retry-After'));
      const waitMs = Number.isFinite(retryAfter) && retryAfter > 0
        ? retryAfter * 1000
        : DEFAULT_BACKOFF_MS * Math.pow(2, attempt); // exponential fallback
      attempt += 1;
      await sleep(waitMs);
      continue;
    }

    const body = await res.json().catch(() => ({ error: 'Non-JSON response' }));

    if (!res.ok) {
      const msg = (body as Record<string, unknown>)?.error ?? res.statusText;
      throw new Error(`CRM API error ${res.status}: ${msg}`);
    }

    return body;
  }
}

// Enqueue a write so it runs after all prior writes complete. Honors the
// idempotency key (returns the cached result for a repeated key).
function enqueueWrite(url: string, options: RequestInit, opts: WriteOpts): Promise<unknown> {
  const key = opts.idempotencyKey;
  if (key) {
    const cached = cacheGet(key);
    if (cached !== undefined) return Promise.resolve(cached);
  }

  const run = writeChain.then(async () => {
    // Re-check inside the queue: a concurrent enqueue with the same key may
    // have completed while we waited our turn.
    if (key) {
      const cached = cacheGet(key);
      if (cached !== undefined) return cached;
    }
    const headers: Record<string, string> = key ? { 'Idempotency-Key': key } : {};
    const result = await rawFetch(url, options, headers);
    if (key) cachePut(key, result);
    return result;
  });

  // Keep the chain alive even if this write rejects.
  writeChain = run.then(() => undefined, () => undefined);
  return run;
}

export const crm = {
  // Reads — not serialized, still retry on 429.
  get: (path: string) => rawFetch(`${BASE_URL}${path}`, {}),

  // Writes — serialized + idempotent + Retry-After aware.
  post: (path: string, data: unknown, opts: WriteOpts = {}) =>
    enqueueWrite(`${BASE_URL}${path}`, { method: 'POST', body: JSON.stringify(data) }, opts),
  patch: (path: string, data: unknown, opts: WriteOpts = {}) =>
    enqueueWrite(`${BASE_URL}${path}`, { method: 'PATCH', body: JSON.stringify(data) }, opts),
  delete: (path: string, opts: WriteOpts = {}) =>
    enqueueWrite(`${BASE_URL}${path}`, { method: 'DELETE' }, opts),

  // Social Studio API (/api/social/v1).
  postSocial: (path: string, data: unknown, opts: WriteOpts = {}) =>
    enqueueWrite(`${SOCIAL_BASE}${path}`, { method: 'POST', body: JSON.stringify(data) }, opts),

  // Proposals API (/api/proposals/v1).
  postProposals: (path: string, data: unknown, opts: WriteOpts = {}) =>
    enqueueWrite(`${PROPOSALS_BASE}${path}`, { method: 'POST', body: JSON.stringify(data) }, opts),
};
