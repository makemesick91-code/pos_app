<?php

namespace Database\Factories;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminAuditLog>
 */
class AdminAuditLogFactory extends Factory
{
    protected $model = AdminAuditLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_user_id' => User::factory()->platformAdmin(),
            'action' => AdminAuditLog::ACTION_TENANT_VIEWED,
            'target_type' => AdminAuditLog::TARGET_TENANT,
            'target_id' => null,
            'tenant_id' => null,
            'before_values' => null,
            'after_values' => null,
            'metadata' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'PHPUnit',
        ];
    }
}
