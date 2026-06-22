import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { createCrmServer } from './server.js';

// ---------------------------------------------------------------------------
// stdio entry point (Claude Desktop, Cursor, etc.)
//
// This remains the default entry (`node dist/index.js`) so existing Claude
// Desktop configurations keep working unchanged. The HTTP/SSE transport lives
// in http.ts and is started separately (`node dist/http.js`). Both share the
// exact same tools/resources via createCrmServer().
// ---------------------------------------------------------------------------
const server = createCrmServer();
const transport = new StdioServerTransport();
await server.connect(transport);
console.error('Personal CRM MCP server running on stdio');
