<ul class="nav flex-column" role="navigation">
                        <!-- 所有角色都有首页 -->
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('pm.dashboard') }}">
                                <i class="fa fa-home me-2"></i> 首页
                            </a>
                        </li>

                        <!-- AI：管理员进入「AI 设置」管理，其它角色使用「AI 智能助手」 -->
                        @if($isAdmin)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('ai.*') ? 'active' : '' }}" href="{{ route('pm.ai.settings') }}">
                                <i class="fa fa-robot me-2"></i> AI 设置
                            </a>
                        </li>
                        @else
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('ai.*') ? 'active' : '' }}" href="{{ route('pm.ai.chat') }}">
                                <i class="fa fa-robot me-2"></i> AI 智能助手
                            </a>
                        </li>
                        @endif

                        <!-- AI 授权中心：所有登录用户可用（签发委托令牌 / 审批 AI 变更） -->
                        @php
                            $pendingAiProposals = auth()->check()
                                ? \App\Modules\Pm\Models\AiChangeProposal::where('user_id', auth()->id())
                                    ->where('status', 'pending')->where('expires_at', '>', now())->count()
                                : 0;
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('ai-delegation.*') ? 'active' : '' }}" href="{{ route('pm.ai-delegation.index') }}">
                                <i class="fa fa-key me-2"></i> AI 授权中心
                                @if($pendingAiProposals > 0)
                                <span class="badge bg-warning text-dark ms-1">{{ $pendingAiProposals }}</span>
                                @endif
                            </a>
                        </li>

                        <!-- 管理员菜单 -->
                        @if($isAdmin)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.users') ? 'active' : '' }}" href="{{ route('pm.admin.users') }}">
                                <i class="fa fa-users me-2"></i> 用户管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.departments') ? 'active' : '' }}" href="{{ route('pm.admin.departments') }}">
                                <i class="fa fa-building me-2"></i> 部门管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.roles') ? 'active' : '' }}" href="{{ route('pm.admin.roles') }}">
                                <i class="fa fa-key me-2"></i> 角色管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.statuses') ? 'active' : '' }}" href="{{ route('pm.admin.statuses') }}">
                                <i class="fa fa-toggle-on me-2"></i> 状态管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.task-types') ? 'active' : '' }}" href="{{ route('pm.admin.task-types') }}">
                                <i class="fa fa-list-alt me-2"></i> 任务类型管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.chat-messages*') ? 'active' : '' }}" href="{{ route('pm.admin.chat-messages') }}">
                                <i class="fa fa-comments me-2"></i> 聊天记录管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.file-repository') ? 'active' : '' }}" href="{{ route('pm.admin.file-repository') }}">
                                <i class="fa fa-folder-open me-2"></i> 文件仓库
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.trash*') || request()->routeIs('admin.users.deleted') ? 'active' : '' }}" href="{{ route('pm.admin.trash') }}">
                                <i class="fa fa-trash me-2"></i> 回收站
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('knowledge.*') ? 'active' : '' }}" href="{{ route('pm.knowledge.index') }}">
                                <i class="fa fa-book me-2"></i> 知识库
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.operation-logs') ? 'active' : '' }}" href="{{ route('pm.admin.operation-logs') }}">
                                <i class="fa fa-file-text me-2"></i> 操作日志
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.send-notification') ? 'active' : '' }}" href="{{ route('pm.admin.send-notification') }}">
                                <i class="fa fa-bell me-2"></i> 发送通知
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.services') ? 'active' : '' }}" href="{{ route('pm.admin.services') }}">
                                <i class="fa fa-server me-2"></i> 后台服务
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('pm.admin.wecom.*') ? 'active' : '' }}" href="{{ route('pm.admin.wecom.settings') }}">
                                <i class="fab fa-weixin me-2"></i> 企业微信
                            </a>
                        </li>
                        @endif
                        
                        <!-- 项目经理菜单（项目发起人） -->
                        @if($isManager)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('project-template.*') ? 'active' : '' }}" href="{{ route('pm.project-template.index') }}">
                                <i class="fa fa-clone me-2"></i> 项目模板
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('project.create') ? 'active' : '' }}" href="{{ route('pm.project.create') }}">
                                <i class="fa fa-plus-circle me-2"></i> 创建项目
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('project.my-projects*') ? 'active' : '' }}" href="{{ route('pm.project.my-projects') }}">
                                <i class="fa fa-folder-open me-2"></i> 我的项目列表
                                @if($myProjectCount > 0)
                                <span class="badge bg-primary ms-2">{{ $myProjectCount }}</span>
                                @endif
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('extension.index') ? 'active' : '' }}" href="{{ route('pm.extension.index') }}">
                                <i class="fa fa-calendar me-2"></i> 审核延期申请
                                @if($pendingExtensionCount > 0)
                                <span class="badge bg-danger ms-2">{{ $pendingExtensionCount }}</span>
                                @endif
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('file.resource-files') ? 'active' : '' }}" href="{{ route('pm.file.resource-files') }}">
                                <i class="fa fa-file me-2"></i> 资料文件
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('knowledge.*') ? 'active' : '' }}" href="{{ route('pm.knowledge.index') }}">
                                <i class="fa fa-book me-2"></i> 知识库
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('notification.index') ? 'active' : '' }}" href="{{ route('pm.notification.index') }}">
                                <i class="fa fa-bell me-2"></i> 通知中心
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('pm.profile.wecom.*') ? 'active' : '' }}" href="{{ route('pm.profile.wecom.index') }}">
                                <i class="fab fa-weixin me-2"></i> 企业微信绑定
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('pm.wecom.file.*') ? 'active' : '' }}" href="{{ route('pm.wecom.file.index') }}">
                                <i class="fa fa-inbox me-2"></i> 企微文件收件箱
                            </a>
                        </li>
                        @endif
                        
                        <!-- 项目总经理菜单（只读监督 + 消息通知） -->
                        @if($isProjectManager && !$isAdmin)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('project.index') ? 'active' : '' }}" href="{{ route('pm.project.index') }}">
                                <i class="fa fa-eye me-2"></i> 全部项目
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('extension.index') ? 'active' : '' }}" href="{{ route('pm.extension.index') }}">
                                <i class="fa fa-calendar me-2"></i> 延期申请记录
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('file.resource-files') ? 'active' : '' }}" href="{{ route('pm.file.resource-files') }}">
                                <i class="fa fa-file me-2"></i> 资料文件
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('knowledge.*') ? 'active' : '' }}" href="{{ route('pm.knowledge.index') }}">
                                <i class="fa fa-book me-2"></i> 知识库
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('notification.index') ? 'active' : '' }}" href="{{ route('pm.notification.index') }}">
                                <i class="fa fa-bell me-2"></i> 通知中心
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('pm.profile.wecom.*') ? 'active' : '' }}" href="{{ route('pm.profile.wecom.index') }}">
                                <i class="fab fa-weixin me-2"></i> 企业微信绑定
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('pm.wecom.file.*') ? 'active' : '' }}" href="{{ route('pm.wecom.file.index') }}">
                                <i class="fa fa-inbox me-2"></i> 企微文件收件箱
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.send-notification') ? 'active' : '' }}" href="{{ route('pm.admin.send-notification') }}">
                                <i class="fa fa-paper-plane me-2"></i> 发送指令通知
                            </a>
                        </li>
                        @endif
                        
                        <!-- 审核人菜单（部门主管） -->
                        @if($isDeptManager && !$isAdmin && !$isManager && !$isProjectManager)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('project.my-projects*') ? 'active' : '' }}" href="{{ route('pm.project.my-projects') }}">
                                <i class="fa fa-folder-open me-2"></i> 我的项目列表
                                @if($myProjectCount > 0)
                                <span class="badge bg-primary ms-2">{{ $myProjectCount }}</span>
                                @endif
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('task.my-audit-tasks.pending-audit') ? 'active' : '' }}" href="{{ route('pm.task.my-audit-tasks.pending-audit') }}">
                                <i class="fa fa-tasks me-2"></i> 我的待审核任务列表
                                @if($myPendingAuditTaskCount > 0)
                                <span class="badge bg-danger ms-2">{{ $myPendingAuditTaskCount }}</span>
                                @endif
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('file.resource-files') ? 'active' : '' }}" href="{{ route('pm.file.resource-files') }}">
                                <i class="fa fa-file me-2"></i> 资料文件
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('knowledge.*') ? 'active' : '' }}" href="{{ route('pm.knowledge.index') }}">
                                <i class="fa fa-book me-2"></i> 知识库
                            </a>
                        </li>
                        @endif
                        
                        <!-- 任务执行人菜单 -->
                        @if($isExecutor && !$isAdmin && !$isManager && !$isProjectManager && !$isDeptManager)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('project.my-projects*') ? 'active' : '' }}" href="{{ route('pm.project.my-projects') }}">
                                <i class="fa fa-folder-open me-2"></i> 我的项目列表
                                @if($myProjectCount > 0)
                                <span class="badge bg-primary ms-2">{{ $myProjectCount }}</span>
                                @endif
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('task.my-tasks.pending-execution') ? 'active' : '' }}" href="{{ route('pm.task.my-tasks.pending-execution') }}">
                                <i class="fa fa-tasks me-2"></i> 我的待执行任务列表
                                @if($myPendingExecutionTaskCount > 0)
                                <span class="badge bg-danger ms-2">{{ $myPendingExecutionTaskCount }}</span>
                                @endif
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('file.resource-files') ? 'active' : '' }}" href="{{ route('pm.file.resource-files') }}">
                                <i class="fa fa-file me-2"></i> 资料文件
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('knowledge.*') ? 'active' : '' }}" href="{{ route('pm.knowledge.index') }}">
                                <i class="fa fa-book me-2"></i> 知识库
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('notification.index') ? 'active' : '' }}" href="{{ route('pm.notification.index') }}">
                                <i class="fa fa-bell me-2"></i> 通知中心
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('pm.profile.wecom.*') ? 'active' : '' }}" href="{{ route('pm.profile.wecom.index') }}">
                                <i class="fab fa-weixin me-2"></i> 企业微信绑定
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('pm.wecom.file.*') ? 'active' : '' }}" href="{{ route('pm.wecom.file.index') }}">
                                <i class="fa fa-inbox me-2"></i> 企微文件收件箱
                            </a>
                        </li>
                        @endif
                        
                        <!-- 支援人员菜单（非任务参与人员） -->
                        @if($isSupport && !$isAdmin && !$isManager && !$isProjectManager && !$isDeptManager && !$isExecutor)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('support.index') ? 'active' : '' }}" href="{{ route('pm.support.index') }}">
                                <i class="fa fa-life-ring me-2"></i> 支援中心
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('file.manage-resource-files') ? 'active' : '' }}" href="{{ route('pm.file.manage-resource-files') }}">
                                <i class="fa fa-file me-2"></i> 资料文件管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('notification.index') ? 'active' : '' }}" href="{{ route('pm.notification.index') }}">
                                <i class="fa fa-bell me-2"></i> 通知中心
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('pm.profile.wecom.*') ? 'active' : '' }}" href="{{ route('pm.profile.wecom.index') }}">
                                <i class="fab fa-weixin me-2"></i> 企业微信绑定
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('pm.wecom.file.*') ? 'active' : '' }}" href="{{ route('pm.wecom.file.index') }}">
                                <i class="fa fa-inbox me-2"></i> 企微文件收件箱
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('knowledge.*') ? 'active' : '' }}" href="{{ route('pm.knowledge.index') }}">
                                <i class="fa fa-book me-2"></i> 知识库
                            </a>
                        </li>
                        @endif
                    </ul>
