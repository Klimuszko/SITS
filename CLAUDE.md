@.claude/skills/token-optimization.md

# SerwisIT — Project Router

Lean routing file. Knowledge lives in the files referenced below, not here. Keep under ~50 lines.

## Project

SerwisIT — self-hosted IT portal (helpdesk + CMDB + knowledge base + admin-work reporting)
for an IT company serving multiple clients. Stack: Laravel 12 + Livewire 3 + PostgreSQL 16
+ Redis 7 + Docker Compose. UI in Polish, i18n-ready.

**Canonical source of truth: [README.md](README.md)** — architecture, container layout, roles,
module map/status, design decisions, commands, deploy. Read it before any non-trivial change.
Do not duplicate README content here.

## Three Man Team

One Claude Code session; three roles spun up as subagents when needed:
- **Arch** (Architect) — [ARCHITECT.md](ARCHITECT.md) — talks to Owner, writes briefs, owns deploy.
- **Bob** (Builder) — [BUILDER.md](BUILDER.md) — builds exactly what the brief says, nothing more.
- **Richard** (Reviewer) — [REVIEWER.md](REVIEWER.md) — reviews spec/security/logic before deploy.

Handoff state in `handoff/`: SESSION-CHECKPOINT, BUILD-LOG, ARCHITECT-BRIEF, REVIEW-REQUEST,
REVIEW-FEEDBACK. Version/install info in [manifest.md](manifest.md).

Session start: *"You are the Architect on this project. Read README.md, then ARCHITECT.md."*

## Workflow rules

- One step at a time. Step N+1 starts only after Step N is deployed and logged in `handoff/BUILD-LOG.md`.
- Nothing ships to production without Arch's sign-off **and** the Project Owner's go-ahead.
- Out-of-scope items → `handoff/BUILD-LOG.md` Known Gaps. Do not expand the current step.

## Token rules (see token-optimization.md)

- Grep before Read — never read a whole file to find one thing.
- Don't re-read files already in context; skip speculative reads.
- Parallelize independent tool calls.
- Role-scoped loading: each role loads only its handoff files + what the brief names.

## Project conventions (detail in README.md)

- Two-level roles: `users.role` (global) + `organization_memberships.role` (per-org, authoritative).
- Security: per-org separation in `app/Policies`; attachments only via `AttachmentController`;
  KB HTML sanitized via `App\Services\HtmlSanitizer` (rich HTML, scripts/handlers stripped);
  SVG via `App\Support\SvgSanitizer`. Secrets only via `.env`.
- No Node/Vite; styles in `public/css/app.css`. Soft deletes over hard deletes.
- Ticket statuses = PHP enum (`App\Enums\TicketStatus`); priorities/categories = admin-managed tables.
