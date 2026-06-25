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
        description: 'Create an email draft. For the MCP client the confirmation gate is bypassed, so the draft is auto-approved and ready to send immediately via crm_send_draft (it is still not sent until you call that). Creating a draft alone does not send anything.',
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
      {
        name: 'crm_pipeline_execute',
        description: 'Run a complete outreach pipeline in one call: contact upsert → opportunity create (dedup by title+company) → email draft → follow-up reminder → tags. For the MCP client the draft is auto-approved (the confirmation gate is bypassed); use crm_send_draft afterward to send it. Returns the created/upserted contact, opportunity, draft, and follow-up IDs plus any errors (e.g. draft skipped for a suppressed contact).',
        inputSchema: {
          type: 'object',
          required: ['pipeline', 'data'],
          properties: {
            pipeline: {
              type: 'string',
              enum: ['job_application', 'networking_outreach', 'freelance_pitch', 'research_contact', 'grant_application'],
              description: 'Pipeline type; maps to the opportunity type.',
            },
            data: {
              type: 'object',
              required: ['company_name', 'role_title', 'contact_email', 'email_subject', 'email_body'],
              properties: {
                company_name:       { type: 'string', description: 'Organization name (max 255)' },
                role_title:         { type: 'string', description: 'Opportunity title (max 255)' },
                contact_email:      { type: 'string', description: 'Contact email (used to upsert the contact)' },
                contact_name:       { type: 'string', description: 'Full name; split into first/last' },
                email_subject:      { type: 'string', description: 'Draft subject (max 500)' },
                email_body:         { type: 'string', description: 'Draft body (max 50000)' },
                follow_up_days:     { type: 'number', description: 'Days until the follow-up reminder (1-90, default 7)' },
                tags:               { type: 'array', items: { type: 'string' }, description: 'Tag names applied to the opportunity (max 20)' },
                apply_url:          { type: 'string', description: 'Application/listing URL' },
                opportunity_status: { type: 'string', enum: ['draft', 'active', 'waiting_reply', 'replied', 'interview', 'offer', 'rejected', 'withdrawn', 'closed'], description: 'Opportunity status (default draft)' },
              },
            },
          },
        },
      },
      {
        name: 'crm_bulk_create_opportunities',
        description: 'Create up to 20 opportunities in one call. Deduplicates by title + company (existing ones are reported under "skipped"). Returns created, skipped, and errors arrays.',
        inputSchema: {
          type: 'object',
          required: ['opportunities'],
          properties: {
            opportunities: {
              type: 'array',
              description: '1-20 opportunities',
              items: {
                type: 'object',
                required: ['title', 'company'],
                properties: {
                  title:    { type: 'string' },
                  company:  { type: 'string' },
                  type:     { type: 'string', enum: ['job', 'scholarship', 'research', 'grant', 'networking'] },
                  status:   { type: 'string', enum: ['draft', 'active', 'waiting_reply', 'replied', 'interview', 'offer', 'rejected', 'withdrawn', 'closed'] },
                  priority: { type: 'string', enum: ['low', 'medium', 'high', 'urgent'] },
                  apply_url:{ type: 'string' },
                  notes:    { type: 'string' },
                  tags:     { type: 'array', items: { type: 'string' }, description: 'Up to 10 tag names' },
                },
              },
            },
          },
        },
      },
      {
        name: 'crm_bulk_create_contacts',
        description: 'Create up to 20 contacts in one call. Deduplicates by email (existing ones are reported under "skipped"). Returns created, skipped, and errors arrays.',
        inputSchema: {
          type: 'object',
          required: ['contacts'],
          properties: {
            contacts: {
              type: 'array',
              description: '1-20 contacts',
              items: {
                type: 'object',
                required: ['email'],
                properties: {
                  email:        { type: 'string' },
                  first_name:   { type: 'string' },
                  last_name:    { type: 'string' },
                  company:      { type: 'string' },
                  job_title:    { type: 'string' },
                  phone:        { type: 'string' },
                  linkedin_url: { type: 'string' },
                  notes:        { type: 'string' },
                  status:       { type: 'string', enum: ['active', 'inactive', 'suppressed', 'bounced'] },
                },
              },
            },
          },
        },
      },
      {
        name: 'crm_bulk_create_drafts',
        description: 'Create up to 20 email drafts in one call (one per existing contact_id). Skips suppressed contacts and reports them under "errors". For the MCP client drafts are auto-approved; use crm_send_draft to send each. Returns created and errors arrays.',
        inputSchema: {
          type: 'object',
          required: ['drafts'],
          properties: {
            drafts: {
              type: 'array',
              description: '1-20 drafts',
              items: {
                type: 'object',
                required: ['contact_id', 'subject', 'body'],
                properties: {
                  contact_id:     { type: 'number', description: 'Existing contact ID' },
                  subject:        { type: 'string', description: 'Max 500 chars' },
                  body:           { type: 'string', description: 'Max 50000 chars' },
                  opportunity_id: { type: 'number', description: 'Optional opportunity to link' },
                },
              },
            },
          },
        },
      },
      {
        name: 'crm_send_test_email',
        description: 'Send a test copy of an email draft to a verification address. Does NOT send to the real recipient and does NOT change the draft status. Use this to preview rendering before sending for real.',
        inputSchema: {
          type: 'object',
          required: ['id', 'test_email'],
          properties: {
            id:         { type: 'number', description: 'Email draft ID' },
            test_email: { type: 'string', description: 'Address to receive the test copy' },
          },
        },
      },
      {
        name: 'crm_send_draft',
        description: 'Send an email draft. For the MCP client the send happens synchronously (the confirmation gate is bypassed) and the response reflects the real delivery outcome. The draft must be in "draft" status and the recipient must not be suppressed.',
        inputSchema: {
          type: 'object',
          required: ['id'],
          properties: { id: { type: 'number', description: 'Email draft ID' } },
        },
      },
      {
        name: 'crm_publish_linkedin_post',
        description: 'Publish or schedule a LinkedIn post (Social Studio). For the MCP client this bypasses the confirmation gate and publishes/schedules directly. The post must be in "draft" or "approved" status. Use action="publish_now" to queue an immediate publish, or action="schedule" with scheduled_at (a future datetime) to schedule it.',
        inputSchema: {
          type: 'object',
          required: ['id', 'action'],
          properties: {
            id:           { type: 'number', description: 'LinkedIn post ID' },
            action:       { type: 'string', enum: ['publish_now', 'schedule'], description: 'publish_now or schedule' },
            scheduled_at: { type: 'string', description: 'Future datetime (required when action=schedule)' },
            timezone:     { type: 'string', description: 'IANA timezone for scheduled_at (default UTC)' },
          },
        },
      },

      // -------------------------------------------------------------------
      // Reads
      // -------------------------------------------------------------------
      {
        name: 'crm_check_duplicate',
        description: 'Check whether an opportunity already exists for a given company + role before creating one. Returns { duplicate, deleted, opportunity }.',
        inputSchema: {
          type: 'object',
          required: ['company', 'role'],
          properties: {
            company: { type: 'string', description: 'Organization / company name' },
            role:    { type: 'string', description: 'Role / position title' },
          },
        },
      },
      {
        name: 'crm_list_drafts',
        description: 'List email drafts, filtered by opportunity_id, contact_id, and/or status, with pagination. Defaults to drafts in "draft" status when no status is given.',
        inputSchema: {
          type: 'object',
          properties: {
            opportunity_id: { type: 'number' },
            contact_id:     { type: 'number' },
            status:         { type: 'string', description: 'draft, scheduled, queued, sending, sent, failed, cancelled' },
            per_page:       { type: 'number', description: '1-100 (default 50)' },
            page:           { type: 'number', description: 'Page number (default 1)' },
          },
        },
      },
      {
        name: 'crm_list_followups_due',
        description: 'List follow-ups that are due today or overdue.',
        inputSchema: { type: 'object', properties: {} },
      },
      {
        name: 'crm_list_signatures',
        description: 'List the email signatures available for use in drafts.',
        inputSchema: { type: 'object', properties: {} },
      },

      // -------------------------------------------------------------------
      // Writes — updates & deletes
      // -------------------------------------------------------------------
      {
        name: 'crm_update_opportunity',
        description: 'Update fields on an existing opportunity.',
        inputSchema: {
          type: 'object',
          required: ['id'],
          properties: {
            id:              { type: 'number' },
            title:           { type: 'string' },
            organization:    { type: 'string' },
            description:     { type: 'string' },
            url:             { type: 'string' },
            status:          { type: 'string' },
            priority:        { type: 'string', enum: ['low', 'medium', 'high'] },
            deadline:        { type: 'string', description: 'YYYY-MM-DD' },
            notes:           { type: 'string' },
            idempotency_key: { type: 'string', description: 'Optional; a repeated call with the same key is a no-op that returns the first result.' },
          },
        },
      },
      {
        name: 'crm_delete_opportunity',
        description: 'Soft-delete an opportunity by ID.',
        inputSchema: {
          type: 'object',
          required: ['id'],
          properties: {
            id:              { type: 'number' },
            idempotency_key: { type: 'string' },
          },
        },
      },
      {
        name: 'crm_update_contact',
        description: 'Update fields on an existing contact.',
        inputSchema: {
          type: 'object',
          required: ['id'],
          properties: {
            id:              { type: 'number' },
            first_name:      { type: 'string' },
            last_name:       { type: 'string' },
            full_name:       { type: 'string' },
            email:           { type: 'string' },
            phone:           { type: 'string' },
            company:         { type: 'string' },
            job_title:       { type: 'string' },
            linkedin_url:    { type: 'string' },
            status:          { type: 'string', enum: ['active', 'suppressed', 'bounced'] },
            notes:           { type: 'string' },
            idempotency_key: { type: 'string' },
          },
        },
      },
      {
        name: 'crm_update_draft',
        description: 'Update an email draft (subject, body, signature, attachments). Only drafts in "draft" status can be edited.',
        inputSchema: {
          type: 'object',
          required: ['id'],
          properties: {
            id:              { type: 'number' },
            subject:         { type: 'string' },
            body:            { type: 'string' },
            signature_id:    { type: 'number' },
            attachment_ids:  { type: 'array', items: { type: 'number' }, description: 'Replaces the set of attachments sent with the draft' },
            idempotency_key: { type: 'string' },
          },
        },
      },
      {
        name: 'crm_schedule_draft',
        description: 'Schedule an email draft to be sent at a future time. Sets the draft status to "scheduled"; a worker sends it when the time arrives, honoring the same guardrails as crm_send_draft. Use crm_unschedule_draft to cancel.',
        inputSchema: {
          type: 'object',
          required: ['id', 'scheduled_at'],
          properties: {
            id:              { type: 'number', description: 'Email draft ID' },
            scheduled_at:    { type: 'string', description: 'ISO-8601 future datetime' },
            idempotency_key: { type: 'string' },
          },
        },
      },
      {
        name: 'crm_unschedule_draft',
        description: 'Cancel a scheduled send and revert the draft to "draft" status.',
        inputSchema: {
          type: 'object',
          required: ['id'],
          properties: {
            id:              { type: 'number', description: 'Email draft ID' },
            idempotency_key: { type: 'string' },
          },
        },
      },
      {
        name: 'crm_update_followup',
        description: 'Update a follow-up (due date, status, suggested subject/body, notes).',
        inputSchema: {
          type: 'object',
          required: ['id'],
          properties: {
            id:                { type: 'number' },
            due_at:            { type: 'string', description: 'ISO 8601 datetime' },
            status:            { type: 'string', description: 'pending, sent, cancelled' },
            suggested_subject: { type: 'string' },
            suggested_body:    { type: 'string' },
            notes:             { type: 'string' },
            idempotency_key:   { type: 'string' },
          },
        },
      },

      // -------------------------------------------------------------------
      // Writes — documents, attachments, proposals
      // -------------------------------------------------------------------
      {
        name: 'crm_register_document',
        description: 'Register an externally-hosted document by URL reference (no file upload). Use crm_upload_document instead when you have the raw file bytes.',
        inputSchema: {
          type: 'object',
          required: ['name', 'public_url', 'mime_type', 'size_bytes'],
          properties: {
            name:            { type: 'string', description: 'Display name (max 500)' },
            public_url:      { type: 'string', description: 'Publicly reachable URL (max 2048)' },
            mime_type:       { type: 'string', description: 'e.g. application/pdf' },
            size_bytes:      { type: 'number', description: 'File size in bytes (>= 1)' },
            document_type:   { type: 'string', enum: ['invoice', 'contract', 'agreement', 'statement_of_work', 'proposal', 'other'] },
            description:     { type: 'string' },
            opportunity_id:  { type: 'number' },
            contact_id:      { type: 'number' },
            email_draft_id:  { type: 'number' },
            follow_up_id:    { type: 'number' },
            idempotency_key: { type: 'string' },
          },
        },
      },
      {
        name: 'crm_upload_document',
        description: 'Upload a document from raw bytes (base64). The CRM hosts it and returns a public_url. Max 20 MB. Allowed: pdf, doc(x), xls(x), ppt(x), txt, csv, jpg, png, gif, webp.',
        inputSchema: {
          type: 'object',
          required: ['name', 'filename', 'file_base64'],
          properties: {
            name:            { type: 'string', description: 'Display name (max 500)' },
            filename:        { type: 'string', description: 'Original filename incl. extension (max 255)' },
            file_base64:     { type: 'string', description: 'Base64-encoded file contents' },
            document_type:   { type: 'string', enum: ['invoice', 'contract', 'agreement', 'statement_of_work', 'proposal', 'other'] },
            description:     { type: 'string' },
            opportunity_id:  { type: 'number' },
            contact_id:      { type: 'number' },
            email_draft_id:  { type: 'number' },
            follow_up_id:    { type: 'number' },
            idempotency_key: { type: 'string' },
          },
        },
      },
      {
        name: 'crm_register_attachment',
        description: 'Register an externally-hosted file as a sendable email attachment (by URL). Returns an attachment id you can pass to crm_link_attachment_to_draft. attachment_ids on a draft are SENT with the email (unlike reference-only linked documents).',
        inputSchema: {
          type: 'object',
          required: ['filename', 'public_url', 'mime_type', 'size_bytes'],
          properties: {
            filename:        { type: 'string', description: 'Filename incl. extension (max 500)' },
            public_url:      { type: 'string', description: 'Publicly reachable URL (max 2048)' },
            mime_type:       { type: 'string' },
            size_bytes:      { type: 'number', description: 'File size in bytes (>= 1)' },
            category:        { type: 'string', enum: ['invoice', 'contract', 'agreement', 'proposal', 'cover_letter', 'resume', 'tos', 'other'] },
            notes:           { type: 'string' },
            idempotency_key: { type: 'string' },
          },
        },
      },
      {
        name: 'crm_link_attachment_to_draft',
        description: 'Attach one or more registered attachments to an email draft so they are SENT with it. attachment_ids are real sent attachments, distinct from reference-only linked documents.',
        inputSchema: {
          type: 'object',
          required: ['draft_id', 'attachment_ids'],
          properties: {
            draft_id:        { type: 'number', description: 'Email draft ID' },
            attachment_ids:  { type: 'array', items: { type: 'number' }, description: '1-10 attachment IDs' },
            idempotency_key: { type: 'string' },
          },
        },
      },
      {
        name: 'crm_register_proposal',
        description: 'Register a generated proposal (title, optional opportunity/contact link, public URL, amount). Returns the created proposal.',
        inputSchema: {
          type: 'object',
          required: ['title'],
          properties: {
            title:           { type: 'string', description: 'Proposal title (max 500)' },
            opportunity_id:  { type: 'number' },
            contact_id:      { type: 'number' },
            url:             { type: 'string', description: 'Public URL of the proposal (max 2000)' },
            amount:          { type: 'number', description: 'Quoted amount (>= 0)' },
            currency:        { type: 'string', description: '3-letter ISO code, e.g. USD' },
            body:            { type: 'string' },
            status:          { type: 'string', enum: ['draft', 'sent', 'accepted', 'rejected', 'expired'] },
            valid_until:     { type: 'string', description: 'YYYY-MM-DD' },
            idempotency_key: { type: 'string' },
          },
        },
      },
    ],
  }));

  server.setRequestHandler(CallToolRequestSchema, async (request) => {
    const { name, arguments: args } = request.params;
    const a = (args ?? {}) as Record<string, unknown>;

    // Pull an optional idempotency key off any write call. `payload` is the
    // body with the key (and never the path-param id for updates) stripped.
    const { idempotency_key, ...rest } = a;
    const payload = rest;
    const idem = typeof idempotency_key === 'string' && idempotency_key.length > 0
      ? { idempotencyKey: idempotency_key }
      : {};

    // Build a query string from a flat arg object, dropping empty values.
    const qs = (obj: Record<string, unknown>) =>
      new URLSearchParams(
        Object.entries(obj)
          .filter(([, v]) => v !== undefined && v !== null && v !== '')
          .map(([k, v]) => [k, String(v)]),
      ).toString();

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
          result = await crm.post('/opportunities', payload, idem);
          break;

        case 'crm_add_note': {
          const entityType = a.entity_type === 'contact' ? 'contacts' : 'opportunities';
          result = await crm.post(`/${entityType}/${a.entity_id}/notes`, { note: a.note }, idem);
          break;
        }

        case 'crm_create_email_draft':
          result = await crm.post('/email-drafts', payload, idem);
          break;

        case 'crm_create_followup':
          result = await crm.post('/follow-ups', payload, idem);
          break;

        case 'crm_recent_replies':
          result = await crm.get('/replies/recent?' + qs(a));
          break;

        case 'crm_ingest_opportunities':
          result = await crm.post('/ingestion/opportunities', payload, idem);
          break;

        case 'crm_create_contact':
          result = await crm.post('/contacts', payload, idem);
          break;

        case 'crm_link_contact_to_opportunity':
          result = await crm.post(`/opportunities/${a.opportunity_id}/contacts`, { contact_id: a.contact_id, role: a.role }, idem);
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

        case 'crm_pipeline_execute':
          result = await crm.post('/pipeline/execute', a);
          break;

        case 'crm_bulk_create_opportunities':
          result = await crm.post('/bulk/opportunities', payload, idem);
          break;

        case 'crm_bulk_create_contacts':
          result = await crm.post('/bulk/contacts', payload, idem);
          break;

        case 'crm_bulk_create_drafts':
          result = await crm.post('/bulk/drafts', payload, idem);
          break;

        case 'crm_send_test_email':
          result = await crm.post(`/email-drafts/${a.id}/send-test`, { test_email: a.test_email }, idem);
          break;

        case 'crm_send_draft':
          result = await crm.post(`/email-drafts/${a.id}/send`, {}, idem);
          break;

        case 'crm_publish_linkedin_post': {
          const body: Record<string, unknown> = { action: a.action };
          if (a.scheduled_at) body.scheduled_at = a.scheduled_at;
          if (a.timezone) body.timezone = a.timezone;
          result = await crm.postSocial(`/linkedin/posts/${a.id}/publish`, body, idem);
          break;
        }

        // -------------------------------------------------------------------
        // Reads (new)
        // -------------------------------------------------------------------
        case 'crm_check_duplicate':
          result = await crm.get('/opportunities/check-duplicate?' + qs({ company: a.company, role: a.role }));
          break;

        case 'crm_list_drafts':
          result = await crm.get('/email-drafts?' + qs(a));
          break;

        case 'crm_list_followups_due':
          result = await crm.get('/follow-ups/due');
          break;

        case 'crm_list_signatures':
          result = await crm.get('/signatures');
          break;

        // -------------------------------------------------------------------
        // Writes — updates & deletes (new)
        // -------------------------------------------------------------------
        case 'crm_update_opportunity': {
          const { id, ...fields } = payload;
          result = await crm.patch(`/opportunities/${a.id}`, fields, idem);
          break;
        }

        case 'crm_delete_opportunity':
          result = await crm.delete(`/opportunities/${a.id}`, idem);
          break;

        case 'crm_update_contact': {
          const { id, ...fields } = payload;
          result = await crm.patch(`/contacts/${a.id}`, fields, idem);
          break;
        }

        case 'crm_update_draft': {
          const { id, ...fields } = payload;
          result = await crm.patch(`/email-drafts/${a.id}`, fields, idem);
          break;
        }

        case 'crm_schedule_draft':
          result = await crm.post(`/email-drafts/${a.id}/schedule`, { send_at: a.scheduled_at }, idem);
          break;

        case 'crm_unschedule_draft':
          result = await crm.post(`/email-drafts/${a.id}/unschedule`, {}, idem);
          break;

        case 'crm_update_followup': {
          const { id, ...fields } = payload;
          result = await crm.patch(`/follow-ups/${a.id}`, fields, idem);
          break;
        }

        // -------------------------------------------------------------------
        // Writes — documents, attachments, proposals (new)
        // -------------------------------------------------------------------
        case 'crm_register_document':
          result = await crm.post('/documents', payload, idem);
          break;

        case 'crm_upload_document':
          result = await crm.post('/documents', payload, idem);
          break;

        case 'crm_register_attachment':
          result = await crm.post('/attachments', payload, idem);
          break;

        case 'crm_link_attachment_to_draft':
          result = await crm.post(`/email-drafts/${a.draft_id}/attachments`, { attachment_ids: a.attachment_ids }, idem);
          break;

        case 'crm_register_proposal':
          result = await crm.postProposals('/proposals', payload, idem);
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
