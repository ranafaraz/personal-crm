## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

### Session start (MANDATORY)

At the start of every new session, before doing anything else, run:

```powershell
$env:PATH = [System.Environment]::GetEnvironmentVariable("PATH","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("PATH","User")
& (Get-Content graphify-out\.graphify_python) -m graphify update .
```

This is AST-only (no API cost, no LLM) and takes ~10-20s. It ensures the graph reflects any code written since the last session. Do not skip this step — a stale graph wastes more time than the update costs.

### Rules

- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code during a session, run the update command again to keep the graph current.
