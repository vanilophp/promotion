<?php

declare(strict_types=1);

namespace Vanilo\Promotion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Vanilo\Promotion\Contracts\Promotion;
use Vanilo\Promotion\Contracts\PromotionRule as PromotionRuleContract;
use Vanilo\Promotion\Contracts\PromotionRuleType;
use Vanilo\Promotion\PromotionRuleTypes;
use Vanilo\Support\Traits\ConfigurableModel;
use Vanilo\Support\Traits\ConfigurationHasNoSchema;

/**
 * @property int $id
 * @property int $promotion_id
 * @property string $type
 * @property ?array $configuration
 *
 * @property Promotion $promotion
 */
class PromotionRule extends Model implements PromotionRuleContract
{
    use ConfigurableModel;
    use ConfigurationHasNoSchema;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'configuration' => 'array',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(PromotionProxy::modelClass());
    }

    public function getRuleType(): PromotionRuleType
    {
        return PromotionRuleTypes::make($this->type)->setConfiguration($this->configuration);
    }

    public function isRuleTypPassing(object $subject): bool
    {
        return $this->getRuleType()->isPassing($subject);
    }
}