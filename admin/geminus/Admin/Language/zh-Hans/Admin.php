<?php

return [
    'darkMode'        => '启用暗模式',
    'lightMode'       => '启用亮模式',
    'localeName'      => '简体中文',
    'accountSettings' => '账号设置',
    'logout'          => '退出登录',

    'dashboard' => '仪表盘',

    'users'                => '用户管理',
    'usernameHint'         => '3–30 个字符，只能使用英文字母、数字和点。',
    'userSearch'           => '搜索用户名或邮箱',
    'userSort'             => '排序字段',
    'userCreated'          => '创建时间',
    'userDirection'        => '排序方向',
    'userDescending'       => '降序',
    'userAscending'        => '升序',
    'userFilter'           => '筛选',
    'userClear'            => '清除',
    'userTotal'            => '总数',
    'noUsersFound'         => '没有找到用户。',
    'userTemplate'         => 'CSV 模板',
    'exportUsers'          => '导出 CSV',
    'importUsers'          => '导入用户',
    'userCsvFile'          => 'CSV 文件',
    'userCsvHint'          => '表头为 username,email；最多 500 行、1 MB。已有邮箱跳过。导入不会设置已知密码，也不会绑定微软身份。',
    'invalidUserCsv'       => '请上传有效的 CSV 文件（不超过 1 MB、500 行，表头为 username,email）。',
    'importFinished'       => '导入已完成，请查看下方逐行结果。',
    'exportLimit'          => '导出用户过多，请筛选到不超过 10000 人。',
    'userImportReport'     => '导入结果',
    'userRow'              => '行号',
    'userResult'           => '结果',
    'userReason'           => '原因',
    'userResult_created'   => '已创建',
    'userResult_skipped'   => '已跳过',
    'userResult_error'     => '失败',
    'userReason_duplicate' => '邮箱已存在。',
    'userReason_invalid'   => '用户名或邮箱无效。',
    'userReason_username'  => '用户名已存在。',
    'userReason_save'      => '无法创建用户。',
    'editUser'             => '编辑',
    'userActions'          => '操作',
    'userSelf'             => '当前账号',
    'userProtected'        => '受保护账号',
    'userRole'             => '角色',
    'userPermissions'      => '有效权限',
    'viewUserPermissions'  => '查看权限',
    'userPermissionsHint'  => '仅显示当前权限目录中生效的权限，包括用户直接授权和角色授权。',
    'userPermissionCount'  => '共 %d 项',
    'close'                => '关闭',
    'noCatalogPermissions' => '无目录权限',
    'userRoleUser'         => '普通用户（无后台权限）',
    'userRoleAdmin'        => '管理员',
    'userStatus'           => '状态',
    'userEnabled'          => '正常',
    'userBanned'           => '已封禁',
    'userSessionHint'      => '修改角色或封禁账号可能不会立即结束已建立的会话。',
    'userSaved'            => '用户信息已更新。',
    'userUpdateFailed'     => '无法更新用户。',
    'userBack'             => '返回用户列表',
    'createUser'           => '创建用户',
    'userCreatedSuccess'   => '用户已创建。',
    'userInvite'           => '发送邀请',
    'userInviteStatus'     => '邀请状态',
    'userInviteNotSent'    => '未发送',
    'userInviteSubject'    => '账户邀请',
    'userInviteBody'       => <<<'HTML'
        <p>您好，{username}：</p>
        <p>您已收到邀请。请使用此邮箱申请登录链接，链接自申请后开始计时。</p>
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-radius: 6px; border-collapse: separate !important;">
            <tbody>
                <tr>
                    <td style="line-height: 24px; font-size: 16px; border-radius: 6px; margin: 0;" align="center" bgcolor="#0d6efd">
                        <a href="{link}" style="color: #ffffff; font-size: 16px; font-family: Helvetica, Arial, sans-serif; text-decoration: none; border-radius: 6px; line-height: 20px; display: inline-block; font-weight: normal; white-space: nowrap; background-color: #0d6efd; padding: 8px 12px; border: 1px solid #0d6efd;">申请登录链接</a>
                    </td>
                </tr>
            </tbody>
        </table>
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="width: 100%;" width="100%">
            <tbody>
                <tr>
                    <td style="line-height: 20px; font-size: 20px; width: 100%; height: 20px; margin: 0;" align="left" width="100%" height="20">&#160;</td>
                </tr>
            </tbody>
        </table>
        {microsoftLogin}
        HTML,
    'userInviteMicrosoftBody' => '<p>您也可以使用工作或学校账号<a href="{microsoftLink}">通过微软登录</a>；未绑定的账号须先获审批或完成绑定，才能访问。</p>',
    'userInviteQueued'        => '邀请邮件已入队，可在用户列表查看发送状态。',
    'userInviteFailed'        => '邀请邮件入队失败，请重试。',
    'inviteUnavailable'       => '无法发送邀请，请检查发件邮箱、链接登录设置及用户状态。',
    'userProvisionHint'       => '新用户进入普通用户组，没有可知的密码，也不会自动绑定微软身份。后台权限需另行授予。',

    'systemSettings'         => '系统设置',
    'roleSettings'           => '角色与权限',
    'rolePermissions'        => '角色权限',
    'permissionCatalog'      => '权限目录',
    'permissionDomainLabels' => [
        'admin'              => '后台管理',
        'users'              => '用户管理',
        'beta'               => '测试功能',
        'announcements'      => '公告',
        'email-settings'     => '邮件发送设置',
        'email-deliveries'   => '邮件记录',
        'queue-jobs'         => '队列任务',
        'email-templates'    => '邮件模板',
        'operation-audit'    => '操作审计',
        'microsoft-settings' => '微软登录设置',
    ],
    'superadminPermissionsHint'   => '当前权限目录中实际生效的权限；此处不可修改超级管理员授权。',
    'effectivePermissionCount'    => '已授权 %d / %d 项目录权限',
    'permissionGrantedBy'         => '授权来源',
    'rawRoleGrants'               => '原始授权',
    'noEffectivePermissions'      => '当前没有已授予的目录权限。',
    'saveRolePermissions'         => '保存权限',
    'rolePermissionsSaved'        => '角色权限已保存。',
    'invalidRolePermissions'      => '只能选择已有权限。',
    'wildcardPermissionConflict'  => '取消单项权限前，请先取消覆盖该权限的通配符授权。',
    'extraRoleGrant'              => '权限目录外的已有授权',
    'createRole'                  => '新建角色',
    'editRole'                    => '编辑角色',
    'saveRole'                    => '保存角色',
    'roleUpdated'                 => '角色已更新。',
    'roleKeyImmutable'            => '角色标识可能已用于代码中的授权检查，不能重命名。需要其他标识时请新建角色。',
    'roleKey'                     => '角色标识',
    'roleTitle'                   => '显示名称',
    'roleDescription'             => '角色描述',
    'invalidRoleName'             => '请输入未使用的小写角色标识，仅可包含字母、数字和连字符。',
    'roleCreated'                 => '角色已创建。',
    'createPermission'            => '新建权限',
    'editPermission'              => '编辑权限',
    'savePermission'              => '保存权限',
    'permissionUpdated'           => '权限已更新。',
    'permissionKeyImmutable'      => '权限标识可能已用于代码中的授权检查，不能重命名。需要其他标识时请新建权限。',
    'cancel'                      => '取消',
    'permissionKey'               => '权限标识',
    'permissionKeyHint'           => '格式为 domain.ability，例如 reports.view。每段以小写字母开头，后续只能使用小写字母、数字或连字符；最多 80 个字符，不支持通配符。',
    'permissionDescription'       => '描述',
    'invalidPermissionName'       => '请输入未使用的 domain.ability 格式权限标识，仅可包含小写字母、数字和连字符。',
    'permissionCreated'           => '权限已创建。',
    'microsoftLogin'              => '微软登录',
    'microsoftEnabled'            => '启用微软登录',
    'microsoftEnabledHint'        => '允许已绑定账号登录，其他组织账号可提交审批申请。',
    'microsoftAppRegistration'    => 'Microsoft Entra 应用注册',
    'microsoftTenant'             => '租户',
    'microsoftTenantHint'         => '填写 organizations 可允许任意组织的工作或学校账号登录；填写租户 ID 则仅允许该组织。不支持个人微软账号。',
    'microsoftClientId'           => '应用程序（客户端）ID',
    'microsoftClientIdHint'       => '填写 Microsoft Entra 中的应用程序 ID。',
    'microsoftSecretHint'         => '客户端密钥请在服务器环境中设置（microsoftoauth.clientSecret），不会保存在此处。',
    'microsoftPendingHint'        => '请在 Entra 应用中登记 /en/microsoft/callback 回调地址；仅已绑定或获批准的账号可访问后台。',
    'saveMicrosoftSettings'       => '保存微软登录设置',
    'microsoftSettingsSaved'      => '微软登录设置已保存。',
    'microsoftConnect'            => '绑定微软账号',
    'microsoftLinked'             => '已绑定微软账号',
    'microsoftPasswordInvalid'    => '当前密码不正确。',
    'microsoftLoginFailed'        => '无法完成微软登录。',
    'microsoftApprovalPending'    => '访问申请已提交，请联系管理员审批。',
    'microsoftPendingRequests'    => '微软账号访问申请',
    'microsoftIdentity'           => '微软身份（邮箱未经核实）',
    'microsoftTargetUser'         => '现有后台账号',
    'microsoftApprove'            => '批准',
    'microsoftConfirmApproval'    => '批准前请确认该微软身份确实属于所选后台账号。',
    'microsoftApprovalFailed'     => '无法批准此申请。',
    'microsoftApproved'           => '已批准并绑定微软账号。',
    'microsoftReject'             => '拒绝',
    'microsoftRejected'           => '已拒绝申请。',
    'microsoftRevoke'             => '撤销绑定',
    'microsoftRevoked'            => '已撤销微软登录权限。已有登录会话仍有效。',
    'microsoftConfirmRevoke'      => '撤销此账号未来的微软登录权限？已有登录会话仍有效。',
    'microsoftConnectedAccounts'  => '已绑定的微软账号',
    'microsoftRequestUnavailable' => '无法提交访问申请，请联系管理员。',
    'emailDelivery'               => '邮件发送',
    'mail'                        => '邮件',
    'mailDeliveries'              => '发送记录',
    'mailTemplates'               => '邮件模板',
    'mailInvitation'              => '用户邀请',
    'mailMagicLink'               => '登录链接',
    'mailActivation'              => '账户激活',
    'mailEmail2fa'                => '邮箱验证码',
    'mailTemplate_magic_linkBody' => <<<'HTML'
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-radius: 6px; border-collapse: separate !important;">
            <tbody>
                <tr>
                    <td style="line-height: 24px; font-size: 16px; border-radius: 6px; margin: 0;" align="center" bgcolor="#0d6efd">
                        <a href="{link}" style="color: #ffffff; font-size: 16px; font-family: Helvetica, Arial, sans-serif; text-decoration: none; border-radius: 6px; line-height: 20px; display: inline-block; font-weight: normal; white-space: nowrap; background-color: #0d6efd; padding: 8px 12px; border: 1px solid #0d6efd;">登录</a>
                    </td>
                </tr>
            </tbody>
        </table>
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="width: 100%;" width="100%">
            <tbody>
                <tr>
                    <td style="line-height: 20px; font-size: 20px; width: 100%; height: 20px; margin: 0;" align="left" width="100%" height="20">&#160;</td>
                </tr>
            </tbody>
        </table>
        <b>请求者信息：</b>
        <p>用户名：{username}</p>
        <p>IP 地址：{ipAddress}</p>
        <p>设备：{userAgent}</p>
        <p>时间：{date}</p>
        HTML,
    'mailTemplate_activationBody' => <<<'HTML'
        <p>您的激活码：</p>
        <div style="text-align: center">
            <h1>{code}</h1>
        </div>
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="width: 100%;" width="100%">
            <tbody>
                <tr>
                    <td style="line-height: 20px; font-size: 20px; width: 100%; height: 20px; margin: 0;" align="left" width="100%" height="20">&#160;</td>
                </tr>
            </tbody>
        </table>
        <b>请求者信息：</b>
        <p>用户名：{username}</p>
        <p>IP 地址：{ipAddress}</p>
        <p>设备：{userAgent}</p>
        <p>时间：{date}</p>
        HTML,
    'mailTemplate_email_2faBody' => <<<'HTML'
        <p>您的验证码：</p>
        <div style="text-align: center">
            <h1>{code}</h1>
        </div>
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="width: 100%;" width="100%">
            <tbody>
                <tr>
                    <td style="line-height: 20px; font-size: 20px; width: 100%; height: 20px; margin: 0;" align="left" width="100%" height="20">&#160;</td>
                </tr>
            </tbody>
        </table>
        <b>请求者信息：</b>
        <p>用户名：{username}</p>
        <p>IP 地址：{ipAddress}</p>
        <p>设备：{userAgent}</p>
        <p>时间：{date}</p>
        HTML,
    'mailTemplateSaved'      => '邮件模板已保存。',
    'mailTemplateReset'      => '邮件模板已恢复默认。',
    'mailTemplateInvalid'    => '仅可使用列出的占位符，正文须保留链接或验证码，主题不可包含链接或验证码。',
    'mailTemplateVariables'  => '可用占位符',
    'mailSubjectTokens'      => '主题可用占位符',
    'mailTemplateRestore'    => '恢复默认',
    'mailTemplateSave'       => '保存模板',
    'mailHtmlBody'           => 'HTML 正文',
    'mailPreview'            => '预览',
    'emailQueue'             => '邮件队列与发送审计',
    'mailAudit'              => '发送日志',
    'operationAudit'         => '操作审计',
    'auditActor'             => '操作者',
    'auditTarget'            => '对象',
    'auditAllActors'         => '全部操作者',
    'auditAllObjects'        => '全部对象',
    'auditDeletedUser'       => '已删除用户',
    'auditBrowser'           => '浏览器',
    'auditType'              => '对象类型',
    'auditAction'            => '操作',
    'auditSubmission'        => '提交内容',
    'auditOmitted'           => '已省略',
    'auditTruncated'         => '已截断',
    'auditInvalidInput'      => 'JSON 输入无效',
    'auditLimited'           => '已达到采集上限',
    'auditPath'              => '路径',
    'auditResult'            => '结果',
    'auditFrom'              => '起始日期',
    'auditTo'                => '结束日期',
    'audit_success'          => '成功',
    'audit_failed'           => '失败',
    'audit_redirected'       => '已跳转（结果未知）',
    'mailQueueJobs'          => '队列任务',
    'queueJobs'              => '队列任务',
    'queueJobsDescription'   => '这里显示当前队列任务，并非永久执行历史。任务完成后可能被移除，业务结果请查看对应业务记录。',
    'queueJobsUnsupported'   => '当前配置的队列后端不支持此视图。',
    'queueName'              => '队列名称',
    'queueAll'               => '全部队列',
    'queueStatus_pending'    => '待处理',
    'queueStatus_reserved'   => '已保留',
    'queueStatus_done'       => '已完成',
    'mailRecipient'          => '收件人',
    'mailSubject'            => '主题',
    'mailStatus'             => '状态',
    'mailAllStatuses'        => '全部状态',
    'mailStatus_queued'      => '已入队',
    'mailStatus_sent'        => '已发送',
    'mailStatus_failed'      => '发送失败',
    'mailJob_pending'        => '待处理',
    'mailJob_reserved'       => '处理中',
    'mailJob_unknown'        => '未知状态',
    'mailAttempts'           => '尝试次数',
    'mailCreated'            => '创建时间',
    'mailProcessed'          => '处理时间',
    'mailAvailable'          => '可执行时间',
    'mailFailureReason'      => '失败原因',
    'mailNoRecords'          => '暂无记录。',
    'senderEmail'            => '发件邮箱',
    'senderName'             => '发件人名称',
    'mailProtocol'           => '发送协议',
    'smtpSettings'           => 'SMTP 服务器',
    'smtpHost'               => 'SMTP 主机',
    'smtpUser'               => 'SMTP 用户名',
    'smtpPort'               => 'SMTP 端口',
    'smtpCrypto'             => 'SMTP 加密',
    'smtpNone'               => '无',
    'smtpTls'                => 'STARTTLS',
    'smtpSsl'                => 'SSL',
    'smtpPasswordHint'       => 'SMTP 密码请在服务器环境中设置（email.SMTPPass），不会保存在此处。',
    'smtpHostRequired'       => '使用 SMTP 时请输入 SMTP 主机。',
    'saveEmailSettings'      => '保存邮件设置',
    'emailSettingsSaved'     => '邮件设置已保存。',
    'sendTestEmail'          => '发送测试邮件',
    'sendingTestEmail'       => '正在发送测试邮件',
    'testRecipient'          => '收件邮箱',
    'testEmailSubject'       => 'GeminusAdmin 测试邮件',
    'testEmailBody'          => '这是一封来自 GeminusAdmin 的测试邮件。',
    'testEmailNotConfigured' => '请先保存发件邮箱，再发送测试邮件。',
    'testEmailSent'          => '测试邮件已发送。',
    'testEmailFailed'        => '测试邮件发送失败，请检查邮件服务器设置。',

    'profileDetails'              => '个人资料',
    'username'                    => '用户名',
    'email'                       => '邮箱',
    'language'                    => '语言',
    'toggleNavigation'            => '展开或收起导航',
    'timezone'                    => '时区',
    'saveProfile'                 => '保存资料',
    'profileSaved'                => '个人资料已更新。',
    'usernameTaken'               => '该用户名已被使用。',
    'invalidPreference'           => '请选择支持的语言和时区。',
    'avatar'                      => '头像',
    'chooseAvatar'                => '选择图片',
    'avatarHint'                  => '支持 JPEG、PNG 或 WebP，文件不超过 2 MB。',
    'uploadAvatar'                => '上传头像',
    'removeAvatar'                => '移除头像',
    'avatarSaved'                 => '头像已更新。',
    'attachments'                 => '附件',
    'uploadHistory'               => '上传记录',
    'uploadHistoryEmpty'          => '暂无可查看的上传记录。',
    'attachmentSource'            => '所属业务',
    'attachmentRecord'            => '关联记录',
    'attachmentUploadedAt'        => '上传时间',
    'createdFrom'                 => '创建开始日期（UTC）',
    'createdTo'                   => '创建结束日期（UTC）',
    'attachmentFile'              => '文件',
    'attachmentSize'              => '大小',
    'attachmentUpload'            => '上传',
    'attachmentDownload'          => '下载',
    'attachmentPreview'           => '预览',
    'attachmentOpen'              => '在新标签页打开',
    'attachmentLoading'           => '加载中...',
    'attachmentPreviewFailed'     => '无法显示附件。',
    'attachmentRemove'            => '移除',
    'attachmentsEmpty'            => '暂无附件。',
    'attachmentHint'              => 'PDF、TXT、CSV、JPEG、PNG 或 WebP，最大 {0} MB。',
    'attachmentInvalid'           => '请选择允许格式且不超过 {0} MB 的文件。',
    'attachmentSaved'             => '附件已上传。',
    'attachmentRemoved'           => '附件已移除。',
    'attachmentFailed'            => '附件更新失败，请重试。',
    'avatarRemoved'               => '头像已移除。',
    'avatarFailed'                => '头像更新失败，请重试。',
    'changePassword'              => '修改密码',
    'currentPassword'             => '当前密码',
    'newPassword'                 => '新密码',
    'confirmPassword'             => '确认新密码',
    'passwordHint'                => '至少 {0} 个字符，最多 255 个字符；不要包含用户名、邮箱信息，也不要使用常见密码。',
    'savePassword'                => '更新密码',
    'passwordSaved'               => '密码已更新。',
    'incorrectPassword'           => '当前密码不正确。',
    'localOnly'                   => '仅本地账号可以修改密码。',
    'apiTokens'                   => 'API 密钥',
    'tokenName'                   => '密钥名称',
    'tokenPermissions'            => '密钥权限',
    'tokenPermissionInvalid'      => '请至少选择一项已登记且账号当前拥有的权限。',
    'tokenNoPermissions'          => '暂无可选权限。',
    'tokenPermissionActive'       => '有效',
    'tokenPermissionDenied'       => '账号未授权',
    'tokenPermissionUnregistered' => '权限未登记',
    'tokenInvalid'                => '密钥失效',
    'tokenLegacy'                 => '通配符密钥：不可用于 API',
    'tokenExpires'                => '有效期至（UTC）',
    'lastUsed'                    => '最后使用',
    'createToken'                 => '创建密钥',
    'revokeToken'                 => '吊销',
    'confirmRevoke'               => '确定吊销此密钥吗？',
    'tokenOnce'                   => '请立即保存此密钥，之后将无法再次查看。',
    'noTokens'                    => '暂无 API 密钥。',
    'futureExpiry'                => '请选择未来的有效期。',
    'tokenNotFound'               => '找不到此密钥。',
    'tokenRevoked'                => '密钥已吊销。',

    'error404'        => '未找到',
    'error404Title'   => '哎呀… 您刚刚发现了一个错误页面',
    'error404Message' => '很抱歉，您正在寻找的页面未找到',
    'takeMeHome'      => '带我回首页',
];
