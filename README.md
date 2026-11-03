# GeminusAdmin

GeminusAdmin 是面向 AI 辅助开发、用于构建组织业务管理应用的后台工程底座。它的目标不是预置所有后台功能，而是通过一致的模块约定、安全边界和自动化验证，让业务代码在快速生成后仍易于理解、测试和维护。

- **适合谁**：使用 PHP / CodeIgniter 4 开发组织业务管理应用、需要持续增加业务模块的开发者和团队，尤其是希望借助 AI 编写代码，同时保留明确工程约束与验证手段的团队。
- **能省什么**：复用登录与权限、用户管理、邮件、通知、审计、数据导入导出和附件等基础能力，减少从零搭建、重复接入和设计模块约定的工作，把更多精力放在业务规则上。
- **与普通后台模板的区别**：不只提供后台页面和 UI 组件，还提供可运行的后端基础能力、模块职责与安全约定、业务示例和自动化测试，为 AI 辅助开发提供实现与验证的参照；它不是开箱即用的业务系统，也不通过配置自动生成整页 CRUD。

## 项目定位

- **复用底座，独立建设应用**：不同业务应用可分别部署，独立维护数据库、账号授权和配置；复用代码或身份来源不意味着共用业务数据，也不要求将所有业务放在一个系统中。
- **管理与业务访问并存**：系统用户不等同于管理员。业务模块可基于统一身份与权限能力建设受限的用户自助功能，并负责资源归属、数据范围及业务规则；底座不预置这些业务流程。
- **页面为主，API 按需扩展**：以 CodeIgniter 4 服务端页面和 Tabler 承载日常操作，按业务需要增加系统对接或客户端接口；不以 SPA 或全面 API 化作为前提，也不定位为开箱即用的消费者应用平台。

## 系统构成

- `admin/app/`：CodeIgniter 4 应用入口，包含路由、配置和通用应用代码；通过自动加载配置接入后台模块。
- `admin/geminus/Admin/`：后台管理模块，包含控制器、视图、组件、语言文件及数据库迁移等，提供仪表盘和后台页面布局。用户认证基于 CodeIgniter Shield。
- `admin/modules/Announcements/`：与后台底座并列的[业务模块示例](docs/example-business-module.md)，展示独立的路由、权限、页面和迁移。
- `admin/public/`：Web 入口与静态资源目录，由 FrankenPHP 提供访问。
- `docker/`：本地运行环境，使用 Docker Compose 运行 FrankenPHP 应用、PostgreSQL 数据库、Redis、Adminer 数据库管理工具和 Swagger UI 接口调试工具。

## 工程目标

- 统一业务模块的路由、权限、页面和数据库迁移约定，减少重复设计和实现分歧。
- 将身份认证、资源授权、输入验证等安全要求落实到可复用的实现与测试中，避免只依赖开发提示。
- 提供可参考的业务模块和自动化检查，让 AI 生成的代码有明确范例，并能在合并前发现问题。

## 主要功能

以下为系统当前提供的主要功能，具体能力与使用约束见对应说明：

### 身份与访问

- 登录方式：支持 [Microsoft Entra OIDC 登录](docs/microsoft-login.md)（可复用 Entra 会话），并保留本地账号密码登录。
- 用户与权限：维护用户状态、SSO 账号映射和登录会话，基于角色分配访问权限；支持用户创建，邀请和密码重置仅在启用本地登录时提供。详见 [用户管理说明](docs/user-management.md)和[角色与权限](docs/roles-and-permissions.md)。
- 个人中心：维护个人资料和 API 密钥；仅在启用本地登录时管理登录密码。
- API 访问：使用 Access Token 认证，密钥权限从账号当前权限中显式选择，支持有效期和吊销；提供公告发布接口，每次成功调用均创建新公告，不提供幂等或去重保障。详见 [API 说明](docs/api.md)。

### 系统管理与安全

- 系统设置：配置邮件发送、Microsoft 登录等功能。
- 邮件发送与模板：按邮件类型和语言管理、预览及恢复邀请和认证邮件的 HTML 模板；普通邮件异步投递并记录发送审计，测试邮件直接发送。详见[邮件发送与模板](docs/email-queue.md)。
- 通知中心：Admin 提供通用站内通知，主题切换后的 Tabler 铃铛显示当前用户未读，查看目标页面后标记已读；业务模块决定接收人并接入发送，公告示例展示 CI4 Events 的使用。详见[通知中心](docs/notifications.md)。
- 日志与审计：登录尝试由 Shield 记录，操作审计记录登录后的后台写操作，支持按操作者、对象、时间和结果查询；模块可通过配置增加动作及白名单提交摘要，提供基础开发起点，不追踪实际变更或保证事务一致性。详见 [操作审计说明](docs/operation-audit.md)。
- 安全防护：提供 CSRF 表单防护，以及登录和 API 请求的速率限制。

### 开发与扩展

- 模块开发与扩展：按业务模块注册路由、菜单、权限、页面和数据库迁移；通用接入约定见[模块开发与底座扩展](docs/module-development.md)，具体业务实现见[业务模块示例](docs/example-business-module.md)，并提供权限命名约定、测试样例和部署说明。
- 通用数据管理：Admin 内部提供可复用的关键词、枚举及区间筛选、分页、排序、CSV 逐行导入报告、数据导出和附件上传下载移除，表单验证沿用 CI4；已接入用户管理与头像，公告示例展示业务模块的筛选排序、CSV 导入导出及附件接入。详见[通用数据管理](docs/data-management.md)。
- 通用界面组件：基于 Tabler，使用 CI4 View Cells 复用头像、时区选择器、筛选栏、排序表头、列表空态、分页摘要、日期控件、附件区域和导入报告，纯模板片段保留视图 include；已接入用户管理、个人中心与公告示例。Cell 负责展示，查询、验证、授权和业务写入仍由 Controller / Service 负责，不构建万能表格、表单或配置驱动的整页 CRUD。详见[通用界面组件](docs/ui-components.md)。

### 界面体验

- 仪表盘：按当前用户权限统一展示各模块提供的指标、最近记录及快捷入口，支持模块注册和异常隔离；已接入用户管理、邮件投递、操作审计及公告。详见[仪表盘说明](docs/dashboard.md)。
- 支持多语言和多时区显示。

## 快速启动

需要安装 Docker 和 Docker Compose，并确保本机的 80、443 和 8080 端口未被占用。以下命令均在仓库根目录执行。

1. 复制环境配置文件：

	```sh
	cp admin/env admin/.env
	```

2. 在 `admin/.env` 中取消注释并设置数据库连接（主机名 `db` 是 Docker Compose 中的服务名）：

	```dotenv
	database.default.hostname = db
	database.default.database = geminus
	database.default.username = geminus
	database.default.password = geminus.admin
	database.default.DBDriver = Postgre
	database.default.port = 5432
	```

3. 构建并启动应用及配套开发服务：

	```sh
	docker compose -f docker/docker-compose.yaml up -d --build
	docker compose -f docker/docker-compose.yaml exec geminus-admin composer install
	docker compose -f docker/docker-compose.yaml exec geminus-admin php spark migrate --all
	```

4. 创建首个 `superadmin` 账号（将示例邮箱替换为实际邮箱）：

	```sh
	docker compose -f docker/docker-compose.yaml exec geminus-admin php spark shield:user create -n admin -e admin@example.com -g superadmin
	```

	按终端提示输入并确认密码。公开注册已关闭；首个账号需要 `superadmin` 组以管理后台设置和其他管理员。此命令创建本地登录账号，不会自动配置 Microsoft 登录。

启动后访问 [https://localhost](https://localhost)，使用刚创建的账号登录。本地 HTTPS 使用自签名证书，浏览器可能提示证书不受信任。数据库管理界面位于 [http://localhost:8080](http://localhost:8080)。停止服务可运行 `docker compose -f docker/docker-compose.yaml down`。

接口调试界面位于 [https://localhost/swagger/](https://localhost/swagger/)，首次使用须生成文档并启动可选的 Swagger UI 容器：

```sh
docker compose -f docker/docker-compose.yaml exec -T geminus-admin php spark openapi:generate
docker compose -f docker/docker-compose.yaml up -d swagger-ui
```

接口变更后重新生成文档并刷新页面。在 Swagger UI 的 `Authorize` 中输入个人中心创建的 Access Token，即可调试已声明的接口。详见 [API 说明](docs/api.md#openapi-文档)。Swagger UI 属于 `dev` profile，仅用于本地开发，生产部署不启用该容器及对应代理规则。

## 运行测试

测试必须使用独立的 PostgreSQL 数据库，不要将 `database.tests.database` 指向业务库。启动容器后，在仓库根目录执行一次建库命令：

```sh
docker compose -f docker/docker-compose.yaml exec -T db psql -U geminus -d postgres -c 'CREATE DATABASE geminus_test OWNER geminus'
```

在 `admin/.env` 中添加以下测试连接配置（不要改动上面的 `database.default.*` 业务库配置）：

```dotenv
database.tests.hostname = db
database.tests.database = geminus_test
database.tests.username = geminus
database.tests.password = geminus.admin
database.tests.DBDriver = Postgre
database.tests.DBPrefix =
database.tests.charset = utf8
database.tests.port = 5432
```

运行后台测试；测试所需的表由测试流程创建，无需在测试库中手动执行迁移：

```sh
docker compose -f docker/docker-compose.yaml exec -T geminus-admin vendor/bin/phpunit --testsuite App --no-coverage
```
