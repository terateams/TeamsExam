<?php

return [
    'darkMode'        => 'Enable Dark mode',
    'lightMode'       => 'Enable Light mode',
    'localeName'      => 'English',
    'accountSettings' => 'Account settings',
    'logout'          => 'Logout',

    'dashboard' => 'Dashboard',

    'users'                => 'Users',
    'usernameHint'         => '3-30 characters; English letters, numbers, and periods only.',
    'userSearch'           => 'Search username or email',
    'userSort'             => 'Sort by',
    'userCreated'          => 'Created',
    'userDirection'        => 'Order',
    'userDescending'       => 'Descending',
    'userAscending'        => 'Ascending',
    'userFilter'           => 'Filter',
    'userClear'            => 'Clear',
    'userTotal'            => 'Total',
    'noUsersFound'         => 'No users found.',
    'userTemplate'         => 'CSV template',
    'exportUsers'          => 'Export CSV',
    'importUsers'          => 'Import users',
    'userCsvFile'          => 'CSV file',
    'userCsvHint'          => 'Headers: username,email. Up to 500 rows and 1 MB. Existing emails are skipped. Imported users have no known password and are not linked to Microsoft.',
    'invalidUserCsv'       => 'Upload a valid CSV file (up to 1 MB, 500 rows, with username,email headers).',
    'importFinished'       => 'Import completed. Review the results below.',
    'exportLimit'          => 'Too many users to export. Narrow your search to 10,000 users or fewer.',
    'userImportReport'     => 'Import results',
    'userRow'              => 'Row',
    'userResult'           => 'Result',
    'userReason'           => 'Reason',
    'userResult_created'   => 'Created',
    'userResult_skipped'   => 'Skipped',
    'userResult_error'     => 'Failed',
    'userReason_duplicate' => 'Email already exists.',
    'userReason_invalid'   => 'Invalid username or email.',
    'userReason_username'  => 'Username already exists.',
    'userReason_save'      => 'Could not create user.',
    'editUser'             => 'Edit',
    'userActions'          => 'Actions',
    'userSelf'             => 'Your account',
    'userProtected'        => 'Protected account',
    'userRole'             => 'Role',
    'userPermissions'      => 'Effective permissions',
    'viewUserPermissions'  => 'View permissions',
    'userPermissionsHint'  => 'Shows effective permissions in the current catalog, including direct and role grants.',
    'userPermissionCount'  => 'Permissions: %d',
    'close'                => 'Close',
    'noCatalogPermissions' => 'No catalog permissions',
    'userRoleUser'         => 'User (no admin access)',
    'userRoleAdmin'        => 'Admin',
    'userStatus'           => 'Status',
    'userEnabled'          => 'Enabled',
    'userBanned'           => 'Banned',
    'userSessionHint'      => 'Changing a role or banning an account may not end an existing session.',
    'userSaved'            => 'User updated.',
    'userUpdateFailed'     => 'Could not update user.',
    'userBack'             => 'Back to users',
    'createUser'           => 'Create user',
    'userCreatedSuccess'   => 'User created.',
    'userInvite'           => 'Send invitation',
    'userInviteStatus'     => 'Invitation',
    'userInviteNotSent'    => 'Not sent',
    'userInviteSubject'    => 'Account invitation',
    'userInviteBody'       => <<<'HTML'
        <p>Hello {username},</p>
        <p>You have been invited. Request a sign-in link using this email address. The link expires after you request it.</p>
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-radius: 6px; border-collapse: separate !important;">
            <tbody>
                <tr>
                    <td style="line-height: 24px; font-size: 16px; border-radius: 6px; margin: 0;" align="center" bgcolor="#0d6efd">
                        <a href="{link}" style="color: #ffffff; font-size: 16px; font-family: Helvetica, Arial, sans-serif; text-decoration: none; border-radius: 6px; line-height: 20px; display: inline-block; font-weight: normal; white-space: nowrap; background-color: #0d6efd; padding: 8px 12px; border: 1px solid #0d6efd;">Request sign-in link</a>
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
    'userInviteMicrosoftBody' => '<p>You can also <a href="{microsoftLink}">sign in with Microsoft</a> using a work or school account. An unlinked account requires approval or linking before access is granted.</p>',
    'userInviteQueued'        => 'Invitation email queued. Check the status in the user list.',
    'userInviteFailed'        => 'Invitation email could not be queued. Please try again.',
    'inviteUnavailable'       => 'Invitation unavailable. Check the mail sender, sign-in link settings, and account status.',
    'userProvisionHint'       => 'New users join the user group, have no known password, and are not linked to Microsoft. Grant admin access separately.',

    'systemSettings'         => 'System settings',
    'roleSettings'           => 'Roles and permissions',
    'rolePermissions'        => 'Role permissions',
    'permissionCatalog'      => 'Permission catalog',
    'permissionDomainLabels' => [
        'admin'              => 'Administration',
        'users'              => 'User management',
        'beta'               => 'Beta features',
        'announcements'      => 'Announcements',
        'email-settings'     => 'Email delivery settings',
        'email-deliveries'   => 'Email delivery records',
        'queue-jobs'         => 'Queue jobs',
        'email-templates'    => 'Email templates',
        'operation-audit'    => 'Operation audit',
        'microsoft-settings' => 'Microsoft sign-in settings',
    ],
    'superadminPermissionsHint'   => 'Effective permissions in the current catalog. This role cannot be changed here.',
    'effectivePermissionCount'    => '%d of %d catalog permissions granted',
    'permissionGrantedBy'         => 'Granted by',
    'rawRoleGrants'               => 'Original grants',
    'noEffectivePermissions'      => 'No catalog permissions are currently granted.',
    'saveRolePermissions'         => 'Save permissions',
    'rolePermissionsSaved'        => 'Role permissions saved.',
    'invalidRolePermissions'      => 'Select only available permissions.',
    'wildcardPermissionConflict'  => 'Remove the covering wildcard grant before unchecking one of its permissions.',
    'extraRoleGrant'              => 'Existing grant outside the permission catalog',
    'createRole'                  => 'Create role',
    'editRole'                    => 'Edit role',
    'saveRole'                    => 'Save role',
    'roleUpdated'                 => 'Role updated.',
    'roleKeyImmutable'            => 'This key may be used in authorization checks and cannot be renamed. Create a new role for a different key.',
    'roleKey'                     => 'Role key',
    'roleTitle'                   => 'Display name',
    'roleDescription'             => 'Role description',
    'invalidRoleName'             => 'Use a unique lowercase role key containing letters, digits or hyphens.',
    'roleCreated'                 => 'Role created.',
    'createPermission'            => 'Create permission',
    'editPermission'              => 'Edit permission',
    'savePermission'              => 'Save permission',
    'permissionUpdated'           => 'Permission updated.',
    'permissionKeyImmutable'      => 'This key may be used in authorization checks and cannot be renamed. Create a new permission for a different key.',
    'cancel'                      => 'Cancel',
    'permissionKey'               => 'Permission key',
    'permissionKeyHint'           => 'Use domain.ability (e.g. reports.view). Start each part with a lowercase letter; then use lowercase letters, digits or hyphens. Up to 80 characters; no wildcards.',
    'permissionDescription'       => 'Description',
    'invalidPermissionName'       => 'Use a unique domain.ability key with lowercase letters, digits or hyphens.',
    'permissionCreated'           => 'Permission created.',
    'microsoftLogin'              => 'Microsoft sign-in',
    'microsoftEnabled'            => 'Enable Microsoft sign-in',
    'microsoftEnabledHint'        => 'Allow connected accounts to sign in and other organizational accounts to request approval.',
    'microsoftAppRegistration'    => 'Microsoft Entra app registration',
    'microsoftTenant'             => 'Tenant',
    'microsoftTenantHint'         => 'Use organizations to allow work or school accounts from any organization, or a tenant ID to restrict sign-in to one organization. Personal Microsoft accounts are not supported.',
    'microsoftClientId'           => 'Application (client) ID',
    'microsoftClientIdHint'       => 'Enter the application ID from Microsoft Entra.',
    'microsoftSecretHint'         => 'Set the client secret in the server environment (microsoftoauth.clientSecret). It is not stored here.',
    'microsoftPendingHint'        => 'Register the /en/microsoft/callback redirect URI in your Entra application. Only approved or connected accounts can access this site.',
    'saveMicrosoftSettings'       => 'Save Microsoft settings',
    'microsoftSettingsSaved'      => 'Microsoft settings saved.',
    'microsoftConnect'            => 'Connect Microsoft account',
    'microsoftLinked'             => 'Microsoft account connected',
    'microsoftPasswordInvalid'    => 'Current password is incorrect.',
    'microsoftLoginFailed'        => 'Microsoft sign-in could not be completed.',
    'microsoftApprovalPending'    => 'Access request submitted. Contact an administrator for approval.',
    'microsoftPendingRequests'    => 'Microsoft access requests',
    'microsoftIdentity'           => 'Microsoft identity (email is unverified)',
    'microsoftTargetUser'         => 'Existing admin account',
    'microsoftApprove'            => 'Approve',
    'microsoftConfirmApproval'    => 'Confirm the Microsoft identity belongs to this existing account before approving.',
    'microsoftApprovalFailed'     => 'This request could not be approved.',
    'microsoftApproved'           => 'Microsoft account approved and connected.',
    'microsoftReject'             => 'Reject',
    'microsoftRejected'           => 'Request rejected.',
    'microsoftRevoke'             => 'Revoke',
    'microsoftRevoked'            => 'Microsoft sign-in access revoked. Existing sessions remain active.',
    'microsoftConfirmRevoke'      => 'Revoke this account’s future Microsoft sign-ins? Existing sessions will remain active.',
    'microsoftConnectedAccounts'  => 'Connected Microsoft accounts',
    'microsoftRequestUnavailable' => 'Unable to submit an access request. Contact an administrator.',
    'emailDelivery'               => 'Email delivery',
    'mail'                        => 'Mail',
    'mailDeliveries'              => 'Delivery history',
    'mailTemplates'               => 'Email templates',
    'mailInvitation'              => 'User invitation',
    'mailMagicLink'               => 'Sign-in link',
    'mailActivation'              => 'Account activation',
    'mailEmail2fa'                => 'Email verification code',
    'mailTemplate_magic_linkBody' => <<<'HTML'
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-radius: 6px; border-collapse: separate !important;">
            <tbody>
                <tr>
                    <td style="line-height: 24px; font-size: 16px; border-radius: 6px; margin: 0;" align="center" bgcolor="#0d6efd">
                        <a href="{link}" style="color: #ffffff; font-size: 16px; font-family: Helvetica, Arial, sans-serif; text-decoration: none; border-radius: 6px; line-height: 20px; display: inline-block; font-weight: normal; white-space: nowrap; background-color: #0d6efd; padding: 8px 12px; border: 1px solid #0d6efd;">Sign in</a>
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
        <b>Some information about the person:</b>
        <p>Username: {username}</p>
        <p>IP address: {ipAddress}</p>
        <p>Device: {userAgent}</p>
        <p>Date: {date}</p>
        HTML,
    'mailTemplate_activationBody' => <<<'HTML'
        <p>Your activation code:</p>
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
        <b>Some information about the person:</b>
        <p>Username: {username}</p>
        <p>IP address: {ipAddress}</p>
        <p>Device: {userAgent}</p>
        <p>Date: {date}</p>
        HTML,
    'mailTemplate_email_2faBody' => <<<'HTML'
        <p>Your verification code:</p>
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
        <b>Some information about the person:</b>
        <p>Username: {username}</p>
        <p>IP address: {ipAddress}</p>
        <p>Device: {userAgent}</p>
        <p>Date: {date}</p>
        HTML,
    'mailTemplateSaved'      => 'Email template saved.',
    'mailTemplateReset'      => 'Email template restored to default.',
    'mailTemplateInvalid'    => 'Use only the listed placeholders, keep the required link or code in the message, and do not put links or codes in the subject.',
    'mailTemplateVariables'  => 'Available placeholders',
    'mailSubjectTokens'      => 'Subject placeholders',
    'mailTemplateRestore'    => 'Restore default',
    'mailTemplateSave'       => 'Save template',
    'mailHtmlBody'           => 'HTML message',
    'mailPreview'            => 'Preview',
    'emailQueue'             => 'Email queue and audit',
    'mailAudit'              => 'Delivery log',
    'operationAudit'         => 'Operation audit',
    'auditActor'             => 'Actor',
    'auditTarget'            => 'Object',
    'auditAllActors'         => 'All actors',
    'auditAllObjects'        => 'All objects',
    'auditDeletedUser'       => 'Deleted user',
    'auditBrowser'           => 'Browser',
    'auditType'              => 'Target type',
    'auditAction'            => 'Action',
    'auditSubmission'        => 'Submitted Content',
    'auditOmitted'           => 'Omitted',
    'auditTruncated'         => 'Truncated',
    'auditInvalidInput'      => 'Invalid JSON input',
    'auditLimited'           => 'Submission limit reached',
    'auditPath'              => 'Path',
    'auditResult'            => 'Result',
    'auditFrom'              => 'From',
    'auditTo'                => 'To',
    'audit_success'          => 'Success',
    'audit_failed'           => 'Failed',
    'audit_redirected'       => 'Redirected (outcome unknown)',
    'mailQueueJobs'          => 'Queued jobs',
    'queueJobs'              => 'Queue jobs',
    'queueJobsDescription'   => 'Current queue tasks, not permanent history. Completed tasks may be removed; check the relevant business records for results.',
    'queueJobsUnsupported'   => 'The configured queue backend does not support this view.',
    'queueName'              => 'Queue',
    'queueAll'               => 'All queues',
    'queueStatus_pending'    => 'Pending',
    'queueStatus_reserved'   => 'Reserved',
    'queueStatus_done'       => 'Done',
    'mailRecipient'          => 'Recipient',
    'mailSubject'            => 'Subject',
    'mailStatus'             => 'Status',
    'mailAllStatuses'        => 'All statuses',
    'mailStatus_queued'      => 'Queued',
    'mailStatus_sent'        => 'Sent',
    'mailStatus_failed'      => 'Failed',
    'mailJob_pending'        => 'Pending',
    'mailJob_reserved'       => 'Processing',
    'mailJob_unknown'        => 'Unknown',
    'mailAttempts'           => 'Attempts',
    'mailCreated'            => 'Created',
    'mailProcessed'          => 'Processed',
    'mailAvailable'          => 'Available at',
    'mailFailureReason'      => 'Failure reason',
    'mailNoRecords'          => 'No records found.',
    'senderEmail'            => 'Sender email',
    'senderName'             => 'Sender name',
    'mailProtocol'           => 'Delivery protocol',
    'smtpSettings'           => 'SMTP server',
    'smtpHost'               => 'SMTP host',
    'smtpUser'               => 'SMTP username',
    'smtpPort'               => 'SMTP port',
    'smtpCrypto'             => 'SMTP encryption',
    'smtpNone'               => 'None',
    'smtpTls'                => 'STARTTLS',
    'smtpSsl'                => 'SSL',
    'smtpPasswordHint'       => 'Set the SMTP password in the server environment (email.SMTPPass). It is not stored here.',
    'smtpHostRequired'       => 'Enter an SMTP host when using SMTP.',
    'saveEmailSettings'      => 'Save email settings',
    'emailSettingsSaved'     => 'Email settings saved.',
    'sendTestEmail'          => 'Send test email',
    'sendingTestEmail'       => 'Sending test email',
    'testRecipient'          => 'Recipient email',
    'testEmailSubject'       => 'GeminusAdmin test email',
    'testEmailBody'          => 'This is a test email from GeminusAdmin.',
    'testEmailNotConfigured' => 'Save a sender email before sending a test email.',
    'testEmailSent'          => 'Test email sent.',
    'testEmailFailed'        => 'Could not send the test email. Check the mail server settings.',

    'profileDetails'              => 'Personal details',
    'username'                    => 'Username',
    'email'                       => 'Email',
    'language'                    => 'Language',
    'toggleNavigation'            => 'Toggle navigation',
    'timezone'                    => 'Time zone',
    'saveProfile'                 => 'Save changes',
    'profileSaved'                => 'Profile updated.',
    'usernameTaken'               => 'This username is already in use.',
    'invalidPreference'           => 'Choose a supported language and time zone.',
    'avatar'                      => 'Avatar',
    'chooseAvatar'                => 'Choose an image',
    'avatarHint'                  => 'JPEG, PNG or WebP, up to 2 MB.',
    'uploadAvatar'                => 'Upload avatar',
    'removeAvatar'                => 'Remove avatar',
    'avatarSaved'                 => 'Avatar updated.',
    'attachments'                 => 'Attachments',
    'uploadHistory'               => 'Upload history',
    'uploadHistoryEmpty'          => 'No accessible upload records.',
    'attachmentSource'            => 'Source',
    'attachmentRecord'            => 'Record',
    'attachmentUploadedAt'        => 'Uploaded at',
    'createdFrom'                 => 'Created from (UTC)',
    'createdTo'                   => 'Created through (UTC)',
    'attachmentFile'              => 'File',
    'attachmentSize'              => 'Size',
    'attachmentUpload'            => 'Upload',
    'attachmentDownload'          => 'Download',
    'attachmentPreview'           => 'Preview',
    'attachmentOpen'              => 'Open in new tab',
    'attachmentLoading'           => 'Loading...',
    'attachmentPreviewFailed'     => 'Could not display the attachment.',
    'attachmentRemove'            => 'Remove',
    'attachmentsEmpty'            => 'No attachments.',
    'attachmentHint'              => 'PDF, TXT, CSV, JPEG, PNG or WebP. Maximum {0} MB.',
    'attachmentInvalid'           => 'Select an allowed file of {0} MB or less.',
    'attachmentSaved'             => 'Attachment uploaded.',
    'attachmentRemoved'           => 'Attachment removed.',
    'attachmentFailed'            => 'Could not update attachments. Please try again.',
    'avatarRemoved'               => 'Avatar removed.',
    'avatarFailed'                => 'Could not update avatar. Please try again.',
    'changePassword'              => 'Change password',
    'currentPassword'             => 'Current password',
    'newPassword'                 => 'New password',
    'confirmPassword'             => 'Confirm new password',
    'passwordHint'                => 'Use at least {0} characters (up to 255). Avoid your username, email address, and common passwords.',
    'savePassword'                => 'Update password',
    'passwordSaved'               => 'Password updated.',
    'incorrectPassword'           => 'Current password is incorrect.',
    'localOnly'                   => 'Password changes require a local account.',
    'apiTokens'                   => 'API keys',
    'tokenName'                   => 'Key name',
    'tokenPermissions'            => 'Key permissions',
    'tokenPermissionInvalid'      => 'Select at least one registered permission currently granted to your account.',
    'tokenNoPermissions'          => 'No permissions available.',
    'tokenPermissionActive'       => 'Effective',
    'tokenPermissionDenied'       => 'Account not authorized',
    'tokenPermissionUnregistered' => 'Permission not registered',
    'tokenInvalid'                => 'Key invalid',
    'tokenLegacy'                 => 'Wildcard key: unavailable for API access',
    'tokenExpires'                => 'Expires on (UTC)',
    'lastUsed'                    => 'Last used',
    'createToken'                 => 'Create key',
    'revokeToken'                 => 'Revoke',
    'confirmRevoke'               => 'Revoke this key?',
    'tokenOnce'                   => 'Copy this key now. It will not be shown again.',
    'noTokens'                    => 'No API keys yet.',
    'futureExpiry'                => 'Choose a future expiration date.',
    'tokenNotFound'               => 'Key not found.',
    'tokenRevoked'                => 'Key revoked.',

    'error404'        => 'Not Found',
    'error404Title'   => 'Oops… You just found an error page',
    'error404Message' => 'We are sorry but the page you are looking for was not found',
    'takeMeHome'      => 'Take me home',
];
