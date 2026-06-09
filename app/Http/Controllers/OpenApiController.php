<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class OpenApiController extends Controller
{
    public function gptActions(): JsonResponse
    {
        $base = rtrim(config('app.url'), '/');

        $schema = [
            'openapi' => '3.1.0',
            'info' => [
                'title'       => 'Personal Outreach CRM – GPT Actions API',
                'version'     => '1.3.0',
                'description' => 'Manage CRM data on behalf of the authenticated user. All actions require an X-Api-Key header. Email drafts are NEVER sent automatically — the user must review and send from the CRM UI.',
            ],
            'servers' => [
                ['url' => $base . '/api/gpt/v1', 'description' => 'Production CRM API'],
            ],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type'        => 'apiKey',
                        'in'          => 'header',
                        'name'        => 'X-Api-Key',
                        'description' => 'API key generated in CRM → Settings → Integrations. Format: pocrm_live_<token>',
                    ],
                ],
                'schemas' => [
                    'Error' => [
                        'type' => 'object',
                        'properties' => [
                            'error'   => ['type' => 'string'],
                            'message' => ['type' => 'string'],
                        ],
                    ],
                    'Opportunity' => [
                        'type' => 'object',
                        'properties' => [
                            'id'           => ['type' => 'integer'],
                            'title'        => ['type' => 'string'],
                            'type'         => ['type' => 'string', 'enum' => ['job', 'scholarship', 'research', 'grant', 'networking']],
                            'organization' => ['type' => 'string'],
                            'status'       => ['type' => 'string'],
                            'priority'     => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                            'deadline'     => ['type' => 'string', 'format' => 'date'],
                            'url'          => ['type' => 'string', 'format' => 'uri'],
                            'description'  => ['type' => 'string'],
                            'notes'        => ['type' => 'string'],
                            'created_at'   => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'Contact' => [
                        'type' => 'object',
                        'properties' => [
                            'id'          => ['type' => 'integer'],
                            'full_name'   => ['type' => 'string'],
                            'first_name'  => ['type' => 'string'],
                            'last_name'   => ['type' => 'string'],
                            'email'       => ['type' => 'string', 'format' => 'email'],
                            'company'     => ['type' => 'string'],
                            'job_title'   => ['type' => 'string'],
                            'status'      => ['type' => 'string'],
                            'created_at'  => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'Signature' => [
                        'type' => 'object',
                        'properties' => [
                            'id'         => ['type' => 'integer'],
                            'name'       => ['type' => 'string'],
                            'body'       => ['type' => 'string', 'description' => 'HTML or plain-text signature body'],
                            'is_default' => ['type' => 'boolean'],
                            'rendered'   => ['type' => 'string', 'description' => 'Full rendered HTML including wrapper div'],
                            'created_at' => ['type' => 'string', 'format' => 'date-time'],
                            'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'Document' => [
                        'type' => 'object',
                        'properties' => [
                            'id'             => ['type' => 'integer'],
                            'name'           => ['type' => 'string', 'description' => 'Human-readable document name'],
                            'description'    => ['type' => 'string', 'nullable' => true],
                            'document_type'  => ['type' => 'string', 'enum' => ['resume', 'cover_letter', 'proposal', 'portfolio', 'reference', 'other']],
                            'public_url'     => ['type' => 'string', 'format' => 'uri', 'nullable' => true, 'description' => 'Publicly accessible URL (API-created docs)'],
                            'file_name'      => ['type' => 'string', 'nullable' => true, 'description' => 'Original filename (UI-uploaded docs)'],
                            'file_size'      => ['type' => 'integer', 'nullable' => true, 'description' => 'File size in bytes'],
                            'mime_type'      => ['type' => 'string'],
                            'opportunity_id' => ['type' => 'integer', 'nullable' => true],
                            'contact_id'     => ['type' => 'integer', 'nullable' => true],
                            'created_at'     => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'Attachment' => [
                        'type' => 'object',
                        'properties' => [
                            'id'                   => ['type' => 'integer'],
                            'filename'             => ['type' => 'string'],
                            'public_url'           => ['type' => 'string', 'format' => 'uri'],
                            'mime_type'            => ['type' => 'string'],
                            'size_bytes'           => ['type' => 'integer'],
                            'category'             => ['type' => 'string', 'enum' => ['cv_resume','cover_letter','portfolio','transcript','certificate','id_document','reference','sample_work','proposal','other']],
                            'notes'                => ['type' => 'string', 'nullable' => true],
                            'validation_status'    => ['type' => 'string', 'enum' => ['valid','warning','rejected']],
                            'validation_warnings'  => ['type' => 'array', 'items' => ['type' => 'string']],
                            'created_at'           => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'EmailDraft' => [
                        'type' => 'object',
                        'properties' => [
                            'id'                           => ['type' => 'integer'],
                            'subject'                      => ['type' => 'string'],
                            'to_email'                     => ['type' => 'string', 'format' => 'email'],
                            'to_name'                      => ['type' => 'string'],
                            'status'                       => ['type' => 'string'],
                            'send_status'                  => ['type' => 'string'],
                            'contact_id'                   => ['type' => 'integer'],
                            'opportunity_id'               => ['type' => 'integer', 'nullable' => true],
                            'signature_id'                 => ['type' => 'integer', 'nullable' => true],
                            'signature_name'               => ['type' => 'string', 'nullable' => true],
                            'rendered_signature'           => ['type' => 'string', 'nullable' => true],
                            'attachment_ids'               => ['type' => 'array', 'items' => ['type' => 'integer']],
                            'attachment_count'             => ['type' => 'integer'],
                            'attachment_validation_status' => ['type' => 'string', 'enum' => ['valid','warning']],
                            'confirmation_required'        => ['type' => 'boolean'],
                            'audit_log_reference'          => ['type' => 'integer', 'nullable' => true],
                            'preview'                      => ['type' => 'string'],
                            'created_at'                   => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'FollowUp' => [
                        'type' => 'object',
                        'properties' => [
                            'id'                           => ['type' => 'integer'],
                            'contact_id'                   => ['type' => 'integer'],
                            'opportunity_id'               => ['type' => 'integer', 'nullable' => true],
                            'due_at'                       => ['type' => 'string', 'format' => 'date-time'],
                            'status'                       => ['type' => 'string'],
                            'send_status'                  => ['type' => 'string'],
                            'subject'                      => ['type' => 'string', 'nullable' => true],
                            'signature_id'                 => ['type' => 'integer', 'nullable' => true],
                            'signature_name'               => ['type' => 'string', 'nullable' => true],
                            'rendered_signature'           => ['type' => 'string', 'nullable' => true],
                            'suggested_attachment_ids'     => ['type' => 'array', 'items' => ['type' => 'integer']],
                            'attachment_count'             => ['type' => 'integer'],
                            'attachment_validation_status' => ['type' => 'string', 'enum' => ['valid','warning']],
                            'confirmation_required'        => ['type' => 'boolean'],
                        ],
                    ],
                    'DashboardSummary' => [
                        'type' => 'object',
                        'properties' => [
                            'summary' => [
                                'type' => 'object',
                                'properties' => [
                                    'total_opportunities'    => ['type' => 'integer'],
                                    'active_opportunities'   => ['type' => 'integer'],
                                    'follow_ups_due_today'   => ['type' => 'integer'],
                                    'replies_needing_review' => ['type' => 'integer'],
                                    'deadline_soon'          => ['type' => 'integer'],
                                ],
                            ],
                            'next_actions' => [
                                'type'  => 'array',
                                'items' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
            'security' => [['ApiKeyAuth' => []]],
            'paths' => [

                // ---------------------------------------------------------------
                // Health
                // ---------------------------------------------------------------
                '/health' => [
                    'get' => [
                        'operationId' => 'getHealth',
                        'summary'     => 'API health check',
                        'description' => 'Returns 200 if the API is reachable. No scope or API key required.',
                        'security'    => [],
                        'responses'   => ['200' => ['description' => 'Healthy']],
                    ],
                ],

                // ---------------------------------------------------------------
                // Identity / Dashboard
                // ---------------------------------------------------------------
                '/me' => [
                    'get' => [
                        'operationId' => 'getMe',
                        'summary'     => 'Get authenticated client identity',
                        'description' => 'Returns the user and API client details for the provided key.',
                        'responses'   => [
                            '200' => ['description' => 'Identity info'],
                            '401' => ['description' => 'Unauthenticated'],
                        ],
                    ],
                ],
                '/dashboard-summary' => [
                    'get' => [
                        'operationId' => 'getDashboardSummary',
                        'summary'     => 'CRM dashboard summary',
                        'description' => 'Returns open opportunity counts, follow-ups due today, pending replies, and suggested next actions. Scope: dashboard:read.',
                        'responses' => [
                            '200' => [
                                'description' => 'Dashboard summary',
                                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/DashboardSummary']]],
                            ],
                        ],
                    ],
                ],

                // ---------------------------------------------------------------
                // Opportunities
                // ---------------------------------------------------------------
                '/opportunities' => [
                    'get' => [
                        'operationId' => 'searchOpportunities',
                        'summary'     => 'Search CRM opportunities',
                        'description' => 'Search and filter opportunities. Scope: opportunities:read.',
                        'parameters'  => [
                            ['name' => 'q',               'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'type',            'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'status',          'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'priority',        'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'deadline_before', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
                            ['name' => 'deadline_after',  'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
                            ['name' => 'limit',           'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20, 'maximum' => 100]],
                        ],
                        'responses' => ['200' => ['description' => 'List of matching opportunities']],
                    ],
                    'post' => [
                        'operationId' => 'createOpportunity',
                        'summary'     => 'Create a new opportunity',
                        'description' => 'Creates an opportunity. Deduplicates by title+org+URL. Scope: opportunities:write.',
                        'requestBody' => [
                            'required' => true,
                            'content' => ['application/json' => ['schema' => [
                                'type' => 'object', 'required' => ['title', 'type', 'organization'],
                                'properties' => [
                                    'title'        => ['type' => 'string'],
                                    'type'         => ['type' => 'string', 'enum' => ['job', 'scholarship', 'research', 'grant', 'networking']],
                                    'organization' => ['type' => 'string'],
                                    'description'  => ['type' => 'string'],
                                    'url'          => ['type' => 'string', 'format' => 'uri'],
                                    'status'       => ['type' => 'string'],
                                    'priority'     => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                                    'deadline'     => ['type' => 'string', 'format' => 'date'],
                                    'notes'        => ['type' => 'string'],
                                ],
                            ]]],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Opportunity created'],
                            '200' => ['description' => 'Duplicate – existing opportunity returned'],
                        ],
                    ],
                ],
                '/opportunities/{id}' => [
                    'get' => [
                        'operationId' => 'getOpportunity',
                        'summary'     => 'Get opportunity by ID',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses'   => ['200' => ['description' => 'Opportunity detail']],
                    ],
                ],
                '/opportunities/{id}/contacts' => [
                    'post' => [
                        'operationId' => 'linkContactToOpportunity',
                        'summary'     => 'Link a contact to an opportunity',
                        'description' => 'Creates or updates the contact–opportunity relationship. Idempotent — safe to call multiple times. Scope: opportunities:write.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                            'type'       => 'object',
                            'required'   => ['contact_id'],
                            'properties' => [
                                'contact_id' => ['type' => 'integer', 'description' => 'ID of the contact to link'],
                                'role'       => ['type' => 'string', 'maxLength' => 100, 'description' => 'Optional role label, e.g. "recruiter", "hiring manager"'],
                            ],
                        ]]]],
                        'responses' => ['200' => ['description' => 'Contact linked; returns updated contacts list']],
                    ],
                ],
                '/opportunities/{id}/notes' => [
                    'post' => [
                        'operationId' => 'addOpportunityNote',
                        'summary'     => 'Append a note to an opportunity',
                        'description' => 'Scope: notes:write.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['note'], 'properties' => ['note' => ['type' => 'string']]]]]],
                        'responses'   => ['200' => ['description' => 'Note added']],
                    ],
                ],

                // ---------------------------------------------------------------
                // Contacts
                // ---------------------------------------------------------------
                '/contacts' => [
                    'get' => [
                        'operationId' => 'searchContacts',
                        'summary'     => 'Search CRM contacts',
                        'description' => 'Search by name, email, or company. Scope: contacts:read.',
                        'parameters'  => [
                            ['name' => 'q',            'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'email',        'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'email']],
                            ['name' => 'organization', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'status',       'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'limit',        'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20, 'maximum' => 100]],
                        ],
                        'responses' => ['200' => ['description' => 'List of contacts']],
                    ],
                    'post' => [
                        'operationId' => 'createContact',
                        'summary'     => 'Create a new contact',
                        'description' => 'Deduplicates by email. Scope: contacts:write.',
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => [
                                'first_name'   => ['type' => 'string'],
                                'full_name'    => ['type' => 'string'],
                                'last_name'    => ['type' => 'string'],
                                'email'        => ['type' => 'string', 'format' => 'email'],
                                'phone'        => ['type' => 'string'],
                                'company'      => ['type' => 'string'],
                                'job_title'    => ['type' => 'string'],
                                'linkedin_url' => ['type' => 'string', 'format' => 'uri'],
                                'notes'        => ['type' => 'string'],
                            ],
                        ]]]],
                        'responses' => [
                            '201' => ['description' => 'Contact created'],
                            '200' => ['description' => 'Duplicate – existing contact returned'],
                        ],
                    ],
                ],
                '/contacts/{id}' => [
                    'get' => [
                        'operationId' => 'getContact',
                        'summary'     => 'Get contact by ID',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses'   => ['200' => ['description' => 'Contact detail']],
                    ],
                ],
                '/contacts/{id}/notes' => [
                    'post' => [
                        'operationId' => 'addContactNote',
                        'summary'     => 'Append a note to a contact',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['note'], 'properties' => ['note' => ['type' => 'string']]]]]],
                        'responses'   => ['200' => ['description' => 'Note added']],
                    ],
                ],

                // ---------------------------------------------------------------
                // Signatures
                // ---------------------------------------------------------------
                '/signatures' => [
                    'get' => [
                        'operationId' => 'listSignatures',
                        'summary'     => 'List email signatures',
                        'description' => 'Returns all signatures for the authenticated user, default first. Scope: signatures:read.',
                        'responses'   => [
                            '200' => [
                                'description' => 'List of signatures',
                                'content' => ['application/json' => ['schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'data'  => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Signature']],
                                        'count' => ['type' => 'integer'],
                                    ],
                                ]]],
                            ],
                        ],
                    ],
                    'post' => [
                        'operationId' => 'createSignature',
                        'summary'     => 'Create an email signature',
                        'description' => 'Creates a reusable signature. If is_default is true, any existing default is cleared. Scope: signatures:write.',
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                            'type'     => 'object',
                            'required' => ['name', 'body'],
                            'properties' => [
                                'name'       => ['type' => 'string', 'maxLength' => 255],
                                'body'       => ['type' => 'string', 'maxLength' => 50000, 'description' => 'HTML or plain-text signature content'],
                                'is_default' => ['type' => 'boolean', 'default' => false],
                            ],
                            'example' => ['name' => 'Professional', 'body' => '<p>Best regards,<br><strong>Rana Faraz</strong></p>', 'is_default' => true],
                        ]]]],
                        'responses' => [
                            '201' => ['description' => 'Signature created', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Signature']]]],
                        ],
                    ],
                ],
                '/signatures/{id}' => [
                    'get' => [
                        'operationId' => 'getSignature',
                        'summary'     => 'Get signature by ID',
                        'description' => 'Scope: signatures:read.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses'   => ['200' => ['description' => 'Signature detail', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Signature']]]]],
                    ],
                    'patch' => [
                        'operationId' => 'updateSignature',
                        'summary'     => 'Update a signature',
                        'description' => 'Partial update. Note: editing a signature does NOT alter already-created drafts — those carry a rendered_signature snapshot. Scope: signatures:write.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => [
                                'name'       => ['type' => 'string'],
                                'body'       => ['type' => 'string'],
                                'is_default' => ['type' => 'boolean'],
                            ],
                        ]]]],
                        'responses' => ['200' => ['description' => 'Signature updated']],
                    ],
                    'delete' => [
                        'operationId' => 'deleteSignature',
                        'summary'     => 'Delete a signature',
                        'description' => 'Soft-deletes the signature. Existing draft snapshots are unaffected. Scope: signatures:write.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses'   => ['200' => ['description' => 'Signature deleted']],
                    ],
                ],

                // ---------------------------------------------------------------
                // Attachments
                // ---------------------------------------------------------------
                '/attachments' => [
                    'post' => [
                        'operationId' => 'createAttachment',
                        'summary'     => 'Register a public-URL attachment',
                        'description' => 'Registers a file by its public URL. Only http/https URLs accepted; private IPs and localhost are rejected. Max 20 MB. Identity/credential files trigger validation warnings. Scope: attachments:write.',
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                            'type'     => 'object',
                            'required' => ['filename', 'public_url', 'mime_type', 'size_bytes'],
                            'properties' => [
                                'filename'   => ['type' => 'string', 'maxLength' => 500, 'description' => 'Original filename including extension'],
                                'public_url' => ['type' => 'string', 'format' => 'uri', 'maxLength' => 2048, 'description' => 'Publicly accessible https:// URL'],
                                'mime_type'  => ['type' => 'string', 'enum' => [
                                    'application/pdf',
                                    'application/msword',
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    'application/vnd.ms-excel',
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                    'text/plain',
                                    'text/csv',
                                    'image/jpeg',
                                    'image/png',
                                    'image/gif',
                                    'image/webp',
                                ]],
                                'size_bytes' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20971520, 'description' => 'File size in bytes (max 20 MB)'],
                                'category'   => ['type' => 'string', 'enum' => ['cv_resume','cover_letter','portfolio','transcript','certificate','id_document','reference','sample_work','proposal','other']],
                                'notes'      => ['type' => 'string', 'maxLength' => 2000],
                            ],
                            'example' => [
                                'filename'   => 'Rana_CV_2026.pdf',
                                'public_url' => 'https://drive.google.com/uc?id=abc123&export=download',
                                'mime_type'  => 'application/pdf',
                                'size_bytes' => 524288,
                                'category'   => 'cv_resume',
                            ],
                        ]]]],
                        'responses' => [
                            '201' => ['description' => 'Attachment registered', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Attachment']]]],
                            '422' => ['description' => 'Validation failed (bad URL, unsupported MIME type, or size exceeded)'],
                        ],
                    ],
                ],
                '/attachments/{id}' => [
                    'get' => [
                        'operationId' => 'getAttachment',
                        'summary'     => 'Get attachment by ID',
                        'description' => 'Scope: attachments:read.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses'   => ['200' => ['description' => 'Attachment detail', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Attachment']]]]],
                    ],
                ],

                // ---------------------------------------------------------------
                // Documents
                // ---------------------------------------------------------------
                '/documents' => [
                    'get' => [
                        'operationId' => 'listDocuments',
                        'summary'     => 'List documents',
                        'description' => 'List all documents for the authenticated user. Filter by opportunity_id, contact_id, or document_type. Scope: documents:read.',
                        'parameters'  => [
                            ['name' => 'opportunity_id', 'in' => 'query', 'schema' => ['type' => 'integer'], 'description' => 'Filter by opportunity'],
                            ['name' => 'contact_id',     'in' => 'query', 'schema' => ['type' => 'integer'], 'description' => 'Filter by contact'],
                            ['name' => 'document_type',  'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['resume', 'cover_letter', 'proposal', 'portfolio', 'reference', 'other']]],
                        ],
                        'responses' => [
                            '200' => ['description' => 'List of documents', 'content' => ['application/json' => ['schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'data'  => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Document']],
                                    'count' => ['type' => 'integer'],
                                ],
                            ]]]],
                        ],
                    ],
                    'post' => [
                        'operationId' => 'createDocument',
                        'summary'     => 'Register a document by URL',
                        'description' => 'Creates a document record linked to an optional opportunity or contact. Only http/https URLs are accepted. Scope: documents:write.',
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                            'type'     => 'object',
                            'required' => ['name', 'public_url', 'mime_type'],
                            'properties' => [
                                'name'           => ['type' => 'string', 'maxLength' => 500],
                                'public_url'     => ['type' => 'string', 'format' => 'uri', 'maxLength' => 2048, 'description' => 'Publicly accessible https:// URL to the document'],
                                'mime_type'      => ['type' => 'string', 'enum' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain', 'text/csv', 'image/jpeg', 'image/png', 'image/gif', 'image/webp']],
                                'document_type'  => ['type' => 'string', 'enum' => ['resume', 'cover_letter', 'proposal', 'portfolio', 'reference', 'other']],
                                'description'    => ['type' => 'string', 'maxLength' => 2000],
                                'opportunity_id' => ['type' => 'integer', 'nullable' => true, 'description' => 'Associate with an opportunity'],
                                'contact_id'     => ['type' => 'integer', 'nullable' => true, 'description' => 'Associate with a contact'],
                            ],
                            'example' => [
                                'name'           => 'Rana CV 2026',
                                'public_url'     => 'https://drive.google.com/uc?id=abc123&export=download',
                                'mime_type'      => 'application/pdf',
                                'document_type'  => 'resume',
                                'opportunity_id' => 7,
                            ],
                        ]]]],
                        'responses' => [
                            '201' => ['description' => 'Document created', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Document']]]],
                            '422' => ['description' => 'Validation error'],
                        ],
                    ],
                ],
                '/documents/{id}' => [
                    'get' => [
                        'operationId' => 'getDocument',
                        'summary'     => 'Get document by ID',
                        'description' => 'Scope: documents:read.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses'   => ['200' => ['description' => 'Document detail', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Document']]]]],
                    ],
                    'delete' => [
                        'operationId' => 'deleteDocument',
                        'summary'     => 'Delete a document',
                        'description' => 'Soft-deletes the document record. Does not remove the original file from its host. Scope: documents:write.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses'   => ['200' => ['description' => 'Document deleted']],
                    ],
                ],
                '/opportunities/{id}/documents' => [
                    'get' => [
                        'operationId' => 'listOpportunityDocuments',
                        'summary'     => 'List documents for an opportunity',
                        'description' => 'Returns all documents attached to the given opportunity. Scope: documents:read.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses'   => ['200' => ['description' => 'Documents for the opportunity']],
                    ],
                    'post' => [
                        'operationId' => 'addDocumentToOpportunity',
                        'summary'     => 'Add a document to an opportunity',
                        'description' => 'Creates a new document record and links it to the given opportunity. Scope: documents:write.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                            'type'     => 'object',
                            'required' => ['name', 'public_url', 'mime_type'],
                            'properties' => [
                                'name'          => ['type' => 'string', 'maxLength' => 500],
                                'public_url'    => ['type' => 'string', 'format' => 'uri', 'maxLength' => 2048],
                                'mime_type'     => ['type' => 'string', 'enum' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain', 'text/csv', 'image/jpeg', 'image/png', 'image/gif', 'image/webp']],
                                'document_type' => ['type' => 'string', 'enum' => ['resume', 'cover_letter', 'proposal', 'portfolio', 'reference', 'other']],
                                'description'   => ['type' => 'string', 'maxLength' => 2000],
                            ],
                        ]]]],
                        'responses' => ['201' => ['description' => 'Document added to opportunity']],
                    ],
                ],
                '/contacts/{id}/documents' => [
                    'get' => [
                        'operationId' => 'listContactDocuments',
                        'summary'     => 'List documents for a contact',
                        'description' => 'Returns all documents attached to the given contact. Scope: documents:read.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses'   => ['200' => ['description' => 'Documents for the contact']],
                    ],
                    'post' => [
                        'operationId' => 'addDocumentToContact',
                        'summary'     => 'Add a document to a contact',
                        'description' => 'Creates a new document record and links it to the given contact. Scope: documents:write.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                            'type'     => 'object',
                            'required' => ['name', 'public_url', 'mime_type'],
                            'properties' => [
                                'name'          => ['type' => 'string', 'maxLength' => 500],
                                'public_url'    => ['type' => 'string', 'format' => 'uri', 'maxLength' => 2048],
                                'mime_type'     => ['type' => 'string', 'enum' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain', 'text/csv', 'image/jpeg', 'image/png', 'image/gif', 'image/webp']],
                                'document_type' => ['type' => 'string', 'enum' => ['resume', 'cover_letter', 'proposal', 'portfolio', 'reference', 'other']],
                                'description'   => ['type' => 'string', 'maxLength' => 2000],
                            ],
                        ]]]],
                        'responses' => ['201' => ['description' => 'Document added to contact']],
                    ],
                ],

                // ---------------------------------------------------------------
                // Email Drafts
                // ---------------------------------------------------------------
                '/email-drafts' => [
                    'get' => [
                        'operationId' => 'listEmailDrafts',
                        'summary'     => 'List pending email drafts',
                        'description' => 'Returns drafts awaiting user review, newest first. Includes signature and attachment metadata. Scope: drafts:read.',
                        'responses'   => [
                            '200' => ['description' => 'List of drafts', 'content' => ['application/json' => ['schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'data'  => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/EmailDraft']],
                                    'count' => ['type' => 'integer'],
                                ],
                            ]]]],
                        ],
                    ],
                    'post' => [
                        'operationId' => 'createEmailDraft',
                        'summary'     => 'Create an email draft for review',
                        'description' => 'Saves a draft linked to a contact. The draft is NEVER sent automatically. If signature_id is provided, the rendered HTML is snapshotted so later signature edits do not alter this draft. Scope: drafts:create.',
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                            'type'     => 'object',
                            'required' => ['contact_id', 'subject', 'body'],
                            'properties' => [
                                'contact_id'      => ['type' => 'integer'],
                                'opportunity_id'  => ['type' => 'integer', 'nullable' => true],
                                'subject'         => ['type' => 'string', 'maxLength' => 500],
                                'body'            => ['type' => 'string', 'maxLength' => 50000],
                                'draft_type'      => ['type' => 'string', 'enum' => ['initial_outreach', 'follow_up', 'thank_you', 'general']],
                                'tone'            => ['type' => 'string', 'enum' => ['professional', 'casual', 'formal']],
                                'signature_id'    => ['type' => 'integer', 'nullable' => true, 'description' => 'ID of a saved signature to embed. Snapshot is stored immediately.'],
                                'attachment_ids'  => ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 10, 'description' => 'IDs of pre-registered attachments to link to this draft'],
                            ],
                            'example' => [
                                'contact_id'     => 42,
                                'opportunity_id' => 7,
                                'subject'        => 'Research collaboration inquiry',
                                'body'           => "Dear Prof. Smith,\n\nI am writing to explore...",
                                'draft_type'     => 'initial_outreach',
                                'signature_id'   => 3,
                                'attachment_ids' => [12, 15],
                            ],
                        ]]]],
                        'responses' => [
                            '201' => ['description' => 'Draft saved – awaiting user review', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/EmailDraft']]]],
                            '422' => ['description' => 'Suppressed contact or validation error'],
                        ],
                    ],
                ],
                '/email-drafts/{id}/rendered-preview' => [
                    'get' => [
                        'operationId' => 'getDraftRenderedPreview',
                        'summary'     => 'Get full rendered preview of a draft',
                        'description' => 'Returns recipient, subject, rendered body (with signature), attachment list with validation status, and confirmation metadata. Use this to present the user with the final email before they approve sending. Scope: drafts:read.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses'   => [
                            '200' => ['description' => 'Rendered draft preview', 'content' => ['application/json' => ['schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'draft_id'                       => ['type' => 'integer'],
                                    'send_status'                    => ['type' => 'string'],
                                    'confirmation_required'          => ['type' => 'boolean'],
                                    'to_email'                       => ['type' => 'string', 'format' => 'email'],
                                    'to_name'                        => ['type' => 'string'],
                                    'subject'                        => ['type' => 'string'],
                                    'body_preview'                   => ['type' => 'string'],
                                    'rendered_signature'             => ['type' => 'string', 'nullable' => true],
                                    'rendered_body'                  => ['type' => 'string', 'description' => 'body + signature combined'],
                                    'signature_id'                   => ['type' => 'integer', 'nullable' => true],
                                    'signature_name'                 => ['type' => 'string', 'nullable' => true],
                                    'attachment_ids'                 => ['type' => 'array', 'items' => ['type' => 'integer']],
                                    'attachments'                    => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Attachment']],
                                    'attachment_count'               => ['type' => 'integer'],
                                    'attachment_validation_status'   => ['type' => 'string', 'enum' => ['valid','warning']],
                                    'attachment_validation_warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'audit_log_reference'            => ['type' => 'integer', 'nullable' => true],
                                    'notice'                         => ['type' => 'string'],
                                ],
                            ]]]],
                        ],
                    ],
                ],
                '/email-drafts/{draft_id}/attachments' => [
                    'post' => [
                        'operationId' => 'addAttachmentsToDraft',
                        'summary'     => 'Link attachments to an existing draft',
                        'description' => 'Associates pre-registered attachments to a draft. Returns validation warnings for sensitive documents. Scope: drafts:create + attachments:write.',
                        'parameters'  => [['name' => 'draft_id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                            'type'     => 'object',
                            'required' => ['attachment_ids'],
                            'properties' => [
                                'attachment_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'minItems' => 1, 'maxItems' => 10],
                            ],
                        ]]]],
                        'responses' => [
                            '201' => ['description' => 'Attachments linked'],
                            '422' => ['description' => 'One or more attachment IDs not found'],
                        ],
                    ],
                ],
                // ---------------------------------------------------------------
                // Follow-ups
                // ---------------------------------------------------------------
                '/follow-ups' => [
                    'post' => [
                        'operationId' => 'createFollowUp',
                        'summary'     => 'Schedule a follow-up reminder',
                        'description' => 'Creates a reminder-only follow-up. Auto-sending is always disabled. Signature snapshot and suggested attachments are preserved for user review. Scope: followups:create.',
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                            'type'     => 'object',
                            'required' => ['contact_id', 'due_at'],
                            'properties' => [
                                'contact_id'               => ['type' => 'integer'],
                                'opportunity_id'           => ['type' => 'integer', 'nullable' => true],
                                'due_at'                   => ['type' => 'string', 'format' => 'date-time'],
                                'notes'                    => ['type' => 'string'],
                                'suggested_subject'        => ['type' => 'string', 'maxLength' => 500],
                                'suggested_body'           => ['type' => 'string', 'maxLength' => 20000],
                                'signature_id'             => ['type' => 'integer', 'nullable' => true, 'description' => 'Signature to embed in the suggested follow-up email'],
                                'suggested_attachment_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 10],
                            ],
                        ]]]],
                        'responses' => [
                            '201' => ['description' => 'Follow-up scheduled', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/FollowUp']]]],
                            '422' => ['description' => 'Blocked – suppressed contact'],
                        ],
                    ],
                ],
                '/follow-ups/due' => [
                    'get' => [
                        'operationId' => 'getDueFollowUps',
                        'summary'     => 'List follow-ups due today or overdue',
                        'description' => 'Returns pending follow-ups with full signature and attachment metadata. Scope: followups:read.',
                        'responses'   => ['200' => ['description' => 'Due follow-ups', 'content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => [
                                'data'  => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/FollowUp']],
                                'count' => ['type' => 'integer'],
                            ],
                        ]]]]],
                    ],
                ],

                // ---------------------------------------------------------------
                // Replies
                // ---------------------------------------------------------------
                '/replies/recent' => [
                    'get' => [
                        'operationId' => 'getRecentReplies',
                        'summary'     => 'Get recent inbound replies',
                        'description' => 'Returns matched inbound replies. Scope: replies:read.',
                        'parameters'  => [
                            ['name' => 'since',          'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date-time']],
                            ['name' => 'opportunity_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'contact_id',     'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'sentiment',      'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['positive', 'neutral', 'negative']]],
                            ['name' => 'limit',          'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20, 'maximum' => 50]],
                        ],
                        'responses' => ['200' => ['description' => 'Recent replies']],
                    ],
                ],

                // ---------------------------------------------------------------
                // Bulk Ingestion
                // ---------------------------------------------------------------
                '/ingestion/opportunities' => [
                    'post' => [
                        'operationId' => 'ingestOpportunities',
                        'summary'     => 'Bulk ingest opportunities',
                        'description' => 'Accepts up to 50 opportunities. Deduplicates automatically. Scope: opportunities:write.',
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                            'type' => 'object', 'required' => ['items'],
                            'properties' => ['items' => ['type' => 'array', 'maxItems' => 50, 'items' => [
                                'type' => 'object', 'required' => ['title', 'type', 'organization'],
                                'properties' => [
                                    'title'        => ['type' => 'string', 'maxLength' => 255],
                                    'type'         => ['type' => 'string', 'enum' => ['job', 'scholarship', 'research', 'grant', 'networking']],
                                    'organization' => ['type' => 'string', 'maxLength' => 255],
                                    'description'  => ['type' => 'string'],
                                    'url'          => ['type' => 'string', 'format' => 'uri'],
                                    'deadline'     => ['type' => 'string', 'format' => 'date'],
                                    'priority'     => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']],
                                    'notes'        => ['type' => 'string'],
                                ],
                            ]]],
                        ]]]],
                        'responses' => ['201' => ['description' => 'Ingestion result with created/duplicate counts']],
                    ],
                ],
                '/ingestion/contacts' => [
                    'post' => [
                        'operationId' => 'ingestContacts',
                        'summary'     => 'Bulk ingest contacts',
                        'description' => 'Accepts up to 50 contacts. Deduplicates by email. Scope: contacts:write.',
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                            'type' => 'object', 'required' => ['items'],
                            'properties' => ['items' => ['type' => 'array', 'maxItems' => 50, 'items' => [
                                'type' => 'object', 'required' => ['first_name'],
                                'properties' => [
                                    'first_name'   => ['type' => 'string', 'maxLength' => 100],
                                    'last_name'    => ['type' => 'string', 'maxLength' => 100],
                                    'email'        => ['type' => 'string', 'format' => 'email'],
                                    'company'      => ['type' => 'string', 'maxLength' => 255],
                                    'job_title'    => ['type' => 'string', 'maxLength' => 255],
                                    'phone'        => ['type' => 'string', 'maxLength' => 50],
                                    'linkedin_url' => ['type' => 'string', 'format' => 'uri'],
                                    'notes'        => ['type' => 'string'],
                                ],
                            ]]],
                        ]]]],
                        'responses' => ['201' => ['description' => 'Ingestion result with created/duplicate counts']],
                    ],
                ],

                // ---------------------------------------------------------------
                // Confirmations
                // ---------------------------------------------------------------
                '/confirmations' => [
                    'post' => [
                        'operationId' => 'createConfirmation',
                        'summary'     => 'Request user confirmation for a high-risk action',
                        'description' => 'Creates a pending confirmation the CRM user must approve or reject before proceeding.',
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                            'type' => 'object', 'required' => ['action', 'description'],
                            'properties' => [
                                'action'      => ['type' => 'string'],
                                'description' => ['type' => 'string'],
                                'payload'     => ['type' => 'object'],
                            ],
                        ]]]],
                        'responses' => ['201' => ['description' => 'Confirmation created']],
                    ],
                ],
                '/confirmations/{id}' => [
                    'get' => [
                        'operationId' => 'getConfirmation',
                        'summary'     => 'Poll confirmation status',
                        'description' => 'Returns the current status (pending, approved, rejected) of a confirmation request.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]],
                        'responses'   => [
                            '200' => ['description' => 'Confirmation status'],
                            '404' => ['description' => 'Not found or expired'],
                        ],
                    ],
                ],

            ],
        ];

        return response()->json($schema)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Content-Type', 'application/json; charset=utf-8');
    }

    // ── LinkedIn Social Studio GPT Actions ────────────────────────────────────

    public function socialActions(): JsonResponse
    {
        $base = rtrim(config('app.url'), '/');

        $schema = [
            'openapi'    => '3.1.0',
            'info'       => [
                'title'       => 'Personal CRM – LinkedIn Social Studio',
                'version'     => '1.0.0',
                'description' => 'Create and schedule LinkedIn posts via the CRM. Nothing is published automatically — the human must approve in the CRM first. Requires X-Api-Key. Scopes: social:read, social:write, social:publish, social:analytics.',
            ],
            'servers'    => [['url' => $base . '/api/social/v1', 'description' => 'CRM Social API']],
            'components' => $this->socialSchemaComponents(),
            'security'   => [['ApiKeyAuth' => []]],
            'paths'      => array_merge(
                $this->socialAccountAndPostPaths(),
                $this->socialPostItemPaths(),
                $this->socialConfirmationMediaAnalyticsPaths(),
            ),
        ];

        return response()->json($schema)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Content-Type', 'application/json; charset=utf-8');
    }

    private function socialSchemaComponents(): array
    {
        return [
            'securitySchemes' => [
                'ApiKeyAuth' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-Api-Key'],
            ],
            'schemas' => [
                'LinkedInPost' => [
                    'type' => 'object',
                    'properties' => [
                        'id'                => ['type' => 'integer'],
                        'title_internal'    => ['type' => 'string', 'description' => 'Internal label, not posted publicly'],
                        'post_body'         => ['type' => 'string'],
                        'post_type'         => ['type' => 'string', 'enum' => ['text', 'article_link', 'image']],
                        'status'            => ['type' => 'string', 'enum' => ['draft', 'ready_for_review', 'scheduled', 'publishing', 'published', 'failed']],
                        'approval_status'   => ['type' => 'string', 'enum' => ['pending', 'approved', 'rejected']],
                        'hashtags'          => ['type' => 'array', 'items' => ['type' => 'string']],
                        'article_url'       => ['type' => 'string', 'format' => 'uri', 'nullable' => true],
                        'scheduled_at'      => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                        'timezone_display'  => ['type' => 'string', 'nullable' => true],
                        'author_member_urn' => ['type' => 'string', 'nullable' => true],
                        'content_version'   => ['type' => 'integer'],
                        'linkedin_post_url' => ['type' => 'string', 'format' => 'uri', 'nullable' => true],
                        'created_at'        => ['type' => 'string', 'format' => 'date-time'],
                        'updated_at'        => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
                'Confirmation' => [
                    'type' => 'object',
                    'properties' => [
                        'confirmation_token' => ['type' => 'string', 'format' => 'uuid'],
                        'action'             => ['type' => 'string', 'enum' => ['publish_now', 'schedule']],
                        'status'             => ['type' => 'string', 'enum' => ['pending', 'approved', 'rejected', 'used', 'expired']],
                        'is_usable'          => ['type' => 'boolean'],
                        'expires_at'         => ['type' => 'string', 'format' => 'date-time'],
                        'scheduled_at'       => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    ],
                ],
                'LinkedInAccount' => [
                    'type' => 'object',
                    'properties' => [
                        'id'             => ['type' => 'integer'],
                        'display_name'   => ['type' => 'string'],
                        'status'         => ['type' => 'string'],
                        'is_default'     => ['type' => 'boolean'],
                        'capabilities'   => ['type' => 'object'],
                        'missing_scopes' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
            ],
        ];
    }

    private function socialAccountAndPostPaths(): array
    {
        return [
            '/linkedin/accounts' => [
                'get' => [
                    'operationId' => 'listLinkedInAccounts',
                    'summary'     => 'List connected LinkedIn accounts',
                    'description' => 'Returns all connected LinkedIn accounts. Use is_default to pick the default account. Scope: social:read.',
                    'responses'   => [
                        '200' => ['description' => 'Connected accounts', 'content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => [
                                'accounts' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/LinkedInAccount']],
                            ],
                        ]]]],
                    ],
                ],
            ],
            '/linkedin/posts' => [
                'get' => [
                    'operationId' => 'listLinkedInPosts',
                    'summary'     => 'List LinkedIn post drafts',
                    'description' => 'Lists posts. Filter by status (draft, scheduled, published, etc.). Scope: social:read.',
                    'parameters'  => [
                        ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['draft', 'ready_for_review', 'scheduled', 'published', 'failed']]],
                    ],
                    'responses' => ['200' => ['description' => 'Post list', 'content' => ['application/json' => ['schema' => [
                        'type' => 'object',
                        'properties' => ['posts' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/LinkedInPost']]],
                    ]]]]],
                ],
                'post' => [
                    'operationId' => 'createLinkedInPost',
                    'summary'     => 'Create a LinkedIn post draft',
                    'description' => 'Saves a draft to the CRM. NOT published automatically. To schedule: set scheduled_at. To publish: call requestPublishConfirmation. post_type: text, article_link (needs article_url), or image (attach media separately). Scope: social:write.',
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                        'type'     => 'object',
                        'required' => ['post_body', 'post_type'],
                        'properties' => [
                            'title_internal'     => ['type' => 'string', 'maxLength' => 255, 'description' => 'Internal label (not posted)'],
                            'post_body'          => ['type' => 'string', 'maxLength' => 3000, 'description' => 'The LinkedIn post text'],
                            'post_type'          => ['type' => 'string', 'enum' => ['text', 'article_link', 'image']],
                            'article_url'        => ['type' => 'string', 'format' => 'uri', 'description' => 'Required when post_type is article_link'],
                            'hashtags_json'      => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 30, 'description' => 'Hashtags without # prefix'],
                            'first_comment_body' => ['type' => 'string', 'maxLength' => 1250, 'description' => 'Optional first comment to post after publishing'],
                            'scheduled_at'       => ['type' => 'string', 'format' => 'date-time', 'description' => 'ISO-8601 datetime to schedule. CRM dispatches after human approval.'],
                            'timezone_display'   => ['type' => 'string', 'description' => 'IANA timezone name, e.g. Asia/Karachi'],
                            'author_member_urn'  => ['type' => 'string', 'description' => 'LinkedIn URN of the author. Omit to use the default connected account.'],
                        ],
                        'example' => [
                            'title_internal'   => 'Q2 product update post',
                            'post_body'        => "Excited to share our latest update!\n\nWe've been working hard on...",
                            'post_type'        => 'text',
                            'hashtags_json'    => ['product', 'launch', 'startup'],
                            'scheduled_at'     => '2026-06-01T09:00:00Z',
                            'timezone_display' => 'Asia/Karachi',
                        ],
                    ]]]],
                    'responses' => [
                        '201' => ['description' => 'Draft created', 'content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => ['post' => ['$ref' => '#/components/schemas/LinkedInPost']],
                        ]]]],
                    ],
                ],
            ],
        ];
    }

    private function socialPostItemPaths(): array
    {
        return [
            '/linkedin/posts/{id}' => [
                'get' => [
                    'operationId' => 'getLinkedInPost',
                    'summary'     => 'Get a LinkedIn post by ID',
                    'description' => 'Returns full post details including body, hashtags, schedule, and LinkedIn URN if published. Scope: social:read.',
                    'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses'   => ['200' => ['description' => 'Post detail', 'content' => ['application/json' => ['schema' => [
                        'type' => 'object',
                        'properties' => ['post' => ['$ref' => '#/components/schemas/LinkedInPost']],
                    ]]]]],
                ],
                'patch' => [
                    'operationId' => 'updateLinkedInPost',
                    'summary'     => 'Update a LinkedIn post draft',
                    'description' => 'Partially update an editable post (draft, ready_for_review, or failed). Changing post_body bumps content_version and invalidates pending confirmations. Scope: social:write.',
                    'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                        'type' => 'object',
                        'properties' => [
                            'title_internal'   => ['type' => 'string', 'maxLength' => 255],
                            'post_body'        => ['type' => 'string', 'maxLength' => 3000],
                            'post_type'        => ['type' => 'string', 'enum' => ['text', 'article_link', 'image']],
                            'article_url'      => ['type' => 'string', 'format' => 'uri'],
                            'hashtags_json'    => ['type' => 'array', 'items' => ['type' => 'string']],
                            'scheduled_at'     => ['type' => 'string', 'format' => 'date-time'],
                            'timezone_display' => ['type' => 'string'],
                        ],
                    ]]]],
                    'responses' => ['200' => ['description' => 'Post updated']],
                ],
                'delete' => [
                    'operationId' => 'deleteLinkedInPost',
                    'summary'     => 'Delete a LinkedIn post draft',
                    'description' => 'Soft-deletes an editable draft. Cannot delete published posts. Scope: social:write.',
                    'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses'   => ['200' => ['description' => 'Post deleted']],
                ],
            ],
            '/linkedin/posts/{id}/request-confirmation' => [
                'post' => [
                    'operationId' => 'requestPublishConfirmation',
                    'summary'     => 'Request human approval to publish or schedule a post',
                    'description' => 'Creates a CRM approval request. The post is NOT published until the human approves in the CRM. action=publish_now dispatches after approval; action=schedule queues for scheduled_at. Returns confirmation_token — poll getConfirmationStatus for result. Scope: social:publish.',
                    'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                        'type'     => 'object',
                        'required' => ['action'],
                        'properties' => [
                            'action'       => ['type' => 'string', 'enum' => ['publish_now', 'schedule'], 'description' => 'publish_now dispatches immediately after human approval. schedule queues for scheduled_at time.'],
                            'scheduled_at' => ['type' => 'string', 'format' => 'date-time', 'description' => 'Required when action=schedule. Must be a future datetime.'],
                            'timezone'     => ['type' => 'string', 'description' => 'IANA timezone for display, e.g. Asia/Karachi'],
                        ],
                        'example' => ['action' => 'schedule', 'scheduled_at' => '2026-06-01T09:00:00Z', 'timezone' => 'Asia/Karachi'],
                    ]]]],
                    'responses' => [
                        '201' => ['description' => 'Confirmation created — awaiting human approval', 'content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => [
                                'confirmation_token' => ['type' => 'string'],
                                'expires_at'         => ['type' => 'string', 'format' => 'date-time'],
                                'message'            => ['type' => 'string'],
                            ],
                        ]]]],
                    ],
                ],
            ],
            '/linkedin/posts/{id}/provider-status' => [
                'get' => [
                    'operationId' => 'getLinkedInProviderStatus',
                    'summary'     => 'Check if a post is live on LinkedIn',
                    'description' => 'Queries LinkedIn directly for the post lifecycle state. Returns exists_on_linkedin and post_url. Scope: social:read.',
                    'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses'   => ['200' => ['description' => 'Provider status']],
                ],
            ],
        ];
    }

    private function socialConfirmationMediaAnalyticsPaths(): array
    {
        return [
            '/linkedin/confirmations/{token}' => [
                'get' => [
                    'operationId' => 'getConfirmationStatus',
                    'summary'     => 'Poll a publish confirmation status',
                    'description' => 'Returns current status: pending (awaiting human approval), approved, rejected, used, or expired. If approved and action=publish_now, the CRM dispatches the publish job automatically. Scope: social:read.',
                    'parameters'  => [['name' => 'token', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]],
                    'responses'   => ['200' => ['description' => 'Confirmation status', 'content' => ['application/json' => ['schema' => [
                        'type' => 'object',
                        'properties' => ['confirmation' => ['$ref' => '#/components/schemas/Confirmation']],
                    ]]]]],
                ],
            ],
            '/linkedin/posts/{postId}/media' => [
                'post' => [
                    'operationId' => 'attachMediaToPost',
                    'summary'     => 'Associate a CRM media asset with a post',
                    'description' => 'Links a pre-uploaded CRM media asset (from the Media Library) to a post. The asset must be approved. For image posts, the CRM uploads it to LinkedIn automatically when publishing. Scope: social:write.',
                    'parameters'  => [['name' => 'postId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                        'type'     => 'object',
                        'required' => ['asset_id'],
                        'properties' => [
                            'asset_id'          => ['type' => 'integer', 'description' => 'ID of the approved media asset in the CRM Media Library'],
                            'display_order'     => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                            'is_featured'       => ['type' => 'boolean', 'default' => false],
                            'alt_text_override' => ['type' => 'string', 'maxLength' => 1000],
                        ],
                    ]]]],
                    'responses' => ['200' => ['description' => 'Asset attached to post']],
                ],
            ],
            '/media' => [
                'post' => [
                    'operationId' => 'uploadMediaAsset',
                    'summary'     => 'Upload an image to the CRM Media Library',
                    'description' => 'Creates a Media Library asset from a file upload or image URL. Returns asset_id ready for attachMediaToPost. auto_approve defaults to true. Scope: social:write.',
                    'requestBody' => ['required' => true, 'content' => ['multipart/form-data' => ['schema' => [
                        'type'     => 'object',
                        'required' => ['alt_text'],
                        'properties' => [
                            'file'          => ['type' => 'string', 'format' => 'binary', 'description' => 'Image file (jpg, png, gif, webp; max 10 MB). Provide file or image_url.'],
                            'image_url'     => ['type' => 'string', 'format' => 'uri', 'description' => 'URL the server will download. Alternative to file.'],
                            'title'         => ['type' => 'string', 'maxLength' => 255, 'description' => 'Optional internal label for the asset'],
                            'alt_text'      => ['type' => 'string', 'maxLength' => 500, 'description' => 'Accessibility alt text for the image (required)'],
                            'rights_status' => ['type' => 'string', 'enum' => ['owned', 'licensed', 'generated', 'unknown'], 'default' => 'generated'],
                            'auto_approve'  => ['type' => 'boolean', 'default' => true, 'description' => 'true = approved immediately; false = pending human review'],
                        ],
                    ]]]],
                    'responses' => [
                        '201' => ['description' => 'Asset created', 'content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => [
                                'asset_id'        => ['type' => 'integer', 'description' => 'Use this ID with attachMediaToPost'],
                                'filename'        => ['type' => 'string'],
                                'mime_type'       => ['type' => 'string'],
                                'approval_status' => ['type' => 'string', 'enum' => ['approved', 'pending_review']],
                                'storage_url'     => ['type' => 'string', 'format' => 'uri'],
                                'alt_text'        => ['type' => 'string'],
                            ],
                        ]]]],
                    ],
                ],
            ],

            '/linkedin/analytics/dashboard' => [
                'get' => [
                    'operationId' => 'getLinkedInInsightsDashboard',
                    'summary'     => 'LinkedIn insights dashboard',
                    'description' => 'Returns follower count and latest metrics (impressions, likes, clicks, comments) for the 10 most recent published posts per connected account. Scope: social:analytics.',
                    'responses'   => ['200' => ['description' => 'Insights dashboard data']],
                ],
            ],
            '/linkedin/analytics/posts/{postId}' => [
                'get' => [
                    'operationId' => 'getLinkedInPostMetrics',
                    'summary'     => 'Get analytics for a specific post',
                    'description' => 'Returns stored metrics (impressions, clicks, likes, comments, shares, engagement rate). Synced hourly. Scope: social:analytics.',
                    'parameters'  => [['name' => 'postId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses'   => ['200' => ['description' => 'Post metrics']],
                ],
            ],
        ];
    }
}
