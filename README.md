# WP A2E

Workflow engine for WordPress 7.0 — orchestrate abilities into multi-step workflows where each step's output feeds the next.

The GPL alternative to n8n. No external dependencies, no restrictive licenses.

**No core modifications.** Pure plugin, GPL-2.0.

## The Stack

```
Connectors (vault)  →  Abilities (nodes)  →  A2E (workflows)
credentials            individual actions     chains of actions
```

| Layer | Plugin | What it does |
|-------|--------|-------------|
| Credentials | WP Connector Factory | Encrypted vault (AES-256-CBC), AI + generic connectors |
| Nodes | WP Ability Factory | 6 ability types: AI, API, WP, Function, Hook, Cron |
| Orchestration | **WP A2E** | Sequential workflow execution with data store |

## Requirements

- WordPress 7.0+
- PHP 8.1+
- WP Ability Factory (recommended — provides the abilities A2E orchestrates)
- WP Connector Factory (recommended — provides credential vault)

## How it works

A workflow is an ordered list of steps. Each step executes an operation and stores its result in a shared data store. Subsequent steps reference previous results using `/step_id` path syntax.

```php
$workflow = [
    // Step 1: Call an ability to get posts
    ['id' => 'posts',  'type' => 'ExecuteAbility', 'ability' => 'my/list-posts', 'input' => ['posts_per_page' => 10]],

    // Step 2: Filter results — /posts references step 1's output
    ['id' => 'recent', 'type' => 'FilterData', 'data' => '/posts', 'field' => 'status', 'operator' => 'eq', 'value' => 'publish'],

    // Step 3: Extract just the titles
    ['id' => 'titles', 'type' => 'TransformData', 'data' => '/recent', 'operation' => 'map', 'field' => 'title'],

    // Step 4: Summarize with AI — /titles feeds into the ability input
    ['id' => 'summary', 'type' => 'ExecuteAbility', 'ability' => 'my/summarize', 'input' => ['content' => '/titles']],
];
```

After execution, the data store contains every step's result:

```
/posts   → [{ID: 1, title: "Hello", ...}, ...]
/recent  → [{ID: 1, title: "Hello", ...}]
/titles  → ["Hello"]
/summary → "The site has one post titled Hello..."
```

## Operations (9)

### ExecuteAbility
Call any registered WordPress ability. This is the bridge between A2E workflows and the Ability Factory ecosystem.

```json
{"id": "fetch", "type": "ExecuteAbility", "ability": "my/list-posts", "input": {"posts_per_page": 5}}
```

### ApiCall
Direct HTTP request without needing a pre-defined ability.

```json
{"id": "api", "type": "ApiCall", "url": "https://api.example.com/data", "method": "GET", "headers": {"Authorization": "Bearer token"}}
```

### FilterData
Filter an array by field value. Operators: `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `contains`, `startsWith`, `endsWith`, `in`, `exists`, `empty`.

```json
{"id": "active", "type": "FilterData", "data": "/users", "field": "status", "operator": "eq", "value": "active"}
```

### TransformData
Transform arrays. Operations: `select`, `sort`, `group`, `aggregate`, `map`, `flatten`, `unique`, `reverse`, `slice`, `count`.

```json
{"id": "names", "type": "TransformData", "data": "/users", "operation": "map", "field": "name"}
```

### Conditional
Branch execution based on a condition.

```json
{"id": "check", "type": "Conditional", "left": "/count", "operator": "gt", "right": 0,
  "then": [{"id": "notify", "type": "ExecuteAbility", "ability": "my/send-email", "input": {"body": "/titles"}}],
  "else": [{"id": "log", "type": "StoreData", "key": "message", "value": "No posts found"}]
}
```

### Loop
Iterate over an array, executing sub-steps for each item. Max 1000 iterations.

```json
{"id": "process", "type": "Loop", "data": "/posts", "as": "_item", "index_as": "_index",
  "steps": [{"id": "tag", "type": "ExecuteAbility", "ability": "my/tag-post", "input": {"post_id": "/_item.ID"}}]
}
```

### StoreData
Write a value to the data store.

```json
{"id": "save", "type": "StoreData", "key": "final_result", "value": "/processed"}
```

### Wait
Pause execution (max 30 seconds).

```json
{"id": "pause", "type": "Wait", "seconds": 2}
```

### MergeData
Combine data from multiple sources. Modes: `concat`, `union`, `intersect`, `deepMerge`, `zip`.

```json
{"id": "combined", "type": "MergeData", "sources": ["/list_a", "/list_b"], "mode": "concat"}
```

## Path References

Values starting with `/` are resolved from the data store at execution time:

| Syntax | Resolves to |
|--------|------------|
| `/step_id` | Full result of that step |
| `/step_id.field` | Nested field access |
| `/step_id.0.title` | Array index + field |
| `/step_id.length` | Count of array result |
| `plain value` | Passed as-is (no resolution) |

## REST API

```
GET    /wp-a2e/v1/health                         Service status
GET    /wp-a2e/v1/workflows                      List all workflows
POST   /wp-a2e/v1/workflows                      Create/update workflow
GET    /wp-a2e/v1/workflows/{id}                  Get workflow definition
DELETE /wp-a2e/v1/workflows/{id}                  Delete workflow
POST   /wp-a2e/v1/workflows/{id}/execute          Execute saved workflow
POST   /wp-a2e/v1/execute                         Execute inline workflow (JSON body)
```

### Execute a saved workflow

```bash
curl -X POST http://localhost/wp7/index.php?rest_route=/wp-a2e/v1/workflows/my-flow/execute \
  -H "Content-Type: application/json" \
  -d '{"input": {"key": "value"}}'
```

### Execute inline

```bash
curl -X POST http://localhost/wp7/index.php?rest_route=/wp-a2e/v1/execute \
  -H "Content-Type: application/json" \
  -d '{"steps": [{"id": "s1", "type": "StoreData", "key": "hello", "value": "world"}]}'
```

## MCP Integration

A2E registers two abilities in the WordPress Abilities API:

| Ability | Description |
|---------|-------------|
| `a2e/execute-workflow` | Execute a saved workflow by ID |
| `a2e/list-workflows` | List all available workflows |

Any MCP-connected AI agent can discover and execute workflows:

```
Agent → MCP Adapter → a2e/execute-workflow → A2E Engine → Abilities → Results
```

## Architecture

```
wp-a2e/
├── wp-a2e.php                      Bootstrap, singleton
├── includes/
│   ├── class-data-store.php        In-memory key-value store per execution
│   ├── class-path-resolver.php     /step_id.field resolution engine
│   ├── class-executor.php          Sequential step executor with error handling
│   ├── class-workflow-storage.php  CRUD for workflow definitions (wp_options)
│   ├── class-rest-api.php          REST endpoints for CRUD and execution
│   ├── class-abilities.php         Registers a2e/* abilities for MCP discovery
│   ├── class-admin-page.php        Visual workflow builder
│   └── operations/
│       ├── class-execute-ability.php
│       ├── class-api-call.php
│       ├── class-filter-data.php
│       ├── class-transform-data.php
│       ├── class-conditional.php
│       ├── class-loop.php
│       ├── class-store-data.php
│       ├── class-wait.php
│       └── class-merge-data.php
├── js/admin.js
└── css/admin.css
```

## Error Handling

- Steps that return `WP_Error` stop the workflow by default
- Add `"continue_on_error": true` to a step to continue on failure
- Error details stored in the data store under the step's ID
- Execution result includes `errors` array with step ID, type, code, and message

## License

GPL-2.0-or-later
