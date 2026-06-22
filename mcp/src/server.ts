import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import {
  CallToolRequestSchema,
  ListResourcesRequestSchema,
  ListToolsRequestSchema,
  ReadResourceRequestSchema,
} from '@modelcontextprotocol/sdk/types.js';
import { crm } from './crm-client.js';

// ---------------------------------------------------------------------------
// Server factory
//
// Builds a fully-configured MCP Server with all CRM tools and resources
// registered. A factory (rather than a module-level singleton) is required
// because the Streamable HTTP transport creates one Server instance per
// session, while the stdio transport uses a single instance. Both entry
// points (index.ts = stdio, http.ts = HTTP/SSE) call this same function so
// the exposed tools and behaviour are always identical.
// ---------------------------------------------------------------------------
export function createCrmServer(): Server {
  const server = new Server(
    { name: 'personal-crm', version: '1.0.0' },
    { capabilities: { tools: {}, resources: {} } },
  );

  // -------------------------------------------------------------------------
  // Tools
  // -------------------------------------------------------------------------
  server.setRequestHandler(ListToolsRequestSchema, async () => ({
    tools: [
      {
        name: 'crm_dashboard_summary',
        description: 'Get CRM dashboard summary: open opportunities, follow-ups due, pending replies, and suggested next actions.',
        inputSchema: { type: 'object', properties: {}, required: [] },
      },
      {
        name: 'crm_search_contacts',
        description: 'Search CRM contacts by name, email, or company.',
        inputSchema: {
          type: 'object',
          properties: {
            q:            { type: 'string', description: 'Search text' },
            email:        { type: 'string', description: 'Exact email lookup' },
            organization: { type: 'string', description: 'Filter by company' },
            status:       { type: 'string', description: 'active, inactive, suppressed' },
            limit:        { type: 'number', description: 'Max results (1-100, default 20)' },
          },
        },
      },
      {
        name: 'crm_get_contact',
        description: 'Get a specific contact by ID.',
        inputSchema: {
          type: 'object',
          required: ['id'],
          properties: { id: { type: 'number', description: 'Contact ID' } },
        },
      },
      {
        name: 'crm_search_opportunities',
        description: 'Search CRM opportunities by keyword, type, status, priority, or deadline.',
        inputSchema: {
          type: 'object',
          properties: {
            q:               { type: 'string' },
            type:            { type: 'string', description: 'job, scholarship, research, grant, networking' },
            status:          { type: 'string', description: 'open, active, applied, closed' },
            priority:        { type: 'string', description: 'low, medium, high' },
            deadline_before: { type: 'string', description: 'ISO date YYYY-MM-DD' },
            deadline_after:  { type: 'string', description: 'ISO date YYYY-MM-DD' },
            limit:           { type: 'number' },
          },
        },
      },
      {
        name: 'crm_get_opportunity',
        description: 'Get a specific opportunity by ID.',
        inputSchema: {
          type: 'object',
          required: ['id'],
          properties: { id: { type: 'number', description: 'Opportunity ID' } },
        },
      },
      {
        name: 'crm_create_opportunity',
        description: 'Create a new opportunity in the CRM. Deduplicates by title + organization + URL.',
        inputSchema: {
          type: 'object',
          required: ['title', 'type', 'organization'],
          properties: {
            title:        { type: 'string' },
            type:         { type: 'string', enum: ['job', 'scholarship', 'research', 'grant', 'networking'] },
            organization: { type: 'string' },
            description:  { type: 'string' },
            url:          { type: 'string' },
            deadline:     { type: 'string', description: 'YYYY-MM-DD' },
            priority:     { type: 'string', enum: ['low', 'medium', 'high'] },
            notes:        { type: 'string' },
          },
        },
      },
      {
        name: 'crm_add_note',
        description: 'Append a note to a contact or opportunity.',
        inputSchema: {
          type: 'object',
          required: ['entity_type', 'entity_id', 'note'],
          properties: {
            entity_type: { type: 'string', enum: ['contact', 'opportunity'] },
            entity_id:   { type: 'number' },
            note:        { type: 'string' },
          },
        },
      },
      {
        name: 'crm_create_email_draft',
        description: 'Save an email draft for user review. NEVER sends automatically. User must review and send from the CRM.',
        inputSchema: {
          type: 'object',
          required: ['contact_id', 'subject', 'body'],
          properties: {
            contact_id:     { type: 'number' },
            opportunity_id: { type: 'number' },
            subject:        { type: 'string' },
            body:           { type: 'string' },
            draft_type:     { type: 'string', enum: ['initial_outreach', 'follow_up', 'thank_you', 'general'] },
            tone:           { type: 'string', enum: ['professional', 'casual', 'formal'] },
          },
        },
      },
      {
        name: 'crm_create_followup',
        description: 'Schedule a reminder-only follow-up. Auto-sending is disabled in MVP.',
        inputSchema: {
          type: 'object',
          required: ['contact_id', 'due_at'],
          properties: {
            contact_id:        { type: 'number' },
            opportunity_id:    { type: 'number' },
            due_at:            { type: 'string', description: 'ISO 8601 datetime' },
            notes:             { type: 'string' },
            suggested_subject: { type: 'string' },
            suggested_body:    { type: 'string' },
          },
        },
      },
      {
        name: 'crm_recent_replies',
        description: 'Get recent inbound replies matched to outbound emails.',
        inputSchema: {
          type: 'object',
          properties: {
            since:          { type: 'string', description: 'ISO datetime' },
            opportunity_id: { type: 'number' },
            contact_id:     { type: 'number' },
            sentiment:      { type: 'string', enum: ['positive', 'neutral', 'negative'] },
            limit:          { type: 'number' },
          },
        },
      },
      {
        name: 'crm_ingest_opportunities',
        description: 'Bulk-ingest up to 50 opportunities from external sources (n8n, scrapers). Deduplicates automatically.',
        inputSchema: {
          type: 'object',
          required: ['items'],
          properties: {
            items: {
              type: 'array',
              items: {
                type: 'object',
                required: ['title', 'type', 'organization'],
                properties: {
                  title:        { type: 'string' },
                  type:         { type: 'string' },
                  organization: { type: 'string' },
                  description:  { type: 'string' },
                  url:          { type: 'string' },
                  deadline:     { type: 'string' },
                  priority:     { type: 'string' },
                  notes:        { type: 'string' },
                },
              },
            },
          },
        },
      },
      {
        name: 'crm_create_contact',
        description: 'Create a new contact in the CRM.',
        inputSchema: {
          type: 'object',
          required: ['first_name', 'last_name'],
          properties: {
            first_name:   { type: 'string' },
            last_name:    { type: 'string' },
            email:        { type: 'string' },
            organization: { type: 'string' },
            title:        { type: 'string' },
            phone:        { type: 'string' },
            linkedin_url: { type: 'string' },
            notes:        { type: 'string' },
          },
        },
      },
      {
        name: 'crm_link_contact_to_opportunity',
        description: 'Link an existing contact to an opportunity.',
        inputSchema: {
          type: 'object',
          required: ['opportunity_id', 'contact_id'],
          properties: {
            opportunity_id: { type: 'number' },
            contact_id:     { type: 'number' },
            role:           { type: 'string', description: 'e.g. hiring_manager, recruiter, referral' },
          },
        },
      },
      {
        name: 'crm_list_documents',
        description: 'List documents stored in the CRM, optionally scoped to a contact or opportunity.',
        inputSchema: {
          type: 'object',
          properties: {
            contact_id:     { type: 'number', description: 'Filter to a specific contact' },
            opportunity_id: { type: 'number', description: 'Filter to a specific opportunity' },
            limit:          { type: 'number', description: 'Max results (default 20)' },
          },
        },
      },
      {
        name: 'crm_get_document',
        description: 'Get a specific document by ID, including its version history and entity links.',
        inputSchema: {
          type: 'object',
          required: ['id'],
          properties: { id: { type: 'number', description: 'Document ID' } },
        },
      },
      {
        name: 'crm_get_email_draft_preview',
        description: 'Get a rendered HTML preview of an email draft, with signature and template applied.',
        inputSchema: {
          type: 'object',
          required: ['id'],
          properties: { id: { type: 'number', description: 'Email draft ID' } },
        },
      },
    ],
  }));

  server.setRequestHandler(CallToolRequestSchema, async (request) => {
    const { name, arguments: args } = request.params;
    const a = (args ?? {}) as Record<string, unknown>;

    try {
      let result: unknown;

      switch (name) {
        case 'crm_dashboard_summary':
          result = await crm.get('/dashboard-summary');
          break;

        case 'crm_search_contacts':
          result = await crm.get('/contacts?' + new URLSearchParams(
            Object.fromEntries(Object.entries(a).map(([k, v]) => [k, String(v)]))
          ).toString());
          break;

        case 'crm_get_contact':
          result = await crm.get(`/contacts/${a.id}`);
          break;

        case 'crm_search_opportunities':
          result = await crm.get('/opportunities?' + new URLSearchParams(
            Object.fromEntries(Object.entries(a).map(([k, v]) => [k, String(v)]))
          ).toString());
          break;

        case 'crm_get_opportunity':
          result = await crm.get(`/opportunities/${a.id}`);
          break;

        case 'crm_create_opportunity':
          result = await crm.post('/opportunities', a);
          break;

        case 'crm_add_note': {
          const entityType = a.entity_type === 'contact' ? 'contacts' : 'opportunities';
          result = await crm.post(`/${entityType}/${a.entity_id}/notes`, { note: a.note });
          break;
        }

        case 'crm_create_email_draft':
          result = await crm.post('/email-drafts', a);
          break;

        case 'crm_create_followup':
          result = await crm.post('/follow-ups', a);
          break;

        case 'crm_recent_replies':
          result = await crm.get('/replies/recent?' + new URLSearchParams(
            Object.fromEntries(Object.entries(a).map(([k, v]) => [k, String(v)]))
          ).toString());
          break;

        case 'crm_ingest_opportunities':
          result = await crm.post('/ingestion/opportunities', a);
          break;

        case 'crm_create_contact':
          result = await crm.post('/contacts', a);
          break;

        case 'crm_link_contact_to_opportunity':
          result = await crm.post(`/opportunities/${a.opportunity_id}/contacts`, { contact_id: a.contact_id, role: a.role });
          break;

        case 'crm_list_documents': {
          const limit = Number(a.limit) || 20;
          if (a.contact_id) {
            result = await crm.get(`/contacts/${Number(a.contact_id)}/documents?limit=${limit}`);
          } else if (a.opportunity_id) {
            result = await crm.get(`/opportunities/${Number(a.opportunity_id)}/documents?limit=${limit}`);
          } else {
            result = await crm.get(`/documents?limit=${limit}`);
          }
          break;
        }

        case 'crm_get_document':
          result = await crm.get(`/documents/${a.id}`);
          break;

        case 'crm_get_email_draft_preview':
          result = await crm.get(`/email-drafts/${a.id}/rendered-preview`);
          break;

        default:
          return { content: [{ type: 'text', text: `Unknown tool: ${name}` }], isError: true };
      }

      return {
        content: [{ type: 'text', text: JSON.stringify(result, null, 2) }],
      };
    } catch (err) {
      const message = err instanceof Error ? err.message : String(err);
      return { content: [{ type: 'text', text: `Error: ${message}` }], isError: true };
    }
  });

  // -------------------------------------------------------------------------
  // Resources
  // -------------------------------------------------------------------------
  const RESOURCES = [
    { uri: 'crm://dashboard/summary',           name: 'Dashboard Summary',       description: 'Current CRM dashboard summary' },
    { uri: 'crm://opportunities/recent',         name: 'Recent Opportunities',    description: 'Most recently updated opportunities' },
    { uri: 'crm://opportunities/due-soon',       name: 'Opportunities Due Soon',  description: 'Opportunities with deadlines in the next 7 days' },
    { uri: 'crm://contacts/recent',              name: 'Recent Contacts',         description: 'Most recently created contacts' },
    { uri: 'crm://followups/due',                name: 'Follow-ups Due',          description: 'Follow-ups due today or overdue' },
    { uri: 'crm://email-drafts/pending-review',  name: 'Pending Email Drafts',    description: 'Email drafts awaiting user review' },
  ];

  server.setRequestHandler(ListResourcesRequestSchema, async () => ({
    resources: RESOURCES.map(r => ({ ...r, mimeType: 'application/json' })),
  }));

  server.setRequestHandler(ReadResourceRequestSchema, async (request) => {
    const { uri } = request.params;

    let data: unknown;

    switch (uri) {
      case 'crm://dashboard/summary':
        data = await crm.get('/dashboard-summary');
        break;
      case 'crm://opportunities/recent':
        data = await crm.get('/opportunities?limit=10');
        break;
      case 'crm://opportunities/due-soon':
        data = await crm.get(`/opportunities?deadline_before=${daysFromNow(7)}&deadline_after=${today()}&limit=20`);
        break;
      case 'crm://contacts/recent':
        data = await crm.get('/contacts?limit=10');
        break;
      case 'crm://followups/due':
        data = await crm.get('/follow-ups/due');
        break;
      case 'crm://email-drafts/pending-review':
        data = await crm.get('/email-drafts');
        break;
      default:
        throw new Error(`Unknown resource: ${uri}`);
    }

    return {
      contents: [{
        uri,
        mimeType: 'application/json',
        text: JSON.stringify(data, null, 2),
      }],
    };
  });

  return server;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function today(): string {
  return new Date().toISOString().slice(0, 10);
}

function daysFromNow(n: number): string {
  const d = new Date();
  d.setDate(d.getDate() + n);
  return d.toISOString().slice(0, 10);
}
