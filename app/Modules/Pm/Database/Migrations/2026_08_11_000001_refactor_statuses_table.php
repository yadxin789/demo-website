<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pm';

    /**
     * 重构 statuses 表：从独立状态字典改为状态机 label 覆盖表
     * state_class 存储状态机完整类名（唯一键）
     * custom_label 存储用户自定义的标签名（为空则使用状态机默认 label）
     */
    public function up()
    {
        // 清空旧数据（旧的 code 体系已废弃）
        Schema::dropIfExists('statuses');

        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->string('state_class')->unique()->comment('状态机完整类名，如 App\\States\\Project\\InProgress');
            $table->string('custom_label')->nullable()->comment('自定义标签名，为空时使用状态机默认 label()');
            $table->timestamps();
        });

        // 初始化默认数据：为所有状态机类创建记录
        $states = [
            // 项目状态
            \App\Modules\Pm\States\Project\InProgress::class,
            \App\Modules\Pm\States\Project\Completed::class,
            \App\Modules\Pm\States\Project\Overdue::class,
            \App\Modules\Pm\States\Project\OverdueCompleted::class,
            \App\Modules\Pm\States\Project\Suspended::class,
            \App\Modules\Pm\States\Project\NotStarted::class,
            // 任务状态
            \App\Modules\Pm\States\Task\Pending::class,
            \App\Modules\Pm\States\Task\InProgress::class,
            \App\Modules\Pm\States\Task\ForAudit::class,
            \App\Modules\Pm\States\Task\Completed::class,
            \App\Modules\Pm\States\Task\Rejected::class,
            \App\Modules\Pm\States\Task\Overdue::class,
            \App\Modules\Pm\States\Task\OverdueCompleted::class,
        ];

        foreach ($states as $stateClass) {
            \Illuminate\Support\Facades\DB::table('statuses')->insert([
                'state_class' => $stateClass,
                'custom_label' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('statuses');

        // 回滚到旧结构
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type');
            $table->string('color')->default('#6c757d');
            $table->timestamps();
        });
    }
};
