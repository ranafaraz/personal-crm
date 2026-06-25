#!/usr/bin/env node
// ---------------------------------------------------------------------------
// CRM connector smoke test.
//
// Exercises the REST surface the MCP server depends on, end to end:
//   1. GET  /me                          — auth + identity
//   2. POST /contacts   (NO email)       — must succeed (Bug 1 fix)
//   3. POST /opportunities               — must succeed
//   4. POST /email-drafts                — create a draft for that contact
//   5. POST /email-drafts/{id}/schedule  — schedule +5 min (does NOT send)
//   6. cleanup: unschedule + delete draft, contact, opportunity
//
// Usage:
//   POCRM_BASE_URL=https://crm.dexdevs.com/api/gpt/v1 \
//   POCRM_API_KEY=pocrm_live_xxx node scripts/smoke.mjs
//
// Reads POCRM_* (preferred) or legacy CRM_* env vars. Never prints the key.
// Exit code 0 = all steps passed, 1 = a step failed.
// ---------------------------------------------------------------------------

const BASE = (process.env.POCRM_BASE_URL ?? process.env.CRM_BASE_URL ?? '').replace(/\/$/, '');
const KEY  = process.env.POCRM_API_KEY  ?? process.env.CRM_API_KEY  ?? '';

if (!BASE || !KEY) {
  console.error('FAIL: set POCRM_BASE_URL and POCRM_API_KEY (or legacy CRM_* names).');
  process.exit(1);
}

let failed = 0;
const created = { noEmailContactId: null, contactId: null, opportunityId: null, draftId: null };

async function api(method, path, body) {
  const res = await fetch(`${BASE}${path}`, {
    method,
    headers: { 'X-Api-Key': KEY, 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: body ? JSON.stringify(body) : undefined,
  });
  const json = await res.json().catch(() => ({}));
  return { status: res.status, json };
}

function check(label, ok, detail = '') {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? '  — ' + detail : ''}`);
  if (!ok) failed++;
  return ok;
}

// Drafts/opportunities responses wrap the row under `data`; contacts too.
const rowId = (j) => j?.data?.id ?? j?.id ?? null;

try {
  // 1. /me
  let r = await api('GET', '/me');
  check('GET /me', r.status === 200, `HTTP ${r.status}`);

  // 2. contact with NO email must succeed (Bug 1 — application-portal case)
  r = await api('POST', '/contacts', { full_name: 'Smoke NoEmail', company: 'Smoke Portal Co' });
  created.noEmailContactId = rowId(r.json);
  check('POST /contacts (no email) succeeds', (r.status === 200 || r.status === 201) && created.noEmailContactId, `HTTP ${r.status} id=${created.noEmailContactId}`);

  // 2b. emailed contact for the draft flow (a no-email contact cannot be a recipient)
  r = await api('POST', '/contacts', { full_name: 'Smoke Recipient', email: `smoke+${Date.now()}@example.test` });
  created.contactId = rowId(r.json);
  check('POST /contacts (with email)', (r.status === 200 || r.status === 201) && created.contactId, `HTTP ${r.status} id=${created.contactId}`);

  // 3. opportunity
  r = await api('POST', '/opportunities', { title: 'Smoke Opportunity', type: 'job', organization: 'Smoke Org' });
  created.opportunityId = rowId(r.json);
  check('POST /opportunities', (r.status === 200 || r.status === 201) && created.opportunityId, `HTTP ${r.status} id=${created.opportunityId}`);

  // 4. draft
  if (created.contactId) {
    r = await api('POST', '/email-drafts', {
      contact_id: created.contactId,
      opportunity_id: created.opportunityId,
      subject: 'Smoke test draft',
      body: 'This is a smoke-test draft. It is scheduled, never sent.',
    });
    created.draftId = rowId(r.json);
    check('POST /email-drafts', (r.status === 200 || r.status === 201) && created.draftId, `HTTP ${r.status} id=${created.draftId}`);
  }

  // 5. schedule +5 min (no real send). NOTE: a future A3 enhancement adds a
  //    dry_run flag to /email-drafts/{id}/send for a non-committing send test;
  //    scheduling is already inherently non-sending, so it is used here.
  if (created.draftId) {
    const sendAt = new Date(Date.now() + 5 * 60 * 1000).toISOString();
    r = await api('POST', `/email-drafts/${created.draftId}/schedule`, { send_at: sendAt });
    check('POST /email-drafts/{id}/schedule (+5m)', r.status === 200, `HTTP ${r.status}`);
  }
} catch (err) {
  check('unexpected error', false, String(err));
} finally {
  // 6. cleanup — best effort, always runs.
  if (created.draftId) {
    await api('POST', `/email-drafts/${created.draftId}/unschedule`).catch(() => {});
    const r = await api('DELETE', `/email-drafts/${created.draftId}`);
    check('cleanup: delete draft', r.status === 200 || r.status === 204, `HTTP ${r.status}`);
  }
  if (created.contactId) {
    const r = await api('DELETE', `/contacts/${created.contactId}`);
    check('cleanup: delete contact', r.status === 200 || r.status === 204, `HTTP ${r.status}`);
  }
  if (created.noEmailContactId) {
    const r = await api('DELETE', `/contacts/${created.noEmailContactId}`);
    check('cleanup: delete no-email contact', r.status === 200 || r.status === 204, `HTTP ${r.status}`);
  }
  if (created.opportunityId) {
    const r = await api('DELETE', `/opportunities/${created.opportunityId}`);
    check('cleanup: delete opportunity', r.status === 200 || r.status === 204, `HTTP ${r.status}`);
  }
}

console.log(failed === 0 ? '\nSMOKE OK' : `\nSMOKE FAILED (${failed} step(s))`);
process.exit(failed === 0 ? 0 : 1);
