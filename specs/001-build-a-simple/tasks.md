# Tasks: Social Prediction Game - MVP

**Input**: Design documents from `/home/coder/guess/specs/001-build-a-simple/`
**Prerequisites**: plan.md, research.md, data-model.md, contracts/, quickstart.md
**MVP Context**: Build social prediction game features using existing Laravel 12 + Vue.js + Inertia.js starter kit
**ASSUMPTION**: Laravel 12 + Vue.js + Inertia.js starter kit is already installed and configured
**LOCAL DEVELOPMENT**: SQLite database, mocked Telegram WebApp SDK for testing

## Execution Flow (main)
```
1. Load plan.md from feature directory
   → ✅ SUCCESS: Laravel 12 + Vue 3 + Inertia.js + Telegram WebApp SDK
   → GIVEN: Starter kit already installed and configured
2. Load optional design documents:
   → ✅ data-model.md: 6 core entities (User, PredictionQuestion, Prediction, DailyLeaderboard, Achievement, Category)
   → ✅ contracts/: 6 endpoints converted to Inertia web routes
   → ✅ research.md: Pest 4 testing, MySQL + Redis, external APIs
3. Generate tasks by category:
   → ✅ Database: Migrations for game entities
   → ✅ Tests: Feature tests for Inertia responses, integration tests
   → ✅ Core: 6 models, services, Inertia controllers
   → ✅ Frontend: Vue pages and components for game UI
   → ✅ Integration: Telegram WebApp, game mechanics
4. Apply task rules:
   → ✅ Different files marked [P] for parallel execution
   → ✅ Tests before implementation (TDD enforced)
5. Number tasks sequentially (T001-T037)
6. Generate dependency graph focused on game features
7. Create parallel execution examples
8. Validate task completeness:
   → ✅ All 6 routes have feature tests
   → ✅ All 6 entities have model tasks
   → ✅ All endpoints return Inertia responses
   → ✅ Vue pages/components for complete game UI
9. Return: SUCCESS (37 tasks for game feature implementation)
```

## Format: `[ID] [P?] Description`
- **[P]**: Can run in parallel (different files, no dependencies)
- All paths follow Laravel 12 + Inertia.js conventions (existing starter kit)

## Path Conventions
**Laravel 12 + Inertia.js Structure (Already Installed)**:
- **Models**: `app/Models/`
- **Controllers**: `app/Http/Controllers/` (return Inertia responses)
- **Services**: `app/Services/`
- **Tests**: `tests/Feature/`, `tests/Unit/`, `tests/Integration/`
- **Migrations**: `database/migrations/`
- **Vue Pages**: `resources/js/pages/` (Inertia pages)
- **Vue Components**: `resources/js/components/`
- **Vue Layouts**: `resources/js/layouts/`
- **Vue Composables**: `resources/js/composables/`
- **Routes**: `routes/web.php`

## Phase 1: Database & Migrations ⚠️ MUST COMPLETE BEFORE MODELS

- [ ] **T001** [P] Add Telegram fields to existing users table in `database/migrations/add_telegram_fields_to_users_table.php` (SQLite compatible)
- [ ] **T002** [P] Create categories migration in `database/migrations/create_categories_table.php`  
- [ ] **T003** [P] Create prediction_questions migration in `database/migrations/create_prediction_questions_table.php`
- [ ] **T004** [P] Create predictions migration with betting logic in `database/migrations/create_predictions_table.php`
- [ ] **T005** [P] Create daily_leaderboards migration in `database/migrations/create_daily_leaderboards_table.php`
- [ ] **T006** [P] Create achievements migration in `database/migrations/create_achievements_table.php`

## Phase 2: Feature Tests (TDD) ⚠️ MUST COMPLETE BEFORE IMPLEMENTATION

**CRITICAL: These tests MUST be written and MUST FAIL before ANY controller implementation**

- [ ] **T007** [P] Feature test GET /questions/daily in `tests/Feature/Http/Controllers/QuestionsControllerTest.php` (returns Inertia)
- [ ] **T008** [P] Feature test POST /predictions in `tests/Feature/Http/Controllers/PredictionsControllerTest.php` (Inertia redirect)
- [ ] **T009** [P] Feature test GET /leaderboard in `tests/Feature/Http/Controllers/LeaderboardControllerTest.php` (returns Inertia)
- [ ] **T010** [P] Feature test GET /profile in `tests/Feature/Http/Controllers/ProfileControllerTest.php` (returns Inertia)
- [ ] **T011** [P] Feature test POST /achievements/share in `tests/Feature/Http/Controllers/AchievementsControllerTest.php` (Inertia redirect)
- [ ] **T012** [P] Feature test GET /dashboard in `tests/Feature/Http/Controllers/DashboardControllerTest.php` (returns Inertia)

## Phase 3: Integration Tests ⚠️ MUST COMPLETE BEFORE IMPLEMENTATION

- [ ] **T013** [P] Integration test daily game flow in `tests/Integration/DailyGameFlowTest.php`
- [ ] **T014** [P] Integration test betting system in `tests/Integration/BettingSystemTest.php`
- [ ] **T015** [P] Integration test streak calculation in `tests/Integration/StreakCalculationTest.php`
- [ ] **T016** [P] Integration test leaderboard ranking in `tests/Integration/LeaderboardRankingTest.php`
- [ ] **T017** [P] Integration test Telegram WebApp authentication in `tests/Integration/TelegramAuthenticationTest.php`

## Phase 4: Eloquent Models (ONLY after tests are failing)

- [ ] **T018** [P] User model with Telegram auth in `app/Models/User.php` with relationships and validation
- [ ] **T019** [P] Category model in `app/Models/Category.php` with relationships  
- [ ] **T020** [P] PredictionQuestion model in `app/Models/PredictionQuestion.php` with scopes and relationships
- [ ] **T021** [P] Prediction model in `app/Models/Prediction.php` with betting validation and relationships
- [ ] **T022** [P] DailyLeaderboard model in `app/Models/DailyLeaderboard.php` with ranking logic
- [ ] **T023** [P] Achievement model in `app/Models/Achievement.php` with sharing functionality

## Phase 5: Service Layer Implementation

- [ ] **T024** [P] PredictionService in `app/Services/PredictionService.php` for question management and resolution
- [ ] **T025** [P] BettingService in `app/Services/BettingService.php` for coin management and winnings calculation
- [ ] **T026** [P] LeaderboardService in `app/Services/LeaderboardService.php` for daily ranking calculations
- [ ] **T027** [P] StreakService in `app/Services/StreakService.php` for streak tracking and multipliers
- [ ] **T028** [P] AchievementService in `app/Services/AchievementService.php` for milestone detection and rewards
- [ ] **T029** [P] TelegramService in `app/Services/TelegramService.php` for WebApp authentication and sharing

## Phase 6: Inertia Controllers & Web Routes

- [ ] **T030** QuestionsController GET /questions/daily in `app/Http/Controllers/QuestionsController.php` (returns Inertia)
- [ ] **T031** PredictionsController POST /predictions in `app/Http/Controllers/PredictionsController.php` (Inertia redirect) 
- [ ] **T032** LeaderboardController GET /leaderboard in `app/Http/Controllers/LeaderboardController.php` (returns Inertia)
- [ ] **T033** ProfileController GET /profile in `app/Http/Controllers/ProfileController.php` (returns Inertia)
- [ ] **T034** AchievementsController POST /achievements/share in `app/Http/Controllers/AchievementsController.php` (Inertia redirect)
- [ ] **T035** DashboardController GET /dashboard in `app/Http/Controllers/DashboardController.php` (returns Inertia)
- [ ] **T036** Configure web routes in `routes/web.php` with proper middleware and authentication

## Phase 7: Local Development Setup & Vue Pages

- [ ] **T037** [P] Setup Telegram WebApp SDK mocking in `resources/js/lib/telegram-mock.ts` for local development
- [ ] **T038** [P] Dashboard page in `resources/js/pages/Dashboard.vue` with daily questions display
- [ ] **T039** [P] Questions page in `resources/js/pages/Questions/Daily.vue` for prediction interface
- [ ] **T040** [P] Leaderboard page in `resources/js/pages/Leaderboard/Daily.vue` with rankings
- [ ] **T041** [P] Profile page in `resources/js/pages/Profile/Stats.vue` with user statistics
- [ ] **T042** [P] PredictionCard component in `resources/js/components/PredictionCard.vue`
- [ ] **T043** [P] BettingSlider component in `resources/js/components/BettingSlider.vue`
- [ ] **T044** [P] LeaderboardTable component in `resources/js/components/LeaderboardTable.vue`
- [ ] **T045** [P] StreakDisplay component in `resources/js/components/StreakDisplay.vue`

## Dependencies

**Phase Order (Strict)**:
1. Database (T001-T006) → Tests (T007-T017) → Models (T018-T023) → Services (T024-T029) → Controllers (T030-T036) → Vue UI (T037-T044)

**Critical Dependencies**:
- Database migrations (T001-T006) before models (T018-T023)
- ALL tests (T007-T017) before ANY implementation (T018-T044)
- Models (T018-T023) before services (T024-T029)
- Services (T024-T029) before controllers (T030-T035)
- Controllers (T030-T035) before routes (T036)
- Routes (T036) before Vue pages (T037-T044)

**Blocking Relationships**:
- T007-T012 (feature tests) MUST fail before T030-T035 (controllers)
- T013-T017 (integration tests) MUST fail before T024-T029 (services)
- T024 (PredictionService) blocks T030 (QuestionsController)
- T025 (BettingService) blocks T031 (PredictionsController)
- T026 (LeaderboardService) blocks T032 (LeaderboardController)
- T036 (routes) blocks T037-T044 (Vue pages/components)

## Parallel Execution Examples

### Phase 1 - Database Migrations (All Independent)
```bash
# Launch T001-T006 together:
Task: "Add Telegram fields to existing users table in database/migrations/add_telegram_fields_to_users_table.php"
Task: "Create categories migration in database/migrations/create_categories_table.php"
Task: "Create prediction_questions migration in database/migrations/create_prediction_questions_table.php"
Task: "Create predictions migration with betting logic in database/migrations/create_predictions_table.php"
Task: "Create daily_leaderboards migration in database/migrations/create_daily_leaderboards_table.php"
Task: "Create achievements migration in database/migrations/create_achievements_table.php"
```

### Phase 2 - Feature Tests (All Independent - MUST FAIL)
```bash
# Launch T007-T012 together:
Task: "Feature test GET /questions/daily returns Inertia response in tests/Feature/Http/Controllers/QuestionsControllerTest.php"
Task: "Feature test POST /predictions Inertia redirect in tests/Feature/Http/Controllers/PredictionsControllerTest.php"
Task: "Feature test GET /leaderboard returns Inertia response in tests/Feature/Http/Controllers/LeaderboardControllerTest.php"
Task: "Feature test GET /profile returns Inertia response in tests/Feature/Http/Controllers/ProfileControllerTest.php"
Task: "Feature test POST /achievements/share Inertia redirect in tests/Feature/Http/Controllers/AchievementsControllerTest.php"
Task: "Feature test GET /dashboard returns Inertia response in tests/Feature/Http/Controllers/DashboardControllerTest.php"
```

### Phase 3 - Integration Tests (All Independent - MUST FAIL)
```bash
# Launch T013-T017 together:
Task: "Integration test daily game flow in tests/Integration/DailyGameFlowTest.php"
Task: "Integration test betting system in tests/Integration/BettingSystemTest.php"
Task: "Integration test streak calculation in tests/Integration/StreakCalculationTest.php"
Task: "Integration test leaderboard ranking in tests/Integration/LeaderboardRankingTest.php"
Task: "Integration test Telegram WebApp authentication in tests/Integration/TelegramAuthenticationTest.php"
```

### Phase 4 - Eloquent Models (All Independent)
```bash
# Launch T018-T023 together:
Task: "User model with Telegram auth in app/Models/User.php"
Task: "Category model in app/Models/Category.php"
Task: "PredictionQuestion model in app/Models/PredictionQuestion.php"
Task: "Prediction model in app/Models/Prediction.php"
Task: "DailyLeaderboard model in app/Models/DailyLeaderboard.php"
Task: "Achievement model in app/Models/Achievement.php"
```

### Phase 5 - Service Layer (All Independent)
```bash
# Launch T024-T029 together:
Task: "PredictionService in app/Services/PredictionService.php"
Task: "BettingService in app/Services/BettingService.php"
Task: "LeaderboardService in app/Services/LeaderboardService.php"
Task: "StreakService in app/Services/StreakService.php"
Task: "AchievementService in app/Services/AchievementService.php"
Task: "TelegramService in app/Services/TelegramService.php"
```

### Phase 7 - Vue Pages & Components (All Independent)
```bash
# Launch T037-T044 together:
Task: "Dashboard page in resources/js/pages/Dashboard.vue"
Task: "Questions page in resources/js/pages/Questions/Daily.vue"
Task: "Leaderboard page in resources/js/pages/Leaderboard/Daily.vue"
Task: "Profile page in resources/js/pages/Profile/Stats.vue"
Task: "PredictionCard component in resources/js/components/PredictionCard.vue"
Task: "BettingSlider component in resources/js/components/BettingSlider.vue"
Task: "LeaderboardTable component in resources/js/components/LeaderboardTable.vue"
Task: "StreakDisplay component in resources/js/components/StreakDisplay.vue"
```

## MVP Scope

**Included in MVP**:
- Core prediction and betting mechanics with Inertia.js
- Daily questions and leaderboards with Vue.js UI
- Telegram WebApp authentication integration
- Streak tracking and achievements with Vue components
- Complete full-stack implementation

**Excluded from MVP** (Future phases):
- Advanced real-time broadcasting features
- External API integrations (manual questions only)
- Social sharing with external platforms
- Performance optimizations and caching layers
- Advanced achievement types and notifications

## Inertia.js Implementation Notes

**Controller Pattern**:
```php
return Inertia::render('Questions/Daily', [
    'questions' => $questions,
    'userCoins' => auth()->user()->daily_coins,
]);
```

**Vue Page Pattern**:
```javascript
defineProps({
    questions: Array,
    userCoins: Number,
});
```

**Feature Test Pattern**:
```php
$response->assertInertia(fn (Assert $page) => 
    $page->component('Questions/Daily')->has('questions', 10)
);
```

## Task Generation Rules Applied

1. **From Data Model**: 6 entities → 6 model creation tasks [P]
2. **From Contracts**: 6 endpoints → 6 Inertia feature test tasks [P]
3. **From User Stories**: 5 integration scenarios → 5 integration test tasks [P]
4. **From UI Needs**: 4 pages + 4 components → 8 Vue tasks [P]
5. **Ordering**: Database → Tests → Models → Services → Controllers → Routes → Vue
6. **TDD Enforcement**: ALL tests before ANY implementation

## Validation Checklist ✅

- [x] All 6 routes have corresponding feature tests (T007-T012)
- [x] All 6 entities have model tasks (T018-T023)
- [x] All tests come before implementation (Phase 2-3 before 4-7)
- [x] Parallel tasks truly independent (different files)
- [x] Each task specifies exact file path
- [x] No task modifies same file as another [P] task
- [x] MVP scope includes complete full-stack UI
- [x] All routes use Inertia responses, NO API endpoints
- [x] **REMOVED**: All Laravel/Vue/Inertia setup tasks (starter kit already installed)

## Notes

- **[P] tasks** = Different files, no dependencies, can run in parallel
- **Verify tests FAIL** before implementing (RED phase of TDD)
- **Commit after each task** for proper version control
- **Starter Kit Assumption**: Laravel 12 + Vue.js + Inertia.js already configured
- **Constitutional Compliance**: TDD mandatory, no implementation before tests
- **Full-Stack MVP**: Complete game implementation with Vue.js UI

---

**Tasks Generated**: 44 total tasks for game feature implementation  
**Ready for Execution**: All tasks focus on building the actual prediction game  
**Next Step**: Execute tasks starting with T001 (database migrations), ensuring TDD compliance