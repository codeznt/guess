<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'icon',
        'color',
        'is_active',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the prediction questions for this category.
     */
    public function predictionQuestions(): HasMany
    {
        return $this->hasMany(PredictionQuestion::class);
    }

    /**
     * Get active prediction questions for this category.
     */
    public function activePredictionQuestions(): HasMany
    {
        return $this->predictionQuestions()
            ->where('status', 'active')
            ->where('resolution_time', '>', now());
    }

    /**
     * Scope to get only active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order categories by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get the total number of questions in this category.
     */
    public function getTotalQuestionsAttribute(): int
    {
        return $this->predictionQuestions()->count();
    }

    /**
     * Get the number of active questions in this category.
     */
    public function getActiveQuestionsCountAttribute(): int
    {
        return $this->activePredictionQuestions()->count();
    }

    /**
     * Check if the category has any active questions.
     */
    public function hasActiveQuestions(): bool
    {
        return $this->activePredictionQuestions()->exists();
    }

    /**
     * Get the category's style attributes for frontend.
     */
    public function getStyleAttribute(): array
    {
        return [
            'icon' => $this->icon,
            'color' => $this->color,
        ];
    }

    /**
     * Default categories to seed.
     */
    public static function getDefaultCategories(): array
    {
        return [
            [
                'name' => 'Weather',
                'description' => 'Weather predictions and temperature forecasts',
                'icon' => 'cloud-sun',
                'color' => '#3B82F6',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Crypto',
                'description' => 'Cryptocurrency price movements and market predictions',
                'icon' => 'bitcoin',
                'color' => '#F7931A',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Sports',
                'description' => 'Game outcomes and player performance predictions',
                'icon' => 'football',
                'color' => '#10B981',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Pop Culture',
                'description' => 'Entertainment, celebrity news, and trending topics',
                'icon' => 'star',
                'color' => '#EC4899',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Politics',
                'description' => 'Election results and policy outcome predictions',
                'icon' => 'building-government',
                'color' => '#6B7280',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Economics',
                'description' => 'Market movements and economic indicator predictions',
                'icon' => 'chart-line',
                'color' => '#059669',
                'is_active' => true,
                'sort_order' => 6,
            ],
        ];
    }
}