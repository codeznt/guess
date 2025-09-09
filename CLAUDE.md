# Social Prediction Game - Claude Code Context

**Project**: Telegram Mini App for social prediction gaming  
**Tech Stack**: Laravel 12 + Vue 3 + Inertia.js + Telegram WebApp SDK  
**Last Updated**: September 9, 2025  

## Architecture Overview

### Backend (Laravel 12)
- **Framework**: PHP 8.3 with Laravel 12
- **Authentication**: Telegram WebApp SDK integration
- **Database**: MySQL 8.0 with Redis caching
- **Real-time**: Laravel Broadcasting with Pusher
- **APIs**: External integrations for prediction data (weather, crypto, sports)
- **Queue**: Laravel jobs for automated tasks (daily resets, resolution)

### Frontend (Vue 3)
- **Framework**: Vue 3 Composition API with TypeScript
- **UI Library**: Tailwind CSS + shadcn-vue components
- **State Management**: Vue reactive system + Pinia (if needed)
- **Build Tool**: Vite with hot module replacement
- **Integration**: Inertia.js for server-side routing with SPA experience

### Key Features
- **Daily Predictions**: 8-12 binary choice questions across multiple categories
- **Virtual Currency**: Daily coin allocation (1000) with betting system
- **Social Competition**: Daily leaderboards with real-time updates
- **Gamification**: Streaks, achievements, multipliers, social sharing
- **Mobile-First**: Telegram WebApp optimized for mobile experience

## Development Patterns

### Laravel Conventions
```php
// Use Eloquent relationships directly
class User extends Model {
    public function predictions() {
        return $this->hasMany(Prediction::class);
    }
    
    public function dailyLeaderboard() {
        return $this->hasMany(DailyLeaderboard::class);
    }
}

// Service classes for business logic
class PredictionService {
    public function submitPrediction(User $user, PredictionQuestion $question, string $choice, int $betAmount): Prediction
    public function calculateWinnings(Prediction $prediction): int
    public function updateStreak(User $user, bool $isCorrect): void
}

// Artisan commands for automation
class ResetDailyCoins extends Command {
    protected $signature = 'game:reset-daily-coins';
    protected $description = 'Reset all users daily coin allocation';
}
```

### Vue 3 Patterns
```typescript
// Composition API with TypeScript
import { ref, computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'

interface PredictionQuestion {
  id: number
  title: string
  option_a: string
  option_b: string
  resolution_time: string
  category: Category
}

const usePredictions = () => {
  const questions = ref<PredictionQuestion[]>([])
  const userCoins = ref<number>(1000)
  const selectedBets = ref<Map<number, {choice: string, amount: number}>>(new Map())
  
  const totalBet = computed(() => {
    return Array.from(selectedBets.value.values())
      .reduce((sum, bet) => sum + bet.amount, 0)
  })
  
  const submitPredictions = async () => {
    await router.post('/predictions', {
      predictions: Array.from(selectedBets.value.entries()).map(([questionId, bet]) => ({
        question_id: questionId,
        choice: bet.choice,
        bet_amount: bet.amount
      }))
    })
  }
  
  return { questions, userCoins, selectedBets, totalBet, submitPredictions }
}
```

### Telegram WebApp Integration
```javascript
// Initialize Telegram WebApp
const tg = window.Telegram.WebApp
tg.ready()
tg.expand()

// Authentication
const initData = tg.initDataUnsafe
const user = initData.user

// Theme integration
const themeParams = tg.themeParams
document.documentElement.style.setProperty('--tg-color-scheme', tg.colorScheme)

// Haptic feedback
tg.HapticFeedback.impactOccurred('medium')

// Share functionality
const shareToStory = (text: string, url: string) => {
  tg.shareToStory(url, { text })
}
```

## Database Schema

### Core Tables
```sql
-- Users with Telegram integration
users: id, telegram_id, username, first_name, last_name, daily_coins, current_streak, best_streak

-- Prediction questions with categories
prediction_questions: id, category_id, title, option_a, option_b, resolution_time, correct_answer, status

-- User predictions with betting
predictions: id, user_id, question_id, choice, bet_amount, potential_winnings, actual_winnings, is_correct

-- Daily leaderboards
daily_leaderboards: id, user_id, leaderboard_date, total_winnings, predictions_made, accuracy_percentage, rank

-- Achievements and gamification
achievements: id, user_id, achievement_type, title, description, points_value, earned_at

-- Categories for organization
categories: id, name, description, icon, color, is_active
```

### Key Relationships
- User → hasMany Predictions, Achievements, DailyLeaderboard entries
- PredictionQuestion → hasMany Predictions, belongsTo Category
- Prediction → belongsTo User, PredictionQuestion

## API Endpoints

### REST API (Laravel routes)
```php
// Daily questions and predictions
GET /api/questions/daily         // Fetch today's questions
POST /api/predictions           // Submit user predictions

// Leaderboard and social
GET /api/leaderboard/daily      // Current day rankings
GET /api/user/stats            // Personal statistics
GET /api/user/streaks          // Streak information
POST /api/achievements/share    // Social sharing
```

### Real-time Broadcasting
```php
// Laravel Broadcasting channels
Channel::make('leaderboard.daily')           // Real-time ranking updates  
Channel::make('predictions.{userId}')        // Personal notifications
Channel::make('achievements.global')         // Shared achievements
```

## External Integrations

### Data Sources
- **Weather**: OpenWeatherMap API for weather predictions
- **Crypto**: CoinGecko API for cryptocurrency prices
- **Sports**: The Sports DB + ESPN for game outcomes
- **Pop Culture**: Manual curation with future Twitter API integration

### Implementation Pattern
```php
class ExternalDataService {
    public function fetchWeatherPrediction(string $city, string $date): array
    public function getCryptoPrices(array $symbols): array
    public function getSportsSchedule(string $league, string $date): array
}

// Scheduled job for data fetching
class FetchExternalData extends Job {
    public function handle() {
        // Update prediction questions with real data
        // Resolve completed questions
        // Generate new questions for tomorrow
    }
}
```

## Testing Strategy

### Laravel Testing
```php
// Feature tests for API endpoints
class PredictionApiTest extends TestCase {
    public function test_user_can_submit_predictions()
    public function test_insufficient_coins_prevents_bet()
    public function test_leaderboard_calculates_correctly()
}

// Unit tests for business logic
class PredictionServiceTest extends TestCase {
    public function test_streak_calculation_logic()
    public function test_winnings_calculation_with_multipliers()
}
```

### Vue Testing (Vitest)
```typescript
// Component tests
import { mount } from '@vue/test-utils'
import PredictionCard from '@/Components/PredictionCard.vue'

test('renders question with betting options', () => {
  const wrapper = mount(PredictionCard, {
    props: { question, userCoins: 1000 }
  })
  expect(wrapper.text()).toContain(question.title)
})
```

## Performance Considerations

### Database Optimization
- Composite indexes on (user_id, prediction_date) for daily queries
- Redis caching for leaderboard rankings (TTL: 1 hour)
- Query optimization for large-scale leaderboard calculations
- Database connection pooling for concurrent users

### Frontend Performance
- Vue component lazy loading for non-critical UI
- Vite code splitting and bundling optimization
- Service worker for offline capability (future)
- Efficient reactive state management

### Real-time Features
- Laravel Echo with Pusher for WebSocket fallback
- Connection pooling and presence channel optimization
- Rate limiting for broadcasting events
- Mobile-optimized connection handling

## Recent Changes
1. **September 9, 2025**: Initial project specification and implementation plan
2. **Architecture Decision**: Laravel 12 + Vue 3 + Inertia.js stack finalized
3. **Database Schema**: Core entities designed with proper relationships
4. **API Design**: RESTful endpoints with OpenAPI specification
5. **Telegram Integration**: WebApp SDK patterns established

## Development Notes
- Follow TDD approach: write tests before implementation
- Use Laravel's built-in features (Eloquent, Broadcasting, Queues)
- Leverage Vue 3 Composition API for better TypeScript integration
- Implement proper error handling for external API failures
- Design for mobile-first Telegram WebApp experience
- Plan for 10k+ daily active users from launch

---
*Context updated automatically - see /specs/001-build-a-simple/ for detailed documentation*