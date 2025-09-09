# Quickstart Guide: Social Prediction Game

**Purpose**: End-to-end validation of the complete user journey  
**Target Audience**: Developers, QA testers, Product managers  
**Duration**: 5-10 minutes  

## Prerequisites

### Development Environment
- Laravel 12 application running locally or on staging
- Vue 3 frontend with Telegram WebApp SDK integration
- MySQL database with seeded test data
- Redis cache server running
- Telegram Bot created with WebApp configured

### Test Data Required
- At least 10 active prediction questions across 4 categories
- Test user with Telegram ID and initial coin balance
- Sample leaderboard with 5+ users for comparison
- External API mock responses for question resolution

## User Journey Validation

### Step 1: Authentication & Initial Load
**Objective**: Verify Telegram WebApp authentication and data loading

1. **Open Telegram WebApp**:
   - Navigate to test bot and launch WebApp
   - Verify Telegram user data is captured correctly
   - Check that user is automatically authenticated

2. **Verify Initial State**:
   ```bash
   # Expected user state after authentication
   daily_coins: 1000
   current_streak: [varies by user]
   last_active_date: [previous login date]
   ```

3. **Validate Data Loading**:
   - Confirm 8-12 daily questions are displayed
   - Check questions span 3+ categories
   - Verify resolution times are clearly shown
   - Ensure all questions are in 'active' status

**Success Criteria**:
- ✅ User authenticated without additional login prompts  
- ✅ Daily coin balance shows 1000 (or correct reset amount)
- ✅ Questions load within 3 seconds on 3G connection
- ✅ All UI elements render correctly on mobile viewport

### Step 2: Making Predictions
**Objective**: Test prediction submission and coin management

1. **Select Multiple Questions**:
   - Choose 5 questions across different categories
   - Vary bet amounts: 50, 100, 200, 100, 150 coins
   - Mix answer choices (both A and B options)
   - Test different confidence levels

2. **Validate Bet Calculation**:
   ```
   Question 1: 50 coins on "Bitcoin above $100k" (A)
   Question 2: 100 coins on "Rain in NYC today" (B)  
   Question 3: 200 coins on "Lakers win tonight" (A)
   Question 4: 100 coins on "Movie wins Oscar" (B)
   Question 5: 150 coins on "Stock market up" (A)
   
   Total bet: 600 coins
   Remaining coins: 400 coins
   ```

3. **Test Edge Cases**:
   - Try betting more coins than available (should fail)
   - Attempt to modify prediction after submission (should fail)
   - Test minimum bet validation (10 coins minimum)
   - Verify maximum bet per question (1000 coins max)

**Success Criteria**:
- ✅ All predictions submitted successfully
- ✅ Remaining coin balance updates in real-time  
- ✅ UI prevents invalid bet amounts
- ✅ Predictions are immutable after submission
- ✅ API responds within 200ms for prediction submission

### Step 3: Leaderboard Interaction
**Objective**: Verify social features and competitive elements

1. **View Current Rankings**:
   - Navigate to daily leaderboard
   - Find current user's position
   - Check top 10 rankings for accuracy
   - Verify ranking calculations match expected logic

2. **Validate Leaderboard Data**:
   ```
   Expected fields per user:
   - Rank position (1, 2, 3, ...)
   - User name (first_name + username)
   - Total winnings for day
   - Number of predictions made  
   - Accuracy percentage
   - Current streak
   ```

3. **Test Real-time Updates**:
   - Have another test user make predictions
   - Verify leaderboard updates within 30 seconds
   - Check that rankings adjust correctly
   - Confirm user's own rank updates properly

**Success Criteria**:
- ✅ User's rank displays correctly based on performance
- ✅ Leaderboard shows consistent data across all fields
- ✅ Real-time updates work without page refresh
- ✅ Pagination works for large user sets (50+ users)

### Step 4: Question Resolution & Rewards
**Objective**: Test the complete prediction lifecycle

1. **Simulate Question Resolution**:
   - Use admin interface to resolve test questions
   - Set correct answers for questions user predicted
   - Trigger reward calculation process
   - Verify notifications are sent to users

2. **Validate Reward Calculation**:
   ```
   Example calculation:
   Question 1: 50 coins bet, CORRECT, base multiplier 1.5
   - Current streak: 3 (multiplier: 1.05)  
   - Winnings: 50 * 1.5 * 1.05 = 78 coins
   
   Question 2: 100 coins bet, INCORRECT
   - Winnings: 0 coins
   - Streak resets to 0
   ```

3. **Check User State Updates**:
   - Verify correct_predictions counter incremented
   - Confirm total_predictions counter updated
   - Check streak calculations are accurate
   - Validate daily leaderboard position updated

**Success Criteria**:
- ✅ Winnings calculated correctly with multipliers
- ✅ Streak logic works properly (increment/reset)  
- ✅ User statistics update accurately
- ✅ Leaderboard reflects new results within 5 minutes

### Step 5: Achievement & Social Features
**Objective**: Test gamification and sharing capabilities

1. **Trigger Achievement**:
   - Perform action that earns achievement (e.g., perfect day)
   - Verify achievement appears in user profile
   - Check achievement notification displays
   - Validate achievement points are awarded

2. **Test Social Sharing**:
   - Navigate to achievement sharing interface
   - Select Telegram as sharing platform
   - Customize sharing message
   - Confirm share URL generation works

3. **Validate Achievement Types**:
   ```
   Common achievements to test:
   - first_prediction: First prediction made
   - perfect_day: 100% accuracy for a day  
   - streak_milestone: 5, 10, 25 day streaks
   - big_winner: High single-day winnings
   - risk_taker: Betting large amounts
   ```

**Success Criteria**:
- ✅ Achievements are triggered correctly by user actions
- ✅ Sharing generates valid URLs with proper content
- ✅ Achievement history is persistent across sessions
- ✅ Social sharing integrates with Telegram properly

## Performance Validation

### Response Time Testing
```bash
# API endpoint performance targets
GET /api/questions/daily: < 500ms
POST /api/predictions: < 200ms  
GET /api/leaderboard/daily: < 300ms
GET /api/user/stats: < 200ms
```

### Load Testing (Optional)
```bash
# Simulate concurrent users
- 100 simultaneous users making predictions
- 500 users viewing leaderboard
- 50 users sharing achievements
- All operations within performance targets
```

## Troubleshooting Common Issues

### Authentication Failures
**Symptom**: User not authenticated via Telegram
**Solution**: 
1. Check Telegram WebApp configuration
2. Verify bot token and domain settings
3. Ensure HTTPS is enabled for WebApp
4. Check browser console for JavaScript errors

### Slow Question Loading
**Symptom**: Questions take >3 seconds to load
**Solutions**:
1. Check database query performance
2. Verify Redis caching is working
3. Optimize question query with proper indexes
4. Check external API response times

### Incorrect Calculations
**Symptom**: Winnings or streaks calculated wrong
**Solutions**:
1. Review multiplier calculation logic
2. Check streak increment/reset conditions
3. Verify database transaction integrity
4. Test edge cases with manual calculations

### Real-time Updates Not Working  
**Symptom**: Leaderboard doesn't update automatically
**Solutions**:
1. Check Laravel Broadcasting configuration
2. Verify Pusher/WebSocket connection status
3. Test event broadcasting in development
4. Confirm frontend event listeners are active

## Acceptance Checklist

### Core Functionality
- [ ] User can authenticate via Telegram without issues
- [ ] Daily questions load correctly (8-12 questions, multiple categories)
- [ ] Predictions can be submitted with appropriate bet amounts
- [ ] Coin balance updates accurately after predictions
- [ ] Leaderboard displays correct rankings and statistics
- [ ] Questions resolve properly and rewards are calculated correctly
- [ ] Streak logic works (increment correct, reset on wrong)
- [ ] Achievements are triggered and can be shared

### Performance Requirements
- [ ] Page load times under 3 seconds on 3G
- [ ] API responses under target times (200-500ms)
- [ ] Real-time updates delivered within 30 seconds
- [ ] System handles 100+ concurrent users without degradation

### User Experience
- [ ] Mobile-responsive design works on various screen sizes
- [ ] Telegram WebApp integration feels native
- [ ] Error messages are clear and actionable
- [ ] Loading states provide appropriate feedback
- [ ] Social sharing generates compelling content

### Data Integrity
- [ ] No duplicate predictions allowed per user per question
- [ ] Coin conservation maintained (no money created/lost)
- [ ] User statistics accurately reflect activity
- [ ] Leaderboard rankings are mathematically correct
- [ ] Achievement requirements are properly validated

---

**Quickstart Complete**: System ready for user acceptance testing and production deployment

**Next Steps**: 
1. Execute automated test suite
2. Perform load testing with realistic user patterns
3. Conduct accessibility audit for Telegram WebApp
4. Review security considerations for financial operations
5. Plan gradual rollout strategy for production launch