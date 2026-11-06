<?php

return [
    'darkMode'        => '啟用暗模式',
    'lightMode'       => '啟用亮模式',
    'localeName'      => '繁體中文',
    'accountSettings' => '帳號設定',
    'logout'          => '登出',

    'dashboard' => '儀表板',

    'users'                => '使用者管理',
    'usernameHint'         => '3–30 個字元，只能使用英文字母、數字和點。',
    'userSearch'           => '搜尋使用者名稱或電子郵件',
    'userSort'             => '排序欄位',
    'userCreated'          => '建立時間',
    'userDirection'        => '排序方向',
    'userDescending'       => '遞減',
    'userAscending'        => '遞增',
    'userFilter'           => '篩選',
    'userClear'            => '清除',
    'userTotal'            => '總數',
    'noUsersFound'         => '找不到使用者。',
    'userTemplate'         => 'CSV 範本',
    'exportUsers'          => '匯出 CSV',
    'importUsers'          => '匯入使用者',
    'userCsvFile'          => 'CSV 檔案',
    'userCsvHint'          => '標題為 username,email；最多 500 列、1 MB。已有電子郵件會跳過。匯入不設定已知密碼，也不綁定微軟身分。',
    'invalidUserCsv'       => '請上傳有效的 CSV 檔案（不超過 1 MB、500 列，標題為 username,email）。',
    'importFinished'       => '匯入已完成，請查看下方逐列結果。',
    'exportLimit'          => '匯出使用者過多，請篩選至不超過 10000 人。',
    'userImportReport'     => '匯入結果',
    'userRow'              => '列號',
    'userResult'           => '結果',
    'userReason'           => '原因',
    'userResult_created'   => '已建立',
    'userResult_skipped'   => '已跳過',
    'userResult_error'     => '失敗',
    'userReason_duplicate' => '電子郵件已存在。',
    'userReason_invalid'   => '使用者名稱或電子郵件無效。',
    'userReason_username'  => '使用者名稱已存在。',
    'userReason_save'      => '無法建立使用者。',
    'editUser'             => '編輯',
    'userActions'          => '操作',
    'userSelf'             => '目前帳號',
    'userProtected'        => '受保護帳號',
    'userRole'             => '角色',
    'userPermissions'      => '有效權限',
    'viewUserPermissions'  => '查看權限',
    'userPermissionsHint'  => '僅顯示目前權限目錄中生效的權限，包括使用者直接授權與角色授權。',
    'userPermissionCount'  => '共 %d 項',
    'close'                => '關閉',
    'noCatalogPermissions' => '無目錄權限',
    'userRoleUser'         => '一般使用者（無後台權限）',
    'userRoleAdmin'        => '管理員',
    'userStatus'           => '狀態',
    'userEnabled'          => '正常',
    'userBanned'           => '已封禁',
    'userSessionHint'      => '修改角色或封禁帳號可能不會立即結束已建立的工作階段。',
    'userSaved'            => '使用者資料已更新。',
    'userUpdateFailed'     => '無法更新使用者。',
    'userBack'             => '返回使用者清單',
    'createUser'           => '建立使用者',
    'userCreatedSuccess'   => '使用者已建立。',
    'userInvite'           => '寄送邀請',
    'userInviteStatus'     => '邀請狀態',
    'userInviteNotSent'    => '未寄送',
    'userInviteSubject'    => '帳戶邀請',
    'userInviteBody'       => <<<'HTML'
        <p>您好，{username}：</p>
        <p>您已收到邀請。請使用此電子郵件申請登入連結，連結自申請後開始計時。</p>
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-radius: 6px; border-collapse: separate !important;">
            <tbody>
                <tr>
                    <td style="line-height: 24px; font-size: 16px; border-radius: 6px; margin: 0;" align="center" bgcolor="#0d6efd">
                        <a href="{link}" style="color: #ffffff; font-size: 16px; font-family: Helvetica, Arial, sans-serif; text-decoration: none; border-radius: 6px; line-height: 20px; display: inline-block; font-weight: normal; white-space: nowrap; background-color: #0d6efd; padding: 8px 12px; border: 1px solid #0d6efd;">申請登入連結</a>
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
    'userInviteMicrosoftBody' => '<p>您也可以使用工作或學校帳號<a href="{microsoftLink}">透過微軟登入</a>；未綁定的帳號須先獲審核或完成綁定，才能存取。</p>',
    'userInviteQueued'        => '邀請郵件已入列，可在使用者清單查看寄送狀態。',
    'userInviteFailed'        => '邀請郵件入列失敗，請重試。',
    'inviteUnavailable'       => '無法寄送邀請，請檢查寄件信箱、連結登入設定及使用者狀態。',
    'userProvisionHint'       => '新使用者加入一般使用者群組，沒有已知密碼，也不會自動綁定微軟身分。後台權限需另行授予。',

    'systemSettings'         => '系統設定',
    'roleSettings'           => '角色與權限',
    'rolePermissions'        => '角色權限',
    'permissionCatalog'      => '權限目錄',
    'permissionDomainLabels' => [
        'admin'              => '後台管理',
        'users'              => '使用者管理',
        'beta'               => '測試功能',
        'announcements'      => '公告',
        'email-settings'     => '郵件寄送設定',
        'email-deliveries'   => '郵件記錄',
        'queue-jobs'         => '佇列工作',
        'email-templates'    => '郵件範本',
        'operation-audit'    => '操作稽核',
        'microsoft-settings' => '微軟登入設定',
    ],
    'superadminPermissionsHint'   => '目前權限目錄中實際生效的權限；此處不可修改超級管理員授權。',
    'effectivePermissionCount'    => '已授權 %d / %d 項目錄權限',
    'permissionGrantedBy'         => '授權來源',
    'rawRoleGrants'               => '原始授權',
    'noEffectivePermissions'      => '目前沒有已授予的目錄權限。',
    'saveRolePermissions'         => '儲存權限',
    'rolePermissionsSaved'        => '角色權限已儲存。',
    'invalidRolePermissions'      => '只能選擇現有權限。',
    'wildcardPermissionConflict'  => '取消單項權限前，請先取消覆蓋該權限的萬用字元授權。',
    'extraRoleGrant'              => '權限目錄外的現有授權',
    'createRole'                  => '新增角色',
    'editRole'                    => '編輯角色',
    'saveRole'                    => '儲存角色',
    'roleUpdated'                 => '角色已更新。',
    'roleKeyImmutable'            => '角色識別碼可能已用於程式碼中的授權檢查，不能重新命名。需要其他識別碼時請新增角色。',
    'roleKey'                     => '角色識別碼',
    'roleTitle'                   => '顯示名稱',
    'roleDescription'             => '角色描述',
    'invalidRoleName'             => '請輸入未使用的小寫角色識別碼，只能包含字母、數字和連字號。',
    'roleCreated'                 => '角色已建立。',
    'createPermission'            => '新增權限',
    'editPermission'              => '編輯權限',
    'savePermission'              => '儲存權限',
    'permissionUpdated'           => '權限已更新。',
    'permissionKeyImmutable'      => '權限識別碼可能已用於程式碼中的授權檢查，不能重新命名。需要其他識別碼時請新增權限。',
    'cancel'                      => '取消',
    'permissionKey'               => '權限識別碼',
    'permissionKeyHint'           => '格式為 domain.ability，例如 reports.view。每段以小寫字母開頭，後續只能使用小寫字母、數字或連字號；最多 80 個字元，不支援萬用字元。',
    'permissionDescription'       => '描述',
    'invalidPermissionName'       => '請輸入未使用的 domain.ability 格式權限識別碼，只能包含小寫字母、數字和連字號。',
    'permissionCreated'           => '權限已建立。',
    'microsoftLogin'              => '微軟登入',
    'microsoftEnabled'            => '啟用微軟登入',
    'microsoftEnabledHint'        => '允許已綁定帳號登入，其他組織帳號可提交審核申請。',
    'microsoftAppRegistration'    => 'Microsoft Entra 應用程式註冊',
    'microsoftTenant'             => '租用戶',
    'microsoftTenantHint'         => '填寫 organizations 可允許任何組織的工作或學校帳號登入；填寫租用戶 ID 則僅允許該組織。不支援個人微軟帳號。',
    'microsoftClientId'           => '應用程式（用戶端）ID',
    'microsoftClientIdHint'       => '填寫 Microsoft Entra 中的應用程式 ID。',
    'microsoftSecretHint'         => '用戶端密鑰請在伺服器環境中設定（microsoftoauth.clientSecret），不會儲存在此處。',
    'microsoftPendingHint'        => '請在 Entra 應用程式中登記 /en/microsoft/callback 回呼網址；僅已綁定或獲核准的帳號可存取後台。',
    'saveMicrosoftSettings'       => '儲存微軟登入設定',
    'microsoftSettingsSaved'      => '微軟登入設定已儲存。',
    'microsoftConnect'            => '綁定微軟帳號',
    'microsoftLinked'             => '已綁定微軟帳號',
    'microsoftPasswordInvalid'    => '目前密碼不正確。',
    'microsoftLoginFailed'        => '無法完成微軟登入。',
    'microsoftApprovalPending'    => '存取申請已提交，請聯繫管理員審核。',
    'microsoftPendingRequests'    => '微軟帳號存取申請',
    'microsoftIdentity'           => '微軟身分（郵箱未經核實）',
    'microsoftTargetUser'         => '現有後台帳號',
    'microsoftApprove'            => '核准',
    'microsoftConfirmApproval'    => '核准前請確認該微軟身分確實屬於所選後台帳號。',
    'microsoftApprovalFailed'     => '無法核准此申請。',
    'microsoftApproved'           => '已核准並綁定微軟帳號。',
    'microsoftReject'             => '拒絕',
    'microsoftRejected'           => '已拒絕申請。',
    'microsoftRevoke'             => '撤銷綁定',
    'microsoftRevoked'            => '已撤銷微軟登入權限。現有登入工作階段仍有效。',
    'microsoftConfirmRevoke'      => '撤銷此帳號未來的微軟登入權限？現有登入工作階段仍有效。',
    'microsoftConnectedAccounts'  => '已綁定的微軟帳號',
    'microsoftRequestUnavailable' => '無法提交存取申請，請聯繫管理員。',
    'emailDelivery'               => '郵件發送',
    'mail'                        => '郵件',
    'mailDeliveries'              => '發送紀錄',
    'mailTemplates'               => '郵件範本',
    'mailInvitation'              => '使用者邀請',
    'mailMagicLink'               => '登入連結',
    'mailActivation'              => '帳戶啟用',
    'mailEmail2fa'                => '電子郵件驗證碼',
    'mailTemplate_magic_linkBody' => <<<'HTML'
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-radius: 6px; border-collapse: separate !important;">
            <tbody>
                <tr>
                    <td style="line-height: 24px; font-size: 16px; border-radius: 6px; margin: 0;" align="center" bgcolor="#0d6efd">
                        <a href="{link}" style="color: #ffffff; font-size: 16px; font-family: Helvetica, Arial, sans-serif; text-decoration: none; border-radius: 6px; line-height: 20px; display: inline-block; font-weight: normal; white-space: nowrap; background-color: #0d6efd; padding: 8px 12px; border: 1px solid #0d6efd;">登入</a>
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
        <b>請求者資訊：</b>
        <p>使用者名稱：{username}</p>
        <p>IP 位址：{ipAddress}</p>
        <p>裝置：{userAgent}</p>
        <p>時間：{date}</p>
        HTML,
    'mailTemplate_activationBody' => <<<'HTML'
        <p>您的啟用碼：</p>
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
        <b>請求者資訊：</b>
        <p>使用者名稱：{username}</p>
        <p>IP 位址：{ipAddress}</p>
        <p>裝置：{userAgent}</p>
        <p>時間：{date}</p>
        HTML,
    'mailTemplate_email_2faBody' => <<<'HTML'
        <p>您的驗證碼：</p>
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
        <b>請求者資訊：</b>
        <p>使用者名稱：{username}</p>
        <p>IP 位址：{ipAddress}</p>
        <p>裝置：{userAgent}</p>
        <p>時間：{date}</p>
        HTML,
    'mailTemplateSaved'      => '郵件範本已儲存。',
    'mailTemplateReset'      => '郵件範本已還原預設。',
    'mailTemplateInvalid'    => '僅可使用列出的佔位符，正文須保留連結或驗證碼，主旨不可包含連結或驗證碼。',
    'mailTemplateVariables'  => '可用佔位符',
    'mailSubjectTokens'      => '主旨可用佔位符',
    'mailTemplateRestore'    => '還原預設',
    'mailTemplateSave'       => '儲存範本',
    'mailHtmlBody'           => 'HTML 正文',
    'mailPreview'            => '預覽',
    'emailQueue'             => '郵件佇列與發送稽核',
    'mailAudit'              => '發送紀錄',
    'operationAudit'         => '操作稽核',
    'auditActor'             => '操作者',
    'auditTarget'            => '對象',
    'auditAllActors'         => '全部操作者',
    'auditAllObjects'        => '全部對象',
    'auditDeletedUser'       => '已刪除使用者',
    'auditBrowser'           => '瀏覽器',
    'auditType'              => '對象類型',
    'auditAction'            => '操作',
    'auditSubmission'        => '提交內容',
    'auditOmitted'           => '已省略',
    'auditTruncated'         => '已截斷',
    'auditInvalidInput'      => 'JSON 輸入無效',
    'auditLimited'           => '已達到採集上限',
    'auditPath'              => '路徑',
    'auditResult'            => '結果',
    'auditFrom'              => '起始日期',
    'auditTo'                => '結束日期',
    'audit_success'          => '成功',
    'audit_failed'           => '失敗',
    'audit_redirected'       => '已跳轉（結果未知）',
    'mailQueueJobs'          => '佇列工作',
    'queueJobs'              => '佇列工作',
    'queueJobsDescription'   => '此處顯示目前佇列工作，並非永久執行歷史。工作完成後可能被移除，業務結果請查看對應業務紀錄。',
    'queueJobsUnsupported'   => '目前設定的佇列後端不支援此檢視。',
    'queueName'              => '佇列名稱',
    'queueAll'               => '全部佇列',
    'queueStatus_pending'    => '待處理',
    'queueStatus_reserved'   => '已保留',
    'queueStatus_done'       => '已完成',
    'mailRecipient'          => '收件人',
    'mailSubject'            => '主旨',
    'mailStatus'             => '狀態',
    'mailAllStatuses'        => '全部狀態',
    'mailStatus_queued'      => '已入列',
    'mailStatus_sent'        => '已發送',
    'mailStatus_failed'      => '發送失敗',
    'mailJob_pending'        => '待處理',
    'mailJob_reserved'       => '處理中',
    'mailJob_unknown'        => '未知狀態',
    'mailAttempts'           => '嘗試次數',
    'mailCreated'            => '建立時間',
    'mailProcessed'          => '處理時間',
    'mailAvailable'          => '可執行時間',
    'mailFailureReason'      => '失敗原因',
    'mailNoRecords'          => '暫無紀錄。',
    'senderEmail'            => '寄件信箱',
    'senderName'             => '寄件人名稱',
    'mailProtocol'           => '發送協定',
    'smtpSettings'           => 'SMTP 伺服器',
    'smtpHost'               => 'SMTP 主機',
    'smtpUser'               => 'SMTP 使用者名稱',
    'smtpPort'               => 'SMTP 連接埠',
    'smtpCrypto'             => 'SMTP 加密',
    'smtpNone'               => '無',
    'smtpTls'                => 'STARTTLS',
    'smtpSsl'                => 'SSL',
    'smtpPasswordHint'       => 'SMTP 密碼請在伺服器環境中設定（email.SMTPPass），不會儲存在此處。',
    'smtpHostRequired'       => '使用 SMTP 時請輸入 SMTP 主機。',
    'saveEmailSettings'      => '儲存郵件設定',
    'emailSettingsSaved'     => '郵件設定已儲存。',
    'sendTestEmail'          => '發送測試郵件',
    'sendingTestEmail'       => '正在發送測試郵件',
    'testRecipient'          => '收件信箱',
    'testEmailSubject'       => 'GeminusAdmin 測試郵件',
    'testEmailBody'          => '這是一封來自 GeminusAdmin 的測試郵件。',
    'testEmailNotConfigured' => '請先儲存寄件信箱，再發送測試郵件。',
    'testEmailSent'          => '測試郵件已發送。',
    'testEmailFailed'        => '測試郵件發送失敗，請檢查郵件伺服器設定。',

    'profileDetails'              => '個人資料',
    'username'                    => '使用者名稱',
    'email'                       => '電子郵件',
    'language'                    => '語言',
    'toggleNavigation'            => '展開或收起導覽',
    'timezone'                    => '時區',
    'saveProfile'                 => '儲存資料',
    'profileSaved'                => '個人資料已更新。',
    'usernameTaken'               => '此使用者名稱已被使用。',
    'invalidPreference'           => '請選擇支援的語言和時區。',
    'avatar'                      => '頭像',
    'chooseAvatar'                => '選擇圖片',
    'avatarHint'                  => '支援 JPEG、PNG 或 WebP，檔案不超過 2 MB。',
    'uploadAvatar'                => '上傳頭像',
    'removeAvatar'                => '移除頭像',
    'avatarSaved'                 => '頭像已更新。',
    'attachments'                 => '附件',
    'uploadHistory'               => '上傳記錄',
    'uploadHistoryEmpty'          => '暫無可查看的上傳記錄。',
    'attachmentSource'            => '所屬業務',
    'attachmentRecord'            => '關聯記錄',
    'attachmentUploadedAt'        => '上傳時間',
    'createdFrom'                 => '建立開始日期（UTC）',
    'createdTo'                   => '建立結束日期（UTC）',
    'attachmentFile'              => '檔案',
    'attachmentSize'              => '大小',
    'attachmentUpload'            => '上傳',
    'attachmentDownload'          => '下載',
    'attachmentPreview'           => '預覽',
    'attachmentOpen'              => '在新分頁開啟',
    'attachmentLoading'           => '載入中...',
    'attachmentPreviewFailed'     => '無法顯示附件。',
    'attachmentRemove'            => '移除',
    'attachmentsEmpty'            => '暫無附件。',
    'attachmentHint'              => 'PDF、TXT、CSV、JPEG、PNG 或 WebP，最大 {0} MB。',
    'attachmentInvalid'           => '請選擇允許格式且不超過 {0} MB 的檔案。',
    'attachmentSaved'             => '附件已上傳。',
    'attachmentRemoved'           => '附件已移除。',
    'attachmentFailed'            => '附件更新失敗，請重試。',
    'avatarRemoved'               => '頭像已移除。',
    'avatarFailed'                => '頭像更新失敗，請重試。',
    'changePassword'              => '修改密碼',
    'currentPassword'             => '目前密碼',
    'newPassword'                 => '新密碼',
    'confirmPassword'             => '確認新密碼',
    'passwordHint'                => '至少 {0} 個字元，最多 255 個字元；不要包含使用者名稱、電子郵件資訊，也不要使用常見密碼。',
    'savePassword'                => '更新密碼',
    'passwordSaved'               => '密碼已更新。',
    'incorrectPassword'           => '目前密碼不正確。',
    'localOnly'                   => '只有本機帳號可以修改密碼。',
    'apiTokens'                   => 'API 金鑰',
    'tokenName'                   => '金鑰名稱',
    'tokenPermissions'            => '金鑰權限',
    'tokenPermissionInvalid'      => '請至少選擇一項已登記且帳號目前擁有的權限。',
    'tokenNoPermissions'          => '暫無可選權限。',
    'tokenPermissionActive'       => '有效',
    'tokenPermissionDenied'       => '帳號未授權',
    'tokenPermissionUnregistered' => '權限未登記',
    'tokenInvalid'                => '金鑰失效',
    'tokenLegacy'                 => '萬用字元金鑰：不可用於 API',
    'tokenExpires'                => '有效期限（UTC）',
    'lastUsed'                    => '最後使用',
    'createToken'                 => '建立金鑰',
    'revokeToken'                 => '撤銷',
    'confirmRevoke'               => '確定撤銷此金鑰嗎？',
    'tokenOnce'                   => '請立即保存此金鑰，之後將無法再次查看。',
    'noTokens'                    => '尚無 API 金鑰。',
    'futureExpiry'                => '請選擇未來的有效期限。',
    'tokenNotFound'               => '找不到此金鑰。',
    'tokenRevoked'                => '金鑰已撤銷。',

    'error404'        => '未找到',
    'error404Title'   => '哎呀… 您剛剛發現了一個錯誤頁面',
    'error404Message' => '很抱歉，您正在尋找的頁面未找到',
    'takeMeHome'      => '帶我回首頁',
];
