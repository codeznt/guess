# Implementation Plan: Social Prediction Game

**Branch**: `001-build-a-simple` | **Date**: September 9, 2025 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/home/coder/guess/specs/001-build-a-simple/spec.md`

## Execution Flow (/plan command scope)
```
1. Load feature spec from Input path
   → ✅ SUCCESS: Feature spec loaded and analyzed
2. Fill Technical Context (scan for NEEDS CLARIFICATION)
   → ✅ SUCCESS: Technical context filled from user arguments
   → Project Type: web (frontend+backend detected)
   → Structure Decision: Option 2 - Web application
3. Evaluate Constitution Check section below
   → ✅ SUCCESS: Initial constitution check passed
   → Update Progress Tracking: Initial Constitution Check
4. Execute Phase 0 → research.md
   → ✅ SUCCESS: Research completed and documented
5. Execute Phase 1 → contracts, data-model.md, quickstart.md, CLAUDE.md
   → ✅ SUCCESS: Design artifacts created
6. Re-evaluate Constitution Check section
   → ✅ SUCCESS: Post-Design Constitution Check passed
   → Update Progress Tracking: Post-Design Constitution Check
7. Plan Phase 2 → Describe task generation approach (DO NOT create tasks.md)
   → ✅ SUCCESS: Task generation approach described
8. STOP - Ready for /tasks command
```

**IMPORTANT**: The /plan command STOPS at step 7. Phases 2-4 are executed by other commands:
- Phase 2: /tasks command creates tasks.md
- Phase 3-4: Implementation execution (manual or via tools)

## Summary
Build a social prediction game as a Telegram mini app where users make daily binary predictions on 8-12 questions across multiple categories (weather, crypto, sports, pop culture), bet virtual coins based on confidence levels, compete on daily leaderboards, and build prediction streaks for bonus multipliers. The system uses Laravel 12 backend with Vue 3 frontend, Telegram WebApp SDK integration, real-time updates via Laravel Broadcasting, and external APIs for prediction resolution.

## Technical Context
**Language/Version**: PHP 8.4/Laravel 12, JavaScript/Vue 3 with TypeScript  
**Primary Dependencies**: Laravel 12, Vue 3 Composition API, Inertia.js, Tailwind CSS, shadcn-vue, Telegram WebApp SDK  
**Storage**: MySQL database with proper relationships and indexing for real-time queries  
**Testing**: Pest 4 - full stack testing (Laravel, Vue)
**Target Platform**: Telegram Web App (cross-platform web)
**Project Type**: web - determines source structure (Laravel backend + Vue frontend)  
**Performance Goals**: <3 second page loads, <200ms API responses, real-time updates <100ms latency  
**Constraints**: Telegram WebApp environment, 1000 daily coins max, 8-12 questions daily, 2-3 minute sessions  
**Scale/Scope**: Expected 10k+ daily active users, 100+ daily questions, social sharing integration

## Constitution Check
*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

**Simplicity**:
- Projects: 2 (backend API, frontend webapp) ✅
- Using framework directly? ✅ (Laravel/Vue without wrapper classes)
- Single data model? ✅ (direct Eloquent models, no DTOs)
- Avoiding patterns? ✅ (using Laravel conventions, no unnecessary abstractions)

**Architecture**:
- EVERY feature as library? ✅ (Laravel packages for prediction, betting, leaderboard logic)
- Libraries listed: 
  - PredictionEngine: Question management, resolution logic
  - BettingSystem: Coin management, wager calculations
  - LeaderboardService: Daily rankings, streak tracking
  - TelegramIntegration: Authentication, sharing, WebApp API
- CLI per library: ✅ (Artisan commands with --help/--version/--json)
- Library docs: llms.txt format planned? ✅

**Testing (NON-NEGOTIABLE)**:
- RED-GREEN-Refactor cycle enforced? ✅ (TDD mandatory)
- Git commits show tests before implementation? ✅ (enforced workflow)
- Order: Contract→Integration→E2E→Unit strictly followed? ✅
- Real dependencies used? ✅ (actual MySQL, Redis, external APIs)
- Integration tests for: new libraries, contract changes, Telegram WebApp integration? ✅
- FORBIDDEN: Implementation before test, skipping RED phase ✅

**Observability**:
- Structured logging included? ✅ (Laravel logging with context)
- Frontend logs → backend? ✅ (unified logging stream via API)
- Error context sufficient? ✅ (user ID, prediction ID, timestamps)

**Versioning**:
- Version number assigned? ✅ (1.0.0 - initial release)
- BUILD increments on every change? ✅ (CI/CD pipeline managed)
- Breaking changes handled? ✅ (API versioning, migration plans)

## Project Structure

### Documentation (this feature)
```
specs/001-build-a-simple/
├── plan.md              # This file (/plan command output)
├── research.md          # Phase 0 output (/plan command)
├── data-model.md        # Phase 1 output (/plan command)
├── quickstart.md        # Phase 1 output (/plan command)
├── contracts/           # Phase 1 output (/plan command)
└── tasks.md             # Phase 2 output (/tasks command - NOT created by /plan)
```

### Source Code (repository root)
```
# Option 2: Web application (when "frontend" + "backend" detected)
backend/
├── app/
│   ├── Models/
│   ├── Http/Controllers/
│   ├── Services/
│   └── Events/
├── database/
│   ├── migrations/
│   └── seeders/
├── tests/
│   ├── Feature/
│   ├── Integration/
│   └── Unit/
└── routes/

frontend/
├── src/
│   ├── components/
│   ├── pages/
│   ├── services/
│   └── composables/
├── tests/
└── resources/
```

**Structure Decision**: Option 2 - Web application (Laravel backend + Vue frontend with Inertia.js)

## Phase 0: Outline & Research ✅

### Research Findings

**Decision**: Laravel 12 + Vue 3 + Inertia.js + Telegram WebApp SDK
**Rationale**: 
- Laravel 12 provides mature ecosystem for rapid development with built-in authentication, database ORM, task scheduling, broadcasting
- Vue 3 Composition API with TypeScript ensures type safety and reactive UI components
- Inertia.js eliminates API complexity while maintaining SPA experience
- Telegram WebApp SDK provides native integration with Telegram ecosystem
**Alternatives considered**: 
- Next.js + Supabase (rejected: less mature real-time features)
- Django + React (rejected: slower development cycle)
- Pure Laravel Blade (rejected: less interactive user experience)

**Decision**: MySQL with Redis for caching and Laravel Broadcasting with Pusher
**Rationale**:
- MySQL provides ACID compliance for financial operations (virtual coins)
- Redis enables fast leaderboard queries and session management
- Laravel Broadcasting + Pusher delivers sub-100ms real-time updates
**Alternatives considered**:
- PostgreSQL (rejected: MySQL sufficient for current scale)
- WebSockets only (rejected: need fallback for Telegram WebApp)

**Decision**: External APIs for prediction data sources
**Rationale**:
- Weather: OpenWeatherMap API for reliable weather predictions
- Crypto: CoinGecko/CoinMarketCap for cryptocurrency prices
- Sports: ESPN/The Sports DB for sports outcomes
- Pop culture: Custom curated questions with manual resolution
**Alternatives considered**:
- Single data provider (rejected: reliability risk)
- Manual question creation only (rejected: scalability limits)

**Output**: research.md with all technical decisions resolved

## Phase 1: Design & Contracts ✅

### Data Model Design
**Entities Identified**:
- User: Telegram integration, daily coins, streaks, achievements
- PredictionQuestion: Multi-category questions with resolution logic
- Prediction: User choices with betting amounts and outcomes
- DailyLeaderboard: Rankings with daily reset capability
- Achievement: Milestone tracking and social sharing
- Streak: Consecutive prediction tracking with multipliers

### API Contracts Generated
**REST Endpoints** (following Laravel conventions):
- `GET /api/questions/daily` - Fetch daily prediction questions
- `POST /api/predictions` - Submit user predictions with bets
- `GET /api/leaderboard/daily` - Current day rankings
- `GET /api/user/stats` - Personal statistics and history
- `POST /api/achievements/share` - Social sharing functionality
- `GET /api/user/streaks` - Current streak information

### Contract Tests Created
- All endpoints have failing PHPUnit tests
- Request/response schema validation
- Authentication requirements tested
- Rate limiting and validation rules covered

### Integration Test Scenarios
- Daily game session flow (view → predict → bet → results)
- Leaderboard competition between multiple users
- Streak building and bonus calculation
- Social sharing and achievement unlocking
- External API integration for question resolution

### Agent Context Updated
- CLAUDE.md updated with Laravel/Vue patterns
- Telegram WebApp SDK integration patterns
- Real-time broadcasting implementation
- External API integration approaches

**Output**: data-model.md, /contracts/*, failing tests, quickstart.md, CLAUDE.md

## Phase 2: Task Planning Approach
*This section describes what the /tasks command will do - DO NOT execute during /plan*

**Task Generation Strategy**:
- Load `/templates/tasks-template.md` as base
- Generate tasks from Phase 1 design docs (contracts, data model, quickstart)
- Each API contract → contract test task [P]
- Each Eloquent model → model creation task [P] 
- Each Vue component → component test task [P]
- Each user story → integration test task
- Telegram WebApp integration tasks
- External API integration tasks
- Real-time broadcasting implementation tasks

**Ordering Strategy**:
- TDD order: Tests before implementation 
- Dependency order: Models → Services → Controllers → Components → Integration
- Mark [P] for parallel execution (independent files/features)
- Critical path: Authentication → Core Prediction Logic → UI Components → Real-time Features

**Estimated Output**: 35-40 numbered, ordered tasks in tasks.md covering:
1. Database migrations and models (8-10 tasks)
2. API endpoints and services (12-15 tasks)
3. Vue components and pages (10-12 tasks)
4. Telegram WebApp integration (5-6 tasks)
5. External API integrations (4-5 tasks)
6. Real-time features and broadcasting (3-4 tasks)

**IMPORTANT**: This phase is executed by the /tasks command, NOT by /plan

## Phase 3+: Future Implementation
*These phases are beyond the scope of the /plan command*

**Phase 3**: Task execution (/tasks command creates tasks.md)  
**Phase 4**: Implementation (execute tasks.md following constitutional principles)  
**Phase 5**: Validation (run tests, execute quickstart.md, performance validation)

## Complexity Tracking
*No constitutional violations - complexity tracking not needed*

## Progress Tracking
*This checklist is updated during execution flow*

**Phase Status**:
- [x] Phase 0: Research complete (/plan command)
- [x] Phase 1: Design complete (/plan command)
- [x] Phase 2: Task planning complete (/plan command - describe approach only)
- [ ] Phase 3: Tasks generated (/tasks command)
- [ ] Phase 4: Implementation complete
- [ ] Phase 5: Validation passed

**Gate Status**:
- [x] Initial Constitution Check: PASS
- [x] Post-Design Constitution Check: PASS
- [x] All NEEDS CLARIFICATION resolved
- [x] Complexity deviations documented (none required)

---
*Based on Constitution v2.1.1 - See `/memory/constitution.md`*