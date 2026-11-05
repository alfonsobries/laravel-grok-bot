<!-- aikit v0.14.0 - managed file, do not edit -->
<!-- Local override: CLAUDE.local.md (Claude Code) / AGENTS.override.md (Codex). Per-repo override: below the imports in CLAUDE.md / below the managed block in AGENTS.md. Permanent override: PR to https://github.com/alfonsobries/aikit -->

# Baseline

Shared rules for every repo. Apply to any stack.

## Commits

- Format: [Conventional Commits](https://www.conventionalcommits.org) - `feat:`, `fix:`, `chore:`, `style:`, `refactor:`, `test:`, `docs:`
- Subject line only, ideally under 50 characters
- Human, direct tone. Avoid formal or robotic voice ("this commit introduces…", "this change implements…")
- **Never** add `Co-Authored-By` or any mention of Claude / AI / agents
- **Never** add a description or body - subject only
- Commit incrementally when a logical chunk lands - don't batch everything at the end

## Pull Requests

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

## Pre-push checks

Standard order before pushing:

1. Formatter → 2. Linter → 3. Static analysis → 4. Tests

Exact commands are repo-defined (see the repo's `CLAUDE.md` / `AGENTS.md` / `composer.json` / `package.json`). If the formatter modifies files, commit those changes before pushing so CI doesn't kick back automated `style:` cleanup commits.

For long tasks, run only affected checks during the work and defer the full suite to the end. For short tasks, skip intermediate runs.

## Security

- Never commit `.env`, credentials, tokens, or any secret - check diffs for hardcoded values, not just filenames
- Never read or commit protected files: `.env*`, `auth.json`, `AuthKey_*.p8`, `*.pem`, `*.key`, service-account `*.json` credentials
- Flag any new dependency as suspect before adding it (typosquatting, unknown maintainer)
- No destructive operations (`force-push`, branch deletion, history rewrite, `git reset --hard`, `rm -rf`) without explicit confirmation from the dev
- For external tools (CI, pastebins, gists): consider whether uploaded content could be sensitive - it may be cached or indexed even if later deleted

## Code style

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

## Writing

Applies to everything written - code, comments, commits, PRs, docs, UI copy.

- Use a plain hyphen `-`, never em or en dashes (`—`, `–`). They read as AI-generated.
- Prefer straight quotes (`"` `'`) over curly ones, and regular spaces over non-breaking spaces.

## Docs & rules

Write docs, READMEs, and rule files as the **final state**, for a reader with no prior context. Keep them brief - don't over-explain.

- Don't narrate history - what something replaces, used to be, or was added for. Git history and PRs carry that story
- Don't situate a file among its siblings ("pairs with `x.md`", "like `y.md`"). Files move; each doc stands alone
- Reference another file only when the reader must **act on it**, and by what it contains

## Reply style

- Concise. Skip the obvious
- End-of-turn summary: one or two sentences - what changed, what's next
- No long essays, no unnecessary disclaimers, no "sure, happy to help…"

## Overrides

- Personal override (gitignored): `CLAUDE.local.md` for Claude Code; `AGENTS.override.md` for Codex (replaces `AGENTS.md` for that machine - start it by copying `AGENTS.md`). Cursor personal rules live in its User Rules setting, not in a file
- Per-repo override: add rules below the imports in `CLAUDE.md` and below the managed block in `AGENTS.md`
- Permanent override: PR to the aikit repo
