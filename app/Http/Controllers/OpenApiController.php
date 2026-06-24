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
                'title'       => 'Applai – GPT Actions API',
                'version'     => '1.6.0',
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
                    'ApiDocument' => [
                        'type' => 'object',
                        'properties' => [
                            'document_id'        => ['type' => 'integer', 'description' => 'Stable document identity — never changes across versions'],
                            'name'               => ['type' => 'string'],
                            'document_type'      => ['type' => 'string', 'enum' => ['resume', 'cover_letter', 'proposal', 'portfolio', 'reference', 'contract', 'report', 'other']],
                            'description'        => ['type' => 'string', 'nullable' => true],
                            'is_sensitive'       => ['type' => 'boolean', 'description' => 'True when the filename or type suggests identity/credential content'],
                            'sensitive_warnings' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Human-readable warnings for sensitive documents'],
                            'version_count'      => ['type' => 'integer', 'description' => 'Total number of immutable versions stored'],
                            'current_version'    => ['$ref' => '#/components/schemas/ApiDocumentVersion'],
                            'entity_links'       => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ApiDocumentLink']],
                            'created_at'         => ['type' => 'string', 'format' => 'date-time'],
                            'updated_at'         => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'ApiDocumentVersion' => [
                        'type' => 'object',
                        'properties' => [
                            'version_id'        => ['type' => 'integer'],
                            'version_number'    => ['type' => 'integer', 'description' => 'Sequential version number starting at 1'],
                            'original_filename' => ['type' => 'string'],
                            'mime_type'         => ['type' => 'string'],
                            'size_bytes'        => ['type' => 'integer'],
                            'checksum'          => ['type' => 'string', 'nullable' => true, 'description' => 'sha256 hex digest; null for URL-based versions'],
                            'upload_source'     => ['type' => 'string', 'enum' => ['multipart', 'url', 'agent']],
                            'has_local_file'    => ['type' => 'boolean', 'description' => 'True when the file is stored on the CRM server'],
                            'public_url'        => ['type' => 'string', 'format' => 'uri', 'nullable' => true, 'description' => 'External URL for URL-registered documents'],
                            'version_notes'     => ['type' => 'string', 'nullable' => true],
                            'created_at'        => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'ApiDocumentLink' => [
                        'type' => 'object',
                        'properties' => [
                            'link_id'     => ['type' => 'integer'],
                            'entity_type' => ['type' => 'string', 'enum' => ['opportunity', 'contact', 'email_draft', 'follow_up']],
                            'entity_id'   => ['type' => 'integer'],
                            'created_at'  => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'LinkedDocument' => [
                        'type' => 'object',
                        'description' => 'A reference document linked via uploadDocument/addDocumentLink. NOT a sendable attachment.',
                        'properties' => [
                            'document_id'   => ['type' => 'integer'],
                            'name'          => ['type' => 'string'],
                            'document_type' => ['type' => 'string', 'enum' => ['resume', 'cover_letter', 'proposal', 'portfolio', 'reference', 'contract', 'report', 'other']],
                            'filename'      => ['type' => 'string', 'nullable' => true],
                            'mime_type'     => ['type' => 'string', 'nullable' => true],
                            'size_bytes'    => ['type' => 'integer', 'nullable' => true],
                            'download_url'  => ['type' => 'string', 'format' => 'uri'],
                            'linked_at'     => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
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
                            'linked_documents'             => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/LinkedDocument'], 'description' => 'Reference docs linked via uploadDocument — not sent with the email'],
                            'linked_document_count'        => ['type' => 'integer'],
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
                            'linked_documents'             => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/LinkedDocument'], 'description' => 'Reference docs linked via uploadDocument — not sendable'],
                            'linked_document_count'        => ['type' => 'integer'],
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
                    'patch' => [
                        'operationId' => 'updateOpportunity',
                        'summary'     => 'Update an opportunity',
                        'description' => 'Scope: opportunities:write.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'requestBody' => ['content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => [
                                'title'        => ['type' => 'string'],
                                'organization' => ['type' => 'string'],
                                'description'  => ['type' => 'string'],
                                'url'          => ['type' => 'string', 'format' => 'uri'],
                                'status'       => ['type' => 'string'],
                                'priority'     => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                                'deadline'     => ['type' => 'string', 'format' => 'date'],
                                'notes'        => ['type' => 'string'],
                            ],
                        ]]]],
                        'responses' => ['200' => ['description' => 'Opportunity updated'], '404' => ['description' => 'Not found']],
                    ],
                    'delete' => [
                        'operationId' => 'deleteOpportunity',
                        'summary'     => 'Delete an opportunity',
                        'description' => 'Soft delete. Scope: opportunities:delete.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses'   => ['200' => ['description' => 'Deleted'], '404' => ['description' => 'Not found']],
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
                '/opportunities/check-duplicate' => [
                    'get' => [
                        'operationId' => 'checkDuplicateOpportunity',
                        'summary'     => 'Check whether a duplicate opportunity exists',
                        'description' => 'Returns whether an opportunity with the same organization (company) and title (role) already exists for the user, without creating anything. Matches against soft-deleted records too. Scope: opportunities:read.',
                        'parameters'  => [
                            ['name' => 'company', 'in' => 'query', 'required' => true,  'schema' => ['type' => 'string'], 'description' => 'Organization name to match against organization'],
                            ['name' => 'role',    'in' => 'query', 'required' => true,  'schema' => ['type' => 'string'], 'description' => 'Role/title to match against title'],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Duplicate check result', 'content' => ['application/json' => ['schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'duplicate'   => ['type' => 'boolean'],
                                    'deleted'     => ['type' => 'boolean', 'description' => 'True when the matching opportunity is soft-deleted'],
                                    'opportunity' => ['oneOf' => [['$ref' => '#/components/schemas/Opportunity'], ['type' => 'null']]],
                                ],
                            ]]]],
                            '422' => ['description' => 'Both company and role query parameters are required'],
                        ],
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
                    'patch' => [
                        'operationId' => 'updateContact',
                        'summary'     => 'Update a contact',
                        'description' => 'Scope: contacts:write.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'requestBody' => ['content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => [
                                'status'    => ['type' => 'string'],
                                'company'   => ['type' => 'string'],
                                'job_title' => ['type' => 'string'],
                                'phone'     => ['type' => 'string'],
                                'notes'     => ['type' => 'string'],
                            ],
                        ]]]],
                        'responses' => ['200' => ['description' => 'Contact updated'], '404' => ['description' => 'Not found']],
                    ],
                    'delete' => [
                        'operationId' => 'deleteContact',
                        'summary'     => 'Delete a contact',
                        'description' => 'Soft delete. Scope: contacts:delete.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses'   => ['200' => ['description' => 'Deleted'], '404' => ['description' => 'Not found']],
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
                        'summary'     => 'Register an externally-hosted attachment by URL',
                        'description' => 'Registers a file via a public https:// URL. Use POST /attachments/upload instead if the file is available locally. Scope: attachments:write.',
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

                // ---------------------------------------------------------------
                // Documents
                // ---------------------------------------------------------------
                '/documents' => [
                    'get' => [
                        'operationId' => 'listDocuments',
                        'summary'     => 'List documents',
                        'description' => 'Returns documents for the authenticated user. Supports filters for document_type, is_sensitive, and keyword search. Scope: documents:read.',
                        'parameters'  => [
                            ['name' => 'document_type',  'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['resume', 'cover_letter', 'proposal', 'portfolio', 'reference', 'contract', 'report', 'other']]],
                            ['name' => 'is_sensitive',   'in' => 'query', 'schema' => ['type' => 'boolean'], 'description' => 'Filter to sensitive documents only'],
                            ['name' => 'q',              'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Keyword search on name and description'],
                            ['name' => 'limit',          'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20, 'maximum' => 100]],
                        ],
                        'responses' => [
                            '200' => ['description' => 'List of documents', 'content' => ['application/json' => ['schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'data'  => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ApiDocument']],
                                    'count' => ['type' => 'integer'],
                                ],
                            ]]]],
                        ],
                    ],
                    'post' => [
                        'operationId' => 'registerDocumentUrl',
                        'summary'     => 'Register an externally-hosted document by URL',
                        'description' => 'Store a document record pointing to a public https:// URL. Use POST /documents/upload to upload a local file to CRM storage instead. Supply entity IDs to link on creation. Scope: documents:write.',
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                            'type'     => 'object',
                            'required' => ['name', 'public_url', 'mime_type', 'size_bytes'],
                            'properties' => [
                                'name'           => ['type' => 'string', 'maxLength' => 500],
                                'public_url'     => ['type' => 'string', 'format' => 'uri', 'maxLength' => 2048],
                                'mime_type'      => ['type' => 'string', 'enum' => ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document','text/plain','text/csv','image/jpeg','image/png']],
                                'size_bytes'     => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20971520],
                                'document_type'  => ['type' => 'string', 'enum' => ['resume','cover_letter','proposal','portfolio','reference','contract','report','other']],
                                'description'    => ['type' => 'string', 'maxLength' => 2000],
                                'opportunity_id' => ['type' => 'integer'],
                                'contact_id'     => ['type' => 'integer'],
                                'email_draft_id' => ['type' => 'integer', 'description' => 'Does NOT trigger sending'],
                                'follow_up_id'   => ['type' => 'integer'],
                            ],
                        ]]]],
                        'responses' => [
                            '201' => ['description' => 'Document registered'],
                            '422' => ['description' => 'Validation error'],
                        ],
                    ],
                ],

                // ── Upload file to CRM storage ────────────────────────────────
                '/documents/upload' => [
                    'post' => [
                        'operationId' => 'uploadDocument',
                        'summary'     => 'Upload a file to CRM storage',
                        'description' => 'Stores a file and returns a CRM-hosted download URL. Supply entity IDs to link on creation (email_draft attach does NOT send it). Send file_base64 + filename as JSON — GPT Actions cannot stream multipart binary uploads. Scope: documents:write.',
                        'requestBody' => ['required' => true, 'content' => [
                            'application/json' => ['schema' => [
                                'type'     => 'object',
                                'required' => ['name', 'filename', 'file_base64'],
                                'properties' => [
                                    'file_base64'    => ['type' => 'string', 'format' => 'byte', 'description' => 'Base64-encoded file content (max 20 MB decoded)'],
                                    'filename'       => ['type' => 'string', 'maxLength' => 255, 'description' => 'Original filename including extension, e.g. resume.pdf'],
                                    'name'           => ['type' => 'string', 'maxLength' => 500],
                                    'document_type'  => ['type' => 'string', 'enum' => ['resume','cover_letter','proposal','portfolio','reference','contract','report','other']],
                                    'description'    => ['type' => 'string', 'maxLength' => 500],
                                    'opportunity_id' => ['type' => 'integer'],
                                    'contact_id'     => ['type' => 'integer'],
                                    'email_draft_id' => ['type' => 'integer'],
                                    'follow_up_id'   => ['type' => 'integer'],
                                ],
                            ]],
                            'multipart/form-data' => ['schema' => [
                                'type'     => 'object',
                                'required' => ['name', 'file'],
                                'properties' => [
                                    'file'           => ['type' => 'string', 'format' => 'binary', 'description' => 'File to upload (max 20 MB)'],
                                    'name'           => ['type' => 'string', 'maxLength' => 500],
                                    'document_type'  => ['type' => 'string', 'enum' => ['resume','cover_letter','proposal','portfolio','reference','contract','report','other']],
                                    'description'    => ['type' => 'string', 'maxLength' => 500],
                                    'opportunity_id' => ['type' => 'integer'],
                                    'contact_id'     => ['type' => 'integer'],
                                    'email_draft_id' => ['type' => 'integer'],
                                    'follow_up_id'   => ['type' => 'integer'],
                                ],
                            ]],
                        ]],
                        'responses' => [
                            '201' => ['description' => 'File stored on CRM server. Response includes CRM-hosted download_url.'],
                            '422' => ['description' => 'Validation error'],
                        ],
                    ],
                ],

                // ── Upload attachment file to CRM storage ─────────────────────
                '/attachments/upload' => [
                    'post' => [
                        'operationId' => 'uploadAttachment',
                        'summary'     => 'Upload an attachment file to CRM storage',
                        'description' => 'Stores a file and returns a CRM-hosted download URL for use as attachment_ids on drafts/follow-ups. Send file_base64 + filename as JSON — GPT Actions cannot stream multipart binary uploads. Scope: attachments:write.',
                        'requestBody' => ['required' => true, 'content' => [
                            'application/json' => ['schema' => [
                                'type'     => 'object',
                                'required' => ['filename', 'file_base64'],
                                'properties' => [
                                    'file_base64' => ['type' => 'string', 'format' => 'byte', 'description' => 'Base64-encoded file content (max 20 MB decoded)'],
                                    'filename'    => ['type' => 'string', 'maxLength' => 255, 'description' => 'Original filename including extension, e.g. resume.pdf'],
                                    'category'    => ['type' => 'string', 'enum' => ['cv_resume','cover_letter','portfolio','transcript','certificate','id_document','reference','sample_work','proposal','other']],
                                    'notes'       => ['type' => 'string', 'maxLength' => 500],
                                ],
                            ]],
                            'multipart/form-data' => ['schema' => [
                                'type'     => 'object',
                                'required' => ['file'],
                                'properties' => [
                                    'file'     => ['type' => 'string', 'format' => 'binary', 'description' => 'File to upload (max 20 MB)'],
                                    'category' => ['type' => 'string', 'enum' => ['cv_resume','cover_letter','portfolio','transcript','certificate','id_document','reference','sample_work','proposal','other']],
                                    'notes'    => ['type' => 'string', 'maxLength' => 500],
                                ],
                            ]],
                        ]],
                        'responses' => [
                            '201' => ['description' => 'File stored on CRM server. Use the returned id as attachment_ids on drafts/follow-ups.'],
                            '422' => ['description' => 'Validation error'],
                        ],
                    ],
                ],
                '/documents/{id}' => [
                    'get' => [
                        'operationId' => 'getDocument',
                        'summary'     => 'Get document by ID',
                        'description' => 'Returns document metadata, current version info, and all entity links. Scope: documents:read.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses'   => ['200' => ['description' => 'Document detail', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ApiDocument']]]]],
                    ],
                    'delete' => [
                        'operationId' => 'deleteDocument',
                        'summary'     => 'Delete a document',
                        'description' => 'Soft-deletes the document and all its version records. Stored files are retained on disk for audit. Scope: documents:write.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses'   => ['200' => ['description' => 'Document deleted']],
                    ],
                ],
                '/documents/{id}/links' => [
                    'post' => [
                        'operationId' => 'addDocumentLink',
                        'summary'     => 'Link a document to an entity',
                        'description' => 'Attaches the document to an opportunity, contact, email_draft, or follow_up. Idempotent — safe to call multiple times. Attaching to email_draft does NOT trigger sending — the draft stays pending user review. Scope: documents:write.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                            'type'     => 'object',
                            'required' => ['entity_type', 'entity_id'],
                            'properties' => [
                                'entity_type' => ['type' => 'string', 'enum' => ['opportunity', 'contact', 'email_draft', 'follow_up']],
                                'entity_id'   => ['type' => 'integer'],
                            ],
                        ]]]],
                        'responses' => [
                            '201' => ['description' => 'Link created'],
                            '200' => ['description' => 'Link already existed (idempotent)'],
                            '422' => ['description' => 'Entity not found or not owned by you'],
                        ],
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
                        'description' => 'Saves a draft linked to a contact. Never sent automatically. signature_id snapshots rendered HTML. attachment_ids (from uploadAttachment) are sendable; uploadDocument links appear separately as linked_documents (reference-only). Scope: drafts:create.',
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
                '/email-drafts/{id}' => [
                    'patch' => [
                        'operationId' => 'updateEmailDraft',
                        'summary'     => 'Update an email draft',
                        'description' => 'Only drafts can be edited. Scope: drafts:update.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'requestBody' => ['content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => [
                                'subject'        => ['type' => 'string', 'maxLength' => 500],
                                'body'           => ['type' => 'string', 'maxLength' => 50000],
                                'signature_id'   => ['type' => 'integer', 'nullable' => true],
                                'attachment_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 10],
                            ],
                        ]]]],
                        'responses' => ['200' => ['description' => 'Draft updated'], '404' => ['description' => 'Not found']],
                    ],
                    'delete' => [
                        'operationId' => 'deleteEmailDraft',
                        'summary'     => 'Delete an email draft',
                        'description' => 'Scope: drafts:delete.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses'   => ['200' => ['description' => 'Deleted'], '404' => ['description' => 'Not found']],
                    ],
                ],
                '/email-drafts/{id}/send' => [
                    'post' => [
                        'operationId' => 'sendEmailDraft',
                        'summary'     => 'Queue a draft for sending',
                        'description' => 'Schedules the draft for immediate sending via the CRM send pipeline (sets status=scheduled, scheduled_at=now). Never sends inline. 422 if not a draft or contact is suppressed. Scope: email:send.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses'   => ['200' => ['description' => 'Draft queued for sending'], '422' => ['description' => 'Not a draft or suppressed contact'], '404' => ['description' => 'Not found']],
                    ],
                ],
                '/email-drafts/{id}/send-test' => [
                    'post' => [
                        'operationId' => 'sendTestEmailDraft',
                        'summary'     => 'Send a test copy of a draft to a verification address',
                        'description' => 'Sends a one-off test copy of the rendered draft (subject prefixed with [TEST], body prefixed with a TEST EMAIL banner) to test_email. Does NOT send to the original recipient, does NOT change send_status, and is not logged as a real send. Scope: drafts:read.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                            'type'     => 'object',
                            'required' => ['test_email'],
                            'properties' => [
                                'test_email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 255, 'description' => 'Verification address the test copy is sent to'],
                            ],
                        ]]]],
                        'responses' => [
                            '200' => ['description' => 'Test email sent', 'content' => ['application/json' => ['schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'message'  => ['type' => 'string'],
                                    'draft_id' => ['type' => 'integer'],
                                ],
                            ]]]],
                            '404' => ['description' => 'Draft not found'],
                            '422' => ['description' => 'Validation error'],
                        ],
                    ],
                ],
                '/email-drafts/{id}/rendered-preview' => [
                    'get' => [
                        'operationId' => 'getDraftRenderedPreview',
                        'summary'     => 'Get full rendered preview of a draft',
                        'description' => 'Returns recipient, subject, rendered body, sendable attachments with validation status, linked_documents (reference-only, not sent), and confirmation metadata. Use to show the user the final email before sending. Scope: drafts:read.',
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
                                    'linked_documents'               => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/LinkedDocument'], 'description' => 'Reference docs linked via uploadDocument — NOT sent with this email'],
                                    'linked_document_count'          => ['type' => 'integer'],
                                    'audit_log_reference'            => ['type' => 'integer', 'nullable' => true],
                                    'notice'                         => ['type' => 'string'],
                                    'linked_documents_notice'        => ['type' => 'string'],
                                ],
                            ]]]],
                        ],
                    ],
                ],
                // ---------------------------------------------------------------
                // Follow-ups
                // ---------------------------------------------------------------
                '/follow-ups' => [
                    'get' => [
                        'operationId' => 'listFollowUps',
                        'summary'     => 'List follow-ups',
                        'description' => 'Filter by status, contact_id, opportunity_id. Scope: followups:read.',
                        'parameters'  => [
                            ['name' => 'status',         'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'contact_id',     'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'opportunity_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'limit',          'in' => 'query', 'schema' => ['type' => 'integer', 'maximum' => 100]],
                        ],
                        'responses' => ['200' => ['description' => 'List of follow-ups']],
                    ],
                    'post' => [
                        'operationId' => 'createFollowUp',
                        'summary'     => 'Schedule a follow-up reminder',
                        'description' => 'Creates a reminder-only follow-up. Auto-sending is always disabled. suggested_attachment_ids (uploadAttachment) are sendable; uploadDocument links appear separately as linked_documents (reference-only). Scope: followups:create.',
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
                '/follow-ups/{id}' => [
                    'patch' => [
                        'operationId' => 'updateFollowUp',
                        'summary'     => 'Update a follow-up',
                        'description' => 'Scope: followups:update. suggested_subject/suggested_body map to subject/body.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'requestBody' => ['content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => [
                                'status'            => ['type' => 'string'],
                                'due_at'            => ['type' => 'string', 'format' => 'date-time'],
                                'suggested_subject' => ['type' => 'string', 'maxLength' => 500],
                                'suggested_body'    => ['type' => 'string', 'maxLength' => 20000],
                            ],
                        ]]]],
                        'responses' => ['200' => ['description' => 'Follow-up updated'], '404' => ['description' => 'Not found']],
                    ],
                    'delete' => [
                        'operationId' => 'deleteFollowUp',
                        'summary'     => 'Delete a follow-up',
                        'description' => 'Hard delete (follow-ups have no soft deletes). Scope: followups:delete.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses'   => ['200' => ['description' => 'Deleted'], '404' => ['description' => 'Not found']],
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
                'title'       => 'Applai – LinkedIn Social Studio',
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
            '/linkedin/posts/{id}/publish' => [
                'post' => [
                    'operationId' => 'publishLinkedInPost',
                    'summary'     => 'Publish or schedule a post directly (MCP only)',
                    'description' => 'MCP/Cowork-only direct publish that bypasses the request-confirmation → human-approval gate. action=publish_now sets the post to publishing and dispatches the publish job; action=schedule sets it to scheduled for scheduled_at. Only available to MCP clients (403 otherwise); the post must be in status draft or approved. Scope: social:publish.',
                    'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                        'type'     => 'object',
                        'required' => ['action'],
                        'properties' => [
                            'action'       => ['type' => 'string', 'enum' => ['publish_now', 'schedule'], 'description' => 'publish_now dispatches immediately. schedule queues for scheduled_at.'],
                            'scheduled_at' => ['type' => 'string', 'format' => 'date-time', 'description' => 'Required when action=schedule. Must be a future datetime.'],
                            'timezone'     => ['type' => 'string', 'description' => 'IANA timezone used to interpret scheduled_at, e.g. Asia/Karachi. Defaults to UTC.'],
                        ],
                        'example' => ['action' => 'publish_now'],
                    ]]]],
                    'responses' => [
                        '200' => ['description' => 'Post queued for immediate publish or scheduled', 'content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => [
                                'message'      => ['type' => 'string'],
                                'post_id'      => ['type' => 'integer'],
                                'status'       => ['type' => 'string', 'enum' => ['publishing', 'scheduled']],
                                'scheduled_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                            ],
                        ]]]],
                        '403' => ['description' => 'Direct publish is only available to MCP/Cowork clients'],
                        '404' => ['description' => 'Post not found'],
                        '422' => ['description' => 'Post not in a publishable status or validation error'],
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

    // ── Agent Actions (extended backend domains) ──────────────────────────────

    /**
     * OpenAPI spec for the extended agent-backend domains added in the agent
     * build: content calendar, research papers, proposals, YouTube, freelance
     * projects, pipelines + scheduler, webhooks, analytics, tags, and bulk ops.
     * Server base is /api so the single document can span every domain prefix.
     */
    public function agentActions(): JsonResponse
    {
        $base = rtrim(config('app.url'), '/');

        $schema = [
            'openapi'    => '3.1.0',
            'info'       => [
                'title'       => 'Applai – Agent Actions API',
                'version'     => '1.0.0',
                'description' => 'Extended agent-backend endpoints: content calendar, research papers, proposals, YouTube, freelance projects, pipelines + scheduler, webhooks, analytics, tags, and bulk operations. All actions require an X-Api-Key header (format pocrm_live_<token>). Lifecycle actions (publish / send / complete / execute / run / test) only record CRM state — they never contact external services.',
            ],
            'servers'    => [['url' => $base . '/api', 'description' => 'Production CRM API']],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey', 'in' => 'header', 'name' => 'X-Api-Key',
                        'description' => 'API key generated in CRM → Settings → Integrations. Format: pocrm_live_<token>',
                    ],
                ],
            ],
            'security'   => [['ApiKeyAuth' => []]],
            'paths'      => $this->agentActionPaths(),
        ];

        return response()->json($schema)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Content-Type', 'application/json; charset=utf-8');
    }

    /** Build a JSON request body schema. */
    private function jsonBody(array $required, array $props): array
    {
        $schema = ['type' => 'object', 'properties' => $props];
        if (! empty($required)) {
            $schema['required'] = $required;
        }

        return ['required' => ! empty($required), 'content' => ['application/json' => ['schema' => $schema]]];
    }

    /** A single query parameter definition. */
    private function qp(string $name, string $type = 'string', ?array $enum = null): array
    {
        $schema = ['type' => $type];
        if ($enum) {
            $schema['enum'] = $enum;
        }

        return ['name' => $name, 'in' => 'query', 'required' => false, 'schema' => $schema];
    }

    /**
     * Generate standard CRUD (+ optional lifecycle action) path entries for one
     * resource. Config keys: prefix, resource, op (PascalCase id stem), name,
     * plural, readScope, writeScope, required[], props{}, indexParams[],
     * action{name,scope,summary,body}, readOnly(bool).
     */
    private function crudResource(array $c): array
    {
        $coll    = "{$c['prefix']}/{$c['resource']}";
        $item    = "{$coll}/{id}";
        $idParam = ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']];
        $name    = $c['name'];

        $collOps = [
            'get' => [
                'operationId' => 'list' . $c['op'],
                'summary'     => "List {$c['plural']}",
                'description' => "Scope: {$c['readScope']}.",
                'parameters'  => $c['indexParams'] ?? [],
                'responses'   => ['200' => ['description' => "List of {$c['plural']}"]],
            ],
        ];

        if (empty($c['readOnly'])) {
            $collOps['post'] = [
                'operationId' => 'create' . $c['op'],
                'summary'     => "Create {$name}",
                'description' => "Scope: {$c['writeScope']}.",
                'requestBody' => $this->jsonBody($c['required'] ?? [], $c['props'] ?? []),
                'responses'   => ['201' => ['description' => "{$name} created"], '422' => ['description' => 'Validation error']],
            ];
        }

        $itemOps = [
            'get' => [
                'operationId' => 'get' . $c['op'],
                'summary'     => "Get {$name} by ID",
                'description' => "Scope: {$c['readScope']}.",
                'parameters'  => [$idParam],
                'responses'   => ['200' => ['description' => "{$name} detail"], '404' => ['description' => 'Not found']],
            ],
        ];

        if (empty($c['readOnly'])) {
            $itemOps['patch'] = [
                'operationId' => 'update' . $c['op'],
                'summary'     => "Update {$name}",
                'description' => "Scope: {$c['writeScope']}.",
                'parameters'  => [$idParam],
                'requestBody' => $this->jsonBody([], $c['props'] ?? []),
                'responses'   => ['200' => ['description' => "{$name} updated"], '404' => ['description' => 'Not found']],
            ];
            $itemOps['delete'] = [
                'operationId' => 'delete' . $c['op'],
                'summary'     => "Delete {$name}",
                'description' => "Scope: {$c['writeScope']}.",
                'parameters'  => [$idParam],
                'responses'   => ['200' => ['description' => 'Deleted'], '404' => ['description' => 'Not found']],
            ];
        }

        $paths = [$coll => $collOps, $item => $itemOps];

        if (! empty($c['action'])) {
            $a = $c['action'];
            $op = [
                'operationId' => $a['name'] . $c['op'],
                'summary'     => $a['summary'],
                'description' => "Scope: {$a['scope']}. Records CRM state only.",
                'parameters'  => [$idParam],
                'responses'   => ['200' => ['description' => 'OK'], '201' => ['description' => 'Created'], '422' => ['description' => 'Invalid state']],
            ];
            if (! empty($a['body'])) {
                $op['requestBody'] = $this->jsonBody([], $a['body']);
            }
            $paths["{$item}/{$a['name']}"] = ['post' => $op];
        }

        return $paths;
    }

    private function agentActionPaths(): array
    {
        $meta   = ['meta' => ['type' => 'object', 'additionalProperties' => true]];
        $limit  = $this->qp('limit', 'integer');
        $search = $this->qp('search');

        $resources = [
            // Content calendar
            [
                'prefix' => '/content/v1', 'resource' => 'items', 'op' => 'ContentItem',
                'name' => 'content item', 'plural' => 'content items',
                'readScope' => 'content:read', 'writeScope' => 'content:write',
                'required' => ['title'],
                'props' => [
                    'title' => ['type' => 'string'], 'content_type' => ['type' => 'string'],
                    'channel' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => ['idea', 'draft', 'scheduled', 'published', 'archived']],
                    'body' => ['type' => 'string'], 'notes' => ['type' => 'string'],
                    'scheduled_for' => ['type' => 'string', 'format' => 'date-time'],
                    'published_url' => ['type' => 'string', 'format' => 'uri'],
                ] + $meta,
                'indexParams' => [$this->qp('status'), $this->qp('content_type'), $this->qp('channel'), $this->qp('from'), $this->qp('to'), $search, $limit],
                'action' => ['name' => 'publish', 'scope' => 'content:publish', 'summary' => 'Mark a content item published', 'body' => ['published_url' => ['type' => 'string', 'format' => 'uri']]],
            ],
            // Research papers
            [
                'prefix' => '/research/v1', 'resource' => 'papers', 'op' => 'ResearchPaper',
                'name' => 'research paper', 'plural' => 'research papers',
                'readScope' => 'research:read', 'writeScope' => 'research:write',
                'required' => ['title'],
                'props' => [
                    'title' => ['type' => 'string'], 'authors' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'abstract' => ['type' => 'string'], 'url' => ['type' => 'string', 'format' => 'uri'],
                    'pdf_url' => ['type' => 'string', 'format' => 'uri'], 'arxiv_id' => ['type' => 'string'],
                    'doi' => ['type' => 'string'], 'venue' => ['type' => 'string'],
                    'published_date' => ['type' => 'string', 'format' => 'date'],
                    'status' => ['type' => 'string', 'enum' => ['to_read', 'reading', 'read', 'archived']],
                    'notes' => ['type' => 'string'],
                ] + $meta,
                'indexParams' => [$this->qp('status'), $this->qp('venue'), $this->qp('arxiv_id'), $search, $limit],
            ],
            // Proposals
            [
                'prefix' => '/proposals/v1', 'resource' => 'proposals', 'op' => 'Proposal',
                'name' => 'proposal', 'plural' => 'proposals',
                'readScope' => 'proposals:read', 'writeScope' => 'proposals:write',
                'required' => ['title'],
                'props' => [
                    'title' => ['type' => 'string'], 'contact_id' => ['type' => 'integer'],
                    'opportunity_id' => ['type' => 'integer'],
                    'status' => ['type' => 'string', 'enum' => ['draft', 'sent', 'accepted', 'rejected', 'expired']],
                    'amount' => ['type' => 'number'], 'currency' => ['type' => 'string'],
                    'body' => ['type' => 'string'], 'url' => ['type' => 'string', 'format' => 'uri'],
                    'valid_until' => ['type' => 'string', 'format' => 'date'],
                ] + $meta,
                'indexParams' => [$this->qp('status'), $this->qp('contact_id', 'integer'), $this->qp('opportunity_id', 'integer'), $search, $limit],
                'action' => ['name' => 'send', 'scope' => 'proposals:write', 'summary' => 'Mark a draft proposal as sent'],
            ],
            // YouTube videos
            [
                'prefix' => '/youtube/v1', 'resource' => 'videos', 'op' => 'YoutubeVideo',
                'name' => 'YouTube video', 'plural' => 'YouTube videos',
                'readScope' => 'youtube:read', 'writeScope' => 'youtube:write',
                'required' => ['title'],
                'props' => [
                    'title' => ['type' => 'string'], 'video_id' => ['type' => 'string'],
                    'url' => ['type' => 'string', 'format' => 'uri'], 'description' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => ['idea', 'scripting', 'recording', 'editing', 'scheduled', 'published', 'archived']],
                    'visibility' => ['type' => 'string', 'enum' => ['public', 'unlisted', 'private']],
                    'channel' => ['type' => 'string'], 'thumbnail_url' => ['type' => 'string', 'format' => 'uri'],
                    'duration_seconds' => ['type' => 'integer'], 'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'view_count' => ['type' => 'integer'], 'like_count' => ['type' => 'integer'], 'comment_count' => ['type' => 'integer'],
                    'scheduled_for' => ['type' => 'string', 'format' => 'date-time'],
                ] + $meta,
                'indexParams' => [$this->qp('status'), $this->qp('visibility'), $this->qp('channel'), $this->qp('video_id'), $this->qp('from'), $this->qp('to'), $search, $limit],
                'action' => ['name' => 'publish', 'scope' => 'youtube:write', 'summary' => 'Mark a video published', 'body' => ['video_id' => ['type' => 'string'], 'url' => ['type' => 'string', 'format' => 'uri']]],
            ],
            // Freelance projects
            [
                'prefix' => '/freelance/v1', 'resource' => 'projects', 'op' => 'FreelanceProject',
                'name' => 'freelance project', 'plural' => 'freelance projects',
                'readScope' => 'freelance:read', 'writeScope' => 'freelance:write',
                'required' => ['title'],
                'props' => [
                    'title' => ['type' => 'string'], 'contact_id' => ['type' => 'integer'],
                    'opportunity_id' => ['type' => 'integer'], 'client_name' => ['type' => 'string'],
                    'platform' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => ['lead', 'proposal', 'active', 'on_hold', 'completed', 'cancelled']],
                    'rate_type' => ['type' => 'string', 'enum' => ['hourly', 'fixed']],
                    'rate' => ['type' => 'number'], 'budget' => ['type' => 'number'], 'currency' => ['type' => 'string'],
                    'estimated_hours' => ['type' => 'number'], 'hours_logged' => ['type' => 'number'],
                    'description' => ['type' => 'string'], 'url' => ['type' => 'string', 'format' => 'uri'],
                    'start_date' => ['type' => 'string', 'format' => 'date'], 'due_date' => ['type' => 'string', 'format' => 'date'],
                ] + $meta,
                'indexParams' => [$this->qp('status'), $this->qp('platform'), $this->qp('contact_id', 'integer'), $this->qp('opportunity_id', 'integer'), $search, $limit],
                'action' => ['name' => 'complete', 'scope' => 'freelance:write', 'summary' => 'Mark a project completed'],
            ],
            // Pipelines
            [
                'prefix' => '/pipelines/v1', 'resource' => 'pipelines', 'op' => 'Pipeline',
                'name' => 'pipeline', 'plural' => 'pipelines',
                'readScope' => 'pipelines:read', 'writeScope' => 'pipelines:write',
                'required' => ['name'],
                'props' => [
                    'name' => ['type' => 'string'], 'description' => ['type' => 'string'],
                    'trigger_type' => ['type' => 'string', 'enum' => ['manual', 'scheduled', 'webhook']],
                    'status' => ['type' => 'string', 'enum' => ['active', 'paused', 'archived']],
                    'steps' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                    'config' => ['type' => 'object', 'additionalProperties' => true],
                ] + $meta,
                'indexParams' => [$this->qp('status'), $this->qp('trigger_type'), $search, $limit],
                'action' => ['name' => 'execute', 'scope' => 'pipelines:execute', 'summary' => 'Trigger a pipeline run (records a PipelineRun)', 'body' => ['input' => ['type' => 'object', 'additionalProperties' => true], 'trigger_source' => ['type' => 'string', 'enum' => ['manual', 'scheduled', 'webhook', 'api']]]],
            ],
            // Scheduled jobs
            [
                'prefix' => '/pipelines/v1', 'resource' => 'scheduled-jobs', 'op' => 'ScheduledJob',
                'name' => 'scheduled job', 'plural' => 'scheduled jobs',
                'readScope' => 'scheduler:read', 'writeScope' => 'scheduler:write',
                'required' => ['name'],
                'props' => [
                    'name' => ['type' => 'string'], 'description' => ['type' => 'string'],
                    'job_type' => ['type' => 'string'], 'pipeline_id' => ['type' => 'integer'],
                    'frequency' => ['type' => 'string', 'enum' => ['once', 'hourly', 'daily', 'weekly', 'monthly', 'cron']],
                    'cron_expression' => ['type' => 'string'],
                    'run_at' => ['type' => 'string', 'format' => 'date-time'],
                    'next_run_at' => ['type' => 'string', 'format' => 'date-time'],
                    'status' => ['type' => 'string', 'enum' => ['active', 'paused']],
                    'payload' => ['type' => 'object', 'additionalProperties' => true],
                ] + $meta,
                'indexParams' => [$this->qp('status'), $this->qp('job_type'), $this->qp('pipeline_id', 'integer'), $search, $limit],
                'action' => ['name' => 'run', 'scope' => 'scheduler:write', 'summary' => 'Manually run a scheduled job'],
            ],
            // Pipeline runs (read-only log)
            [
                'prefix' => '/pipelines/v1', 'resource' => 'runs', 'op' => 'PipelineRun',
                'name' => 'pipeline run', 'plural' => 'pipeline runs',
                'readScope' => 'pipelines:read', 'writeScope' => 'pipelines:read', 'readOnly' => true,
                'indexParams' => [$this->qp('pipeline_id', 'integer'), $this->qp('status'), $this->qp('trigger_source'), $limit],
            ],
            // Webhooks
            [
                'prefix' => '/webhooks/v1', 'resource' => 'webhooks', 'op' => 'Webhook',
                'name' => 'webhook', 'plural' => 'webhooks',
                'readScope' => 'webhooks:read', 'writeScope' => 'webhooks:write',
                'required' => ['name', 'url'],
                'props' => [
                    'name' => ['type' => 'string'], 'url' => ['type' => 'string', 'format' => 'uri'],
                    'events' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'secret' => ['type' => 'string', 'description' => 'Write-only; never returned.'],
                    'status' => ['type' => 'string', 'enum' => ['active', 'paused']],
                ] + $meta,
                'indexParams' => [$this->qp('status'), $search, $limit],
                'action' => ['name' => 'test', 'scope' => 'webhooks:write', 'summary' => 'Record a test delivery', 'body' => ['event' => ['type' => 'string'], 'payload' => ['type' => 'object', 'additionalProperties' => true]]],
            ],
            // Webhook deliveries (read-only log)
            [
                'prefix' => '/webhooks/v1', 'resource' => 'deliveries', 'op' => 'WebhookDelivery',
                'name' => 'webhook delivery', 'plural' => 'webhook deliveries',
                'readScope' => 'webhooks:read', 'writeScope' => 'webhooks:read', 'readOnly' => true,
                'indexParams' => [$this->qp('webhook_id', 'integer'), $this->qp('status'), $this->qp('event'), $limit],
            ],
            // Tags
            [
                'prefix' => '/tags/v1', 'resource' => 'tags', 'op' => 'Tag',
                'name' => 'tag', 'plural' => 'tags',
                'readScope' => 'tags:read', 'writeScope' => 'tags:write',
                'required' => ['name'],
                'props' => [
                    'name' => ['type' => 'string'], 'color' => ['type' => 'string'], 'slug' => ['type' => 'string'],
                ],
                'indexParams' => [$search, $limit],
            ],
        ];

        $paths = [];
        foreach ($resources as $r) {
            $paths = array_merge($paths, $this->crudResource($r));
        }

        // Non-uniform endpoints.
        $entityEnum = ['contact', 'opportunity'];

        $paths['/tags/v1/tags/attach'] = ['post' => [
            'operationId' => 'attachTags', 'summary' => 'Attach tags to a contact or opportunity',
            'description' => 'Scope: tags:write. Provide tag_ids (existing) and/or tags (names, created if missing).',
            'requestBody' => $this->jsonBody(['entity', 'id'], [
                'entity' => ['type' => 'string', 'enum' => $entityEnum], 'id' => ['type' => 'integer'],
                'tag_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            ]),
            'responses' => ['200' => ['description' => 'Updated tag list'], '404' => ['description' => 'Entity not found'], '422' => ['description' => 'Validation error']],
        ]];
        $paths['/tags/v1/tags/detach'] = ['post' => [
            'operationId' => 'detachTags', 'summary' => 'Detach tags from a contact or opportunity',
            'description' => 'Scope: tags:write.',
            'requestBody' => $this->jsonBody(['entity', 'id', 'tag_ids'], [
                'entity' => ['type' => 'string', 'enum' => $entityEnum], 'id' => ['type' => 'integer'],
                'tag_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
            ]),
            'responses' => ['200' => ['description' => 'Updated tag list'], '404' => ['description' => 'Entity not found']],
        ]];
        $paths['/tags/v1/tags/on/{entity}/{id}'] = ['get' => [
            'operationId' => 'tagsOnEntity', 'summary' => 'List tags attached to a contact or opportunity',
            'description' => 'Scope: tags:read.',
            'parameters' => [
                ['name' => 'entity', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'enum' => $entityEnum]],
                ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
            ],
            'responses' => ['200' => ['description' => 'Tag list'], '404' => ['description' => 'Entity not found']],
        ]];

        // Analytics (read-only aggregations).
        foreach ([
            'summary'       => 'Cross-domain entity counts and key status breakdowns',
            'opportunities' => 'Opportunity pipeline by status and priority',
            'revenue'       => 'Proposal and freelance revenue aggregations',
            'content'       => 'Content and YouTube publishing analytics',
        ] as $path => $summary) {
            $paths["/analytics/v1/{$path}"] = ['get' => [
                'operationId' => 'analytics' . ucfirst($path), 'summary' => $summary,
                'description' => 'Scope: analytics:read.',
                'responses' => ['200' => ['description' => 'Aggregated metrics']],
            ]];
        }

        // Bulk operations (under the core gpt/v1 prefix).
        $paths['/gpt/v1/bulk'] = ['post' => [
            'operationId' => 'bulkOperation', 'summary' => 'Bulk update or delete entities',
            'description' => 'Scope: bulk:write. Per-id partial success; returns a results array. entity ∈ opportunities|contacts|follow_ups; operation ∈ update|delete; max 100 ids.',
            'requestBody' => $this->jsonBody(['entity', 'operation', 'ids'], [
                'entity' => ['type' => 'string', 'enum' => ['opportunities', 'contacts', 'follow_ups']],
                'operation' => ['type' => 'string', 'enum' => ['update', 'delete']],
                'ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 100],
                'data' => ['type' => 'object', 'additionalProperties' => true, 'description' => 'Required for update.'],
            ]),
            'responses' => ['200' => ['description' => 'Per-id results'], '422' => ['description' => 'Validation error']],
        ]];

        // Outreach pipeline (single-call multi-step flow, under the core gpt/v1 prefix).
        $paths['/gpt/v1/pipeline/execute'] = ['post' => [
            'operationId' => 'executeOutreachPipeline', 'summary' => 'Execute a multi-step outreach pipeline in one call',
            'description' => 'Scope: pipelines:execute. Runs contact upsert → opportunity create/upsert (dedup by title+organization) → email draft (skipped if the contact is suppressed) → follow-up reminder → tags, in a single request. Drafts are never auto-sent. Returns the steps completed and the created entity IDs.',
            'requestBody' => $this->jsonBody(['pipeline', 'data'], [
                'pipeline' => ['type' => 'string', 'enum' => ['job_application', 'networking_outreach', 'freelance_pitch', 'research_contact', 'grant_application'], 'description' => 'Pipeline type; maps to the created opportunity type.'],
                'data' => [
                    'type' => 'object',
                    'required' => ['company_name', 'role_title', 'contact_email', 'email_subject', 'email_body'],
                    'properties' => [
                        'company_name'       => ['type' => 'string', 'maxLength' => 255],
                        'role_title'         => ['type' => 'string', 'maxLength' => 255],
                        'contact_email'      => ['type' => 'string', 'format' => 'email', 'maxLength' => 255],
                        'contact_name'       => ['type' => 'string', 'maxLength' => 255],
                        'email_subject'      => ['type' => 'string', 'maxLength' => 500],
                        'email_body'         => ['type' => 'string', 'maxLength' => 50000],
                        'follow_up_days'     => ['type' => 'integer', 'minimum' => 1, 'maximum' => 90, 'description' => 'Days from now for the follow-up reminder (default 7).'],
                        'tags'               => ['type' => 'array', 'items' => ['type' => 'string', 'maxLength' => 100], 'maxItems' => 20, 'description' => 'Tag names applied to the opportunity (created if missing).'],
                        'apply_url'          => ['type' => 'string', 'format' => 'uri', 'maxLength' => 2048],
                        'opportunity_status' => ['type' => 'string', 'enum' => ['draft', 'active', 'waiting_reply', 'replied', 'interview', 'offer', 'rejected', 'withdrawn', 'closed']],
                    ],
                ],
            ]),
            'responses' => [
                '201' => ['description' => 'Pipeline executed', 'content' => ['application/json' => ['schema' => [
                    'type' => 'object',
                    'properties' => [
                        'pipeline'        => ['type' => 'string'],
                        'steps_completed' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'contact_id'      => ['type' => 'integer'],
                        'opportunity_id'  => ['type' => 'integer'],
                        'draft_id'        => ['type' => 'integer', 'nullable' => true, 'description' => 'Null when the draft was skipped (suppressed contact).'],
                        'followup_id'     => ['type' => 'integer'],
                        'errors'          => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ]]]],
                '422' => ['description' => 'Validation error'],
            ],
        ]];

        // Bulk create (up to 20 items per request, per-item partial success; under the core gpt/v1 prefix).
        $bulkCreateResponse = [
            '201' => ['description' => 'Per-item create results', 'content' => ['application/json' => ['schema' => [
                'type' => 'object',
                'properties' => [
                    'created' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true], 'description' => 'Items created in this request.'],
                    'skipped' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true], 'description' => 'Items skipped as duplicates (with the existing id).'],
                    'errors'  => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true], 'description' => 'Items that failed, each with its index and error.'],
                ],
            ]]]],
            '422' => ['description' => 'Validation error'],
        ];

        $paths['/gpt/v1/bulk/opportunities'] = ['post' => [
            'operationId' => 'bulkCreateOpportunities', 'summary' => 'Bulk create opportunities',
            'description' => 'Scope: bulk:write. Creates up to 20 opportunities; deduplicates by title+company (duplicates are returned in skipped). Per-item partial success.',
            'requestBody' => $this->jsonBody(['opportunities'], [
                'opportunities' => [
                    'type' => 'array', 'minItems' => 1, 'maxItems' => 20,
                    'items' => [
                        'type' => 'object',
                        'required' => ['title', 'company'],
                        'properties' => [
                            'title'     => ['type' => 'string', 'maxLength' => 255],
                            'company'   => ['type' => 'string', 'maxLength' => 255],
                            'type'      => ['type' => 'string', 'enum' => ['job', 'scholarship', 'research', 'grant', 'networking']],
                            'status'    => ['type' => 'string', 'enum' => ['draft', 'active', 'waiting_reply', 'replied', 'interview', 'offer', 'rejected', 'withdrawn', 'closed']],
                            'priority'  => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']],
                            'apply_url' => ['type' => 'string', 'format' => 'uri', 'maxLength' => 2048],
                            'notes'     => ['type' => 'string', 'maxLength' => 5000],
                            'tags'      => ['type' => 'array', 'items' => ['type' => 'string', 'maxLength' => 100], 'maxItems' => 10],
                        ],
                    ],
                ],
            ]),
            'responses' => $bulkCreateResponse,
        ]];

        $paths['/gpt/v1/bulk/contacts'] = ['post' => [
            'operationId' => 'bulkCreateContacts', 'summary' => 'Bulk create contacts',
            'description' => 'Scope: bulk:write. Creates up to 20 contacts; deduplicates by email (duplicates are returned in skipped). Per-item partial success.',
            'requestBody' => $this->jsonBody(['contacts'], [
                'contacts' => [
                    'type' => 'array', 'minItems' => 1, 'maxItems' => 20,
                    'items' => [
                        'type' => 'object',
                        'required' => ['email'],
                        'properties' => [
                            'email'        => ['type' => 'string', 'format' => 'email', 'maxLength' => 255],
                            'first_name'   => ['type' => 'string', 'maxLength' => 100],
                            'last_name'    => ['type' => 'string', 'maxLength' => 100],
                            'company'      => ['type' => 'string', 'maxLength' => 255],
                            'job_title'    => ['type' => 'string', 'maxLength' => 255],
                            'phone'        => ['type' => 'string', 'maxLength' => 50],
                            'linkedin_url' => ['type' => 'string', 'format' => 'uri', 'maxLength' => 2048],
                            'notes'        => ['type' => 'string', 'maxLength' => 5000],
                            'status'       => ['type' => 'string', 'enum' => ['active', 'inactive', 'suppressed', 'bounced']],
                        ],
                    ],
                ],
            ]),
            'responses' => $bulkCreateResponse,
        ]];

        $paths['/gpt/v1/bulk/drafts'] = ['post' => [
            'operationId' => 'bulkCreateDrafts', 'summary' => 'Bulk create email drafts',
            'description' => 'Scope: bulk:write. Creates up to 20 email drafts. Items referencing a missing/suppressed contact or missing opportunity are reported in errors (skipped is always empty). Drafts are never auto-sent. Per-item partial success.',
            'requestBody' => $this->jsonBody(['drafts'], [
                'drafts' => [
                    'type' => 'array', 'minItems' => 1, 'maxItems' => 20,
                    'items' => [
                        'type' => 'object',
                        'required' => ['contact_id', 'subject', 'body'],
                        'properties' => [
                            'contact_id'     => ['type' => 'integer'],
                            'subject'        => ['type' => 'string', 'maxLength' => 500],
                            'body'           => ['type' => 'string', 'maxLength' => 50000],
                            'opportunity_id' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ]),
            'responses' => $bulkCreateResponse,
        ]];

        return $paths;
    }
}
