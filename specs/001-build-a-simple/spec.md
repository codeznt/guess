# Feature Specification: Social Prediction Game

**Feature Branch**: `001-build-a-simple`  
**Created**: September 9, 2025  
**Status**: Draft  
**Input**: User description: "Build a simple, social game where users can play during short breaks that tests their intuition, builds friendly competition, and gives them accomplishment through prediction accuracy and streak building. Users open the game and see 8-12 daily prediction questions with clear binary choices like "Will Bitcoin be above $100,000 at 6 PM?" They quickly make choices and bet virtual coins based on their confidence level. Users start each day with the same coin allocation, eliminating permanent bankruptcy fears while maintaining daily risk/reward excitement. Higher bets offer bigger multipliers but more risk. Users compete on daily leaderboards that reset each day and build prediction streaks for bonus multipliers. They share wins and achievements with friends, creating natural conversation and friendly rivalry. Users encounter varied categories - weather, crypto, sports, pop culture - with some resolving quickly for immediate gratification and others building anticipation for next-day results. Users participate meaningfully in 2-3 minute sessions, making this perfect for coffee breaks or commutes while building long-term progression through streaks and achievements."

## Execution Flow (main)
```
1. Parse user description from Input
   → If empty: ERROR "No feature description provided"
2. Extract key concepts from description
   → Identified: users, daily predictions, virtual coins, betting, leaderboards, streaks, achievements, social sharing
3. For each unclear aspect:
   → Mark with [NEEDS CLARIFICATION: specific question]
4. Fill User Scenarios & Testing section
   → Clear user flow identified: daily login → view questions → make predictions → bet coins → see results → compete on leaderboards
5. Generate Functional Requirements
   → Each requirement must be testable
   → Mark ambiguous requirements
6. Identify Key Entities (if data involved)
7. Run Review Checklist
   → If any [NEEDS CLARIFICATION]: WARN "Spec has uncertainties"
   → If implementation details found: ERROR "Remove tech details"
8. Return: SUCCESS (spec ready for planning)
```

---

## ⚡ Quick Guidelines
- ✅ Focus on WHAT users need and WHY
- ❌ Avoid HOW to implement (no tech stack, APIs, code structure)
- 👥 Written for business stakeholders, not developers

---

## User Scenarios & Testing *(mandatory)*

### Primary User Story
A user opens the game during their coffee break and sees 8-12 daily prediction questions. They quickly scan through questions like "Will Bitcoin be above $100,000 at 6 PM?" and "Will it rain in New York today?" They make their predictions and decide how many virtual coins to bet based on their confidence. After submitting predictions, they check their position on the daily leaderboard and see their current prediction streak. When results come in, they earn coins for correct predictions with multipliers based on their bet amounts and streak bonuses. They share notable wins with friends and see social achievements that spark conversations.

### Acceptance Scenarios
1. **Given** a user opens the game at the start of a new day, **When** they view the daily questions, **Then** they see 8-12 binary prediction questions from various categories with clear resolution times
2. **Given** a user wants to make a prediction, **When** they select their choice and bet amount, **Then** the system records their prediction and deducts the bet from their daily coin allocation
3. **Given** a user has made all their daily predictions, **When** results are determined, **Then** they receive coins for correct predictions with appropriate multipliers and streak bonuses
4. **Given** a user completes their daily session, **When** they check the leaderboard, **Then** they see their ranking among other players with scores resetting daily
5. **Given** a user achieves a notable result, **When** they choose to share, **Then** they can post their achievement to social platforms or share with friends

### Edge Cases
- What happens when a user runs out of coins mid-session?
- How does the system handle tied leaderboard positions?
- What occurs if a prediction question becomes invalid or cancelled?
- How are users notified of results for delayed-resolution questions?

## Requirements *(mandatory)*

### Functional Requirements
- **FR-001**: System MUST present 8-12 daily prediction questions with clear binary choices
- **FR-002**: System MUST provide questions from multiple categories (weather, crypto, sports, pop culture)
- **FR-003**: System MUST allow users to bet virtual coins on their predictions with confidence-based amounts
- **FR-004**: System MUST reset each user's daily coin allocation to the same amount every day
- **FR-005**: System MUST calculate winnings based on bet amounts with multiplier bonuses
- **FR-006**: System MUST maintain prediction streaks and apply streak bonuses to winnings
- **FR-007**: System MUST display daily leaderboards that reset every 24 hours
- **FR-008**: System MUST resolve questions at specified times and distribute rewards
- **FR-009**: System MUST provide immediate results for quick-resolution questions
- **FR-010**: System MUST allow users to share achievements and wins with friends
- **FR-011**: System MUST support 2-3 minute gameplay sessions
- **FR-012**: System MUST track long-term progression through streaks and achievements
- **FR-013**: System MUST prevent users from changing predictions after submission
- **FR-014**: System MUST display clear resolution times for each question
- **FR-015**: Users MUST be able to view their prediction history and performance statistics

*Clarifications needed:*
- **FR-016**: System MUST authenticate users via Telegram, it is a telegram mini app
- **FR-017**: System MUST define friend connections through external sharing only
- **FR-018**: System MUST handle 1000 maximum daily coin allocation
- **FR-019**: System MUST determine specific multiplier rates and streak bonus

### Key Entities *(include if feature involves data)*
- **User**: Represents a game player with daily coin allocation, prediction history, streak count, and achievement progress
- **Prediction Question**: Binary choice question with category, resolution time, correct answer, and status
- **Prediction**: User's choice and bet amount for a specific question, with timestamp and result
- **Daily Leaderboard**: Ranking of users based on daily performance with scores and positions
- **Achievement**: Milestone or accomplishment earned by users through various game activities
- **Streak**: Consecutive correct predictions counter with associated bonus multipliers

---

## Review & Acceptance Checklist
*GATE: Automated checks run during main() execution*

### Content Quality
- [x] No implementation details (languages, frameworks, APIs)
- [x] Focused on user value and business needs
- [x] Written for non-technical stakeholders
- [x] All mandatory sections completed

### Requirement Completeness
- [ ] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous  
- [x] Success criteria are measurable
- [x] Scope is clearly bounded
- [x] Dependencies and assumptions identified

---

## Execution Status
*Updated by main() during processing*

- [x] User description parsed
- [x] Key concepts extracted
- [x] Ambiguities marked
- [x] User scenarios defined
- [x] Requirements generated
- [x] Entities identified
- [ ] Review checklist passed (pending clarifications)

---