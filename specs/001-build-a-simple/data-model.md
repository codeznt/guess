# Data Model: Social Prediction Game

**Date**: September 9, 2025  
**Feature**: Social Prediction Game - Telegram Mini App  
**Phase**: Phase 1 - Data Model Design  

## Entity Relationship Overview

```
User ||--o{ Prediction : makes
User ||--o{ Achievement : earns
User ||--o{ DailyLeaderboard : appears_in
User ||--|| UserStats : has

PredictionQuestion ||--o{ Prediction : receives
PredictionQuestion }o--|| Category : belongs_to

Prediction }o--|| PredictionQuestion : answers
Prediction }o--|| User : made_by

DailyLeaderboard }o--|| User : ranks
DailyLeaderboard }o--|| Date : for_date

Achievement }o--|| User : earned_by
Achievement }o--|| AchievementType : of_type

UserStats ||--|| User : belongs_to
UserStats ||--o{ Streak : tracks
```

## Core Entities

### User
**Purpose**: Represents a Telegram user participating in the prediction game
**Source**: Telegram WebApp SDK user data

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT | PRIMARY KEY, AUTO_INCREMENT | Internal user ID |
| telegram_id | BIGINT | UNIQUE, NOT NULL | Telegram user ID |
| username | VARCHAR(255) | NULLABLE | Telegram username |
| first_name | VARCHAR(255) | NOT NULL | User's first name |
| last_name | VARCHAR(255) | NULLABLE | User's last name |
| daily_coins | INT | DEFAULT 1000, >=0 | Available coins for current day |
| total_predictions | INT | DEFAULT 0, >=0 | Lifetime prediction count |
| correct_predictions | INT | DEFAULT 0, >=0 | Lifetime correct predictions |
| current_streak | INT | DEFAULT 0, >=0 | Current consecutive correct predictions |
| best_streak | INT | DEFAULT 0, >=0 | Longest streak achieved |
| last_active_date | DATE | NOT NULL | Last day user made predictions |
| created_at | TIMESTAMP | NOT NULL | Account creation |
| updated_at | TIMESTAMP | NOT NULL | Last modification |

**Validation Rules**:
- `daily_coins <= 1000` (daily maximum)
- `correct_predictions <= total_predictions` (logical consistency)
- `current_streak >= 0` (non-negative)
- `telegram_id` must be unique and match Telegram's user ID format

**Relationships**:
- One-to-many: Predictions, Achievements, DailyLeaderboard entries
- One-to-one: UserStats

### PredictionQuestion
**Purpose**: Represents a binary prediction question with resolution criteria
**Source**: External APIs + manual curation

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT | PRIMARY KEY, AUTO_INCREMENT | Question ID |
| category_id | BIGINT | FOREIGN KEY, NOT NULL | Question category |
| title | VARCHAR(255) | NOT NULL | Question text |
| description | TEXT | NULLABLE | Detailed context |
| option_a | VARCHAR(255) | NOT NULL | First choice (e.g., "Yes", "Above") |
| option_b | VARCHAR(255) | NOT NULL | Second choice (e.g., "No", "Below") |
| resolution_time | TIMESTAMP | NOT NULL | When question resolves |
| resolution_criteria | TEXT | NOT NULL | How to determine correct answer |
| correct_answer | ENUM('A','B') | NULLABLE | Resolved answer |
| status | ENUM('pending','active','resolved','cancelled') | DEFAULT 'pending' | Question state |
| external_reference | VARCHAR(255) | NULLABLE | External API reference |
| created_at | TIMESTAMP | NOT NULL | Question creation |
| updated_at | TIMESTAMP | NOT NULL | Last modification |

**Validation Rules**:
- `resolution_time > created_at` (must resolve in future)
- `correct_answer` only set when `status = 'resolved'`
- `option_a != option_b` (distinct choices)
- `title` length between 10-255 characters

**Relationships**:
- Many-to-one: Category
- One-to-many: Predictions

### Prediction
**Purpose**: Represents a user's prediction and bet on a specific question
**Source**: User input via frontend

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT | PRIMARY KEY, AUTO_INCREMENT | Prediction ID |
| user_id | BIGINT | FOREIGN KEY, NOT NULL | User making prediction |
| question_id | BIGINT | FOREIGN KEY, NOT NULL | Question being answered |
| choice | ENUM('A','B') | NOT NULL | User's selected answer |
| bet_amount | INT | NOT NULL, >0, <=1000 | Coins wagered |
| potential_winnings | INT | NOT NULL, >=0 | Calculated potential payout |
| actual_winnings | INT | NULLABLE | Actual payout after resolution |
| is_correct | BOOLEAN | NULLABLE | Whether prediction was correct |
| multiplier_applied | DECIMAL(3,2) | DEFAULT 1.00 | Streak/confidence multiplier |
| created_at | TIMESTAMP | NOT NULL | Prediction time |
| updated_at | TIMESTAMP | NOT NULL | Last modification |

**Validation Rules**:
- `bet_amount <= user.daily_coins` at creation (sufficient funds)
- `bet_amount >= 10` (minimum bet)
- `potential_winnings = bet_amount * multiplier_applied * base_multiplier`
- `actual_winnings` only set after question resolution
- Unique constraint on (user_id, question_id) - one prediction per user per question

**Relationships**:
- Many-to-one: User, PredictionQuestion

### DailyLeaderboard
**Purpose**: Daily rankings of users based on performance
**Source**: Calculated from daily predictions

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT | PRIMARY KEY, AUTO_INCREMENT | Leaderboard entry ID |
| user_id | BIGINT | FOREIGN KEY, NOT NULL | User being ranked |
| leaderboard_date | DATE | NOT NULL | Date of rankings |
| total_winnings | INT | NOT NULL, >=0 | Total coins won for day |
| predictions_made | INT | NOT NULL, >=0 | Number of predictions made |
| correct_predictions | INT | NOT NULL, >=0 | Number correct for day |
| accuracy_percentage | DECIMAL(5,2) | NOT NULL, 0-100 | Daily accuracy rate |
| rank | INT | NOT NULL, >0 | Position on leaderboard |
| created_at | TIMESTAMP | NOT NULL | Entry creation |
| updated_at | TIMESTAMP | NOT NULL | Last update |

**Validation Rules**:
- `correct_predictions <= predictions_made` (logical consistency)
- `accuracy_percentage = (correct_predictions / predictions_made) * 100`
- Unique constraint on (user_id, leaderboard_date) - one entry per user per day
- `rank` must be sequential (1, 2, 3, ...)

**Relationships**:
- Many-to-one: User

### Achievement
**Purpose**: Tracks user accomplishments and milestones
**Source**: System-generated based on user actions

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT | PRIMARY KEY, AUTO_INCREMENT | Achievement ID |
| user_id | BIGINT | FOREIGN KEY, NOT NULL | User who earned achievement |
| achievement_type | VARCHAR(50) | NOT NULL | Type of achievement |
| title | VARCHAR(255) | NOT NULL | Achievement name |
| description | TEXT | NOT NULL | Achievement description |
| icon | VARCHAR(50) | NOT NULL | Icon identifier |
| points_value | INT | NOT NULL, >=0 | Points awarded |
| is_shareable | BOOLEAN | DEFAULT true | Can be shared socially |
| earned_at | TIMESTAMP | NOT NULL | When achievement was earned |
| shared_at | TIMESTAMP | NULLABLE | When achievement was shared |

**Achievement Types**:
- `first_prediction`: First prediction made
- `perfect_day`: 100% accuracy for a day
- `streak_milestone`: Streak milestones (5, 10, 25, 50, 100)
- `big_winner`: High single-day winnings
- `consistency`: Multiple days of participation
- `social_butterfly`: Sharing achievements
- `risk_taker`: High bet amounts
- `conservative`: Consistent small bets

**Relationships**:
- Many-to-one: User

### Category
**Purpose**: Categorizes prediction questions by topic
**Source**: System-defined categories

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT | PRIMARY KEY, AUTO_INCREMENT | Category ID |
| name | VARCHAR(100) | UNIQUE, NOT NULL | Category name |
| description | TEXT | NULLABLE | Category description |
| icon | VARCHAR(50) | NOT NULL | Icon identifier |
| color | VARCHAR(7) | NOT NULL | Hex color code |
| is_active | BOOLEAN | DEFAULT true | Category is available |
| sort_order | INT | DEFAULT 0 | Display order |
| created_at | TIMESTAMP | NOT NULL | Category creation |
| updated_at | TIMESTAMP | NOT NULL | Last modification |

**Predefined Categories**:
- Weather: Weather predictions, temperature forecasts
- Crypto: Cryptocurrency price movements
- Sports: Game outcomes, player performance
- Pop Culture: Entertainment, celebrity news
- Politics: Election results, policy outcomes
- Economics: Market movements, economic indicators

**Relationships**:
- One-to-many: PredictionQuestions

## Supporting Entities

### UserStats
**Purpose**: Aggregated statistics and calculated fields for users
**Source**: Calculated from user activity

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| user_id | BIGINT | PRIMARY KEY, FOREIGN KEY | User reference |
| total_earnings | BIGINT | DEFAULT 0, >=0 | Lifetime coin earnings |
| total_spent | BIGINT | DEFAULT 0, >=0 | Lifetime coins spent on bets |
| average_bet | DECIMAL(8,2) | DEFAULT 0, >=0 | Average bet amount |
| favorite_category_id | BIGINT | NULLABLE, FOREIGN KEY | Most predicted category |
| best_category_accuracy | DECIMAL(5,2) | DEFAULT 0, 0-100 | Highest category accuracy |
| total_achievements | INT | DEFAULT 0, >=0 | Number of achievements earned |
| days_active | INT | DEFAULT 0, >=0 | Total days with predictions |
| last_calculated | TIMESTAMP | NOT NULL | When stats were last updated |

**Relationships**:
- One-to-one: User
- Many-to-one: Category (favorite)

## State Transitions

### PredictionQuestion States
```
pending → active (when question becomes available for predictions)
active → resolved (when resolution_time passes and answer determined)
active → cancelled (if question becomes invalid)
resolved → [terminal state]
cancelled → [terminal state]
```

### Prediction States
```
created → [immutable until question resolves]
created → won (when question resolves and prediction is correct)
created → lost (when question resolves and prediction is incorrect)
```

### Daily Reset Logic
```
Every day at 00:00 UTC:
1. Update User.daily_coins = 1000 for all users
2. Update User.last_active_date if user made predictions previous day
3. Calculate DailyLeaderboard entries for previous day
4. Generate new PredictionQuestions for current day
5. Reset any daily-specific caches
```

## Indexes and Performance

### Primary Indexes
- Users: `telegram_id` (unique), `last_active_date`
- Predictions: `(user_id, question_id)` (unique), `question_id`
- PredictionQuestions: `category_id`, `status`, `resolution_time`
- DailyLeaderboard: `(user_id, leaderboard_date)` (unique), `leaderboard_date`

### Composite Indexes
- Predictions: `(question_id, is_correct)` for win/loss calculations
- DailyLeaderboard: `(leaderboard_date, rank)` for ranking queries
- Achievements: `(user_id, earned_at)` for recent achievements

### Query Optimization
- Leaderboard queries use date-partitioned indexes
- User statistics cached in Redis with TTL
- Question resolution queries batch-processed
- Daily reset operations use database transactions

## Data Integrity Rules

### Business Logic Constraints
1. **Coin Conservation**: Total coins in system must be auditable
2. **Prediction Finality**: No modifications after question resolution
3. **Streak Accuracy**: Streak calculations must be verifiable
4. **Leaderboard Consistency**: Rankings must be mathematically correct
5. **Achievement Uniqueness**: No duplicate achievements per user

### Referential Integrity
- All foreign keys have CASCADE DELETE where appropriate
- Orphaned records cleaned up by scheduled jobs
- Data retention policies for inactive users (2 years)
- Audit trail for all financial operations

---
**Data Model Complete**: Ready for contract generation and API design