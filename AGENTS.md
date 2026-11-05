<!-- aikit:begin - managed baseline, do not edit -->

## Baseline

Shared rules for every repo. Apply to any stack.

### Commits

- Format: [Conventional Commits](https://www.conventionalcommits.org) - `feat:`, `fix:`, `chore:`, `style:`, `refactor:`, `test:`, `docs:`
- Subject line only, ideally under 50 characters
- Human, direct tone. Avoid formal or robotic voice ("this commit introduces…", "this change implements…")
- **Never** add `Co-Authored-By` or any mention of Claude / AI / agents
- **Never** add a description or body - subject only
- Commit incrementally when a logical chunk lands - don't batch everything at the end

### Pull Requests

- Always draft (`gh pr create --draft`)
- Title in English, same shape as a commit subject: Conventional Commits format (`feat:`, `fix:`, `refactor:`…), ideally under 50 characters. Repos squash-merge, so the title becomes the commit on the default branch
  - ✅ `refactor: reorganize agent context into .agents/`
  - ❌ `Reorganize agent context into .agents/ and update Laravel/Boost`
- Base branch: the repo's default
- If the repo has a `.github/PULL_REQUEST_TEMPLATE.md`, fill it in as the PR body and check off the items that apply. `gh pr create` ignores the template unless you pass it yourself - write the filled body to a file and use `--body-file`
- **Never** reference Claude / AI / agents in title, body, branch name, or comments
- The description states only what the PR does in its final form. Leave out the history, the decisions behind it, planned follow-ups, and any term that needs outside context. A stranger reading it should get what it does, nothing else
- When new commits change what the PR does, update the title and description to match
- Add a brief **How to test** note when the steps are easy to state; if they aren't clear, ask instead of guessing
- When a PR's base branch is another (unmerged) PR's branch, register the chain as a native GitHub stack: `gh stack link <bottom-pr> ... <top-pr>` (bottom to top; include any already-stacked ancestor PRs or the command refuses to update)

### Pre-push checks

Standard order before pushing:

1. Formatter → 2. Linter → 3. Static analysis → 4. Tests

Exact commands are repo-defined (see the repo's `CLAUDE.md` / `AGENTS.md` / `composer.json` / `package.json`). If the formatter modifies files, commit those changes before pushing so CI doesn't kick back automated `style:` cleanup commits.

For long tasks, run only affected checks during the work and defer the full suite to the end. For short tasks, skip intermediate runs.

### Security

- Never commit `.env`, credentials, tokens, or any secret - check diffs for hardcoded values, not just filenames
- Never read or commit protected files: `.env*`, `auth.json`, `AuthKey_*.p8`, `*.pem`, `*.key`, service-account `*.json` credentials
- Flag any new dependency as suspect before adding it (typosquatting, unknown maintainer)
- No destructive operations (`force-push`, branch deletion, history rewrite, `git reset --hard`, `rm -rf`) without explicit confirmation from the dev
- For external tools (CI, pastebins, gists): consider whether uploaded content could be sensitive - it may be cached or indexed even if later deleted

### Code style

- Follow existing patterns before introducing new ones; reuse existing components/helpers before writing new ones
- Comments and identifiers in **English**
- No comments unless explaining a non-obvious **why** (a hidden constraint, a subtle invariant, a workaround for a specific bug). A well-named identifier beats a comment explaining the "what"
- **Don't document changes in comments.** A comment describes the code as it is, not how it got there:
  - ❌ `// moved from backend to frontend`
  - ❌ `// renamed from foo to bar`
  - ❌ `// replaces the previous logic that used X`
  - ❌ `// added for issue #123`
  - ✅ omit it - git log / PR tells the story
- Don't reference callers or temporal context: no `// used by X`, `// for the Y flow`, `// added in sprint Z`
- Never hand-edit generated files (types, route helpers, lockfiles) - rerun the generator instead

### Writing

Applies to everything written - code, comments, commits, PRs, docs, UI copy.

- Use a plain hyphen `-`, never em or en dashes (`—`, `–`). They read as AI-generated.
- Prefer straight quotes (`"` `'`) over curly ones, and regular spaces over non-breaking spaces.

### Docs & rules

Write docs, READMEs, and rule files as the **final state**, for a reader with no prior context. Keep them brief - don't over-explain.

- Don't narrate history - what something replaces, used to be, or was added for. Git history and PRs carry that story
- Don't situate a file among its siblings ("pairs with `x.md`", "like `y.md`"). Files move; each doc stands alone
- Reference another file only when the reader must **act on it**, and by what it contains

### Reply style

- Concise. Skip the obvious
- End-of-turn summary: one or two sentences - what changed, what's next
- No long essays, no unnecessary disclaimers, no "sure, happy to help…"

### Overrides

- Personal override (gitignored): `CLAUDE.local.md` for Claude Code; `AGENTS.override.md` for Codex (replaces `AGENTS.md` for that machine - start it by copying `AGENTS.md`). Cursor personal rules live in its User Rules setting, not in a file
- Per-repo override: add rules below the imports in `CLAUDE.md` and below the managed block in `AGENTS.md`
- Permanent override: PR to the aikit repo

---

## PHP

Code-writing rules for PHP files. Apply when writing or editing PHP code. In Laravel apps these rules layer on top of whatever `laravel/boost` provides.

### Types

- Declare `strict_types=1` at the top of every PHP file.
- Add explicit return type declarations to every method.
- Add type hints to every parameter.
- Use union and intersection types when they fit. Avoid `mixed` unless genuinely unbounded.
- Use `readonly` properties when the value doesn't change after construction.

### Syntax

- Use PHP 8 constructor property promotion. Don't leave empty `__construct()` methods.
- Use curly braces for every control structure, even single-line bodies.
- Use `match` instead of `switch` when assigning a value or returning early.
- Use first-class callable syntax (`Foo::bar(...)`) over closures wrapping a single call.
- Use named arguments when a call has multiple booleans or optional params and clarity beats brevity.

### Enums

- Use enum classes for fixed sets of values; don't use string constants on a class.
- TitleCase for enum case keys: `FavoritePerson`, `Monthly`.

### Comments and PHPDoc

- Prefer PHPDoc blocks over inline comments. Inline comments only for non-obvious WHY.
- Use array shape definitions in PHPDoc for structured arrays:

```php
/** @return array{name: string, age: int} */
```

### PHP 8.4+ array helpers

Prefer the built-in helpers over manual loops:

- `array_find` over `foreach` + early return
- `array_find_key` over manual key lookup
- `array_any` / `array_all` over `foreach` + boolean accumulator

### Composer scripts

Prefer the repo's `composer.json` scripts (`composer format`, `composer analyse`, `composer test`) over invoking the underlying tool directly. If a script is missing, add it.

### Avoid

- Mutating function arguments - take immutable inputs, return new values.
- Static state (singletons, statics for caching). Prefer dependency injection.

---

## Pest

Testing rules for repos using Pest. Apply when writing or editing tests.

- Write tests as Pest `it()` blocks: `it('rejects expired tokens', function (): void { ... });`. No PHPUnit test classes, no `test()`.
- Convert class-based stubs (e.g. from `php artisan make:test`) to `it()` before filling them in.
- Every bug fix ships with a regression test that fails without the fix.
- While working, run only the affected tests (`--filter=...`); run the full suite once before push, or when asked.
- Test names and code in English.

<!-- aikit:end -->

<!-- aikit:project:begin - synced from .agents/*.md - edit those files and run aikit update -->

## Project

<!-- Fill: 2-3 lines - what this project is, who it's for, how the repo is laid out. -->

### When to read what

<!-- Fill: action-anchored pointers to on-demand docs, e.g.
- Before writing tests, read `.agents/guides/testing.md`.
- Full stack and versions: `.agents/stack.md`.
A rule belongs in the always-on files only if it changes behavior in most
tasks; task-specific guidance goes in a guide, consultable facts in a
reference file.
If a subproject has its own AGENTS.md (e.g. Laravel Boost under api/), add:
"if your harness hasn't loaded api/AGENTS.md, read it before touching api/". -->

---

## Commands

<!-- Fill: the daily commands an agent can't guess - dev server, running a
single test, lint, format, codegen. One line each. Point to deeper docs
instead of duplicating them. -->

---

## Conventions

<!-- Fill: hard project rules that apply to most tasks - invariants,
must-never / must-always. If a linter or CI can enforce a rule, configure the
tool instead of writing prose here. -->

<!-- aikit:project:end -->

<!-- Project-specific instructions go below. -->
