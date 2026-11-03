# TeamsExam

TeamsExam 是基于 [GeminusAdmin](https://github.com/yonggang-xiao/geminus.admin) 建设的认证考试管理系统，面向培训班学员的持续认证考试业务。同一应用包含约考管理和考试券管理两个独立业务模块。

业务范围、验收要求及待确认事项见 [TeamsExam 需求说明](docs/teams-exam.md)；其中标为“建议”或“待确认”的内容不是已确定的实现范围。

## 项目定位

- **AI 优先交互，业务核心确定，关键界面保留**：利用 AI 辅助理解材料和表达意图，权限、资源容量、时间冲突、考试券分发及数据一致性由业务系统控制；保留必要的人工操作界面。首版 AI 范围仅为官方确认邮件或凭证的辅助提取，经人工确认后才能保存。
- **内部安排与官方事实分离**：应用管理考试意向、机构资源和官方预约结果；实际预约由学员在官方网站完成，不代替官方预约系统，也不保证官方考位可用。
- **复用底座，独立业务模块**：沿用身份认证、权限、界面组件、导入导出、通知及审计等能力。约考和考试券业务均不加入底座通用 Admin 模块，复用底座或 Entra 身份来源不意味着与底座实例共用业务数据和账号授权。

## 业务范围

以下为已明确的首版范围，具体规则以需求说明为准。

### 约考管理

- 管理员建立考试时段，分别配置机位和人员容量。
- 学员通过机构 Microsoft Entra 账号提交、查看自己的考试时间意向；提交成功即预占资源，审核通过保持占用，拒绝或取消释放资源。
- 学员提交官方预约时间和凭证，管理员核验；官方结果与内部意向分别记录，不一致时不能静默覆盖原安排。
- 服务端检查学员时间冲突和资源容量，并保证并发操作不突破容量、重复操作不重复占用或释放。
- 使用机构已有 Azure 模型服务辅助提取官方确认邮件或凭证；人工核对、修正后的提交仍执行全部业务校验，提取失败不阻断人工录入。

首版不包含课程时间冲突检查、AI 自动排期或自动完成官方预约。改期、异常处理及部分资源规则仍待确认。

### 考试券管理

- 获授权的内部人员独立维护采购批次、考试券和当前接收学员，不要求先创建约考记录。
- 按 CSV 模板批量导入；任一行校验失败时整批不导入，并报告错误行及原因。
- 登记分发和人工核验的使用情况；同一张券不能分配给多人，不支持退回、转让或重新分发。
- 提供库存统计、到期前一个月提醒及操作记录。
- 券码加密存储，完整券码仅供获授权的内部人员按需查看，普通列表、操作记录及导出不包含完整券码。

本模块不向学员提供入口或自助操作。首版不包含采购审批、财务付款、自动核销及与约考记录关联。

## 仓库与分支

- 保留 GitHub Fork 关系，以 GeminusAdmin 底座仓库作为 `upstream`。
- 保留 `main` 分支，仅用于跟踪上游 `main`；在 GitHub 页面上选择 `main` 分支，通过 **Sync fork** 同步该分支的上游更新，不合入 TeamsExam 业务改动。
- `teams-exam` 是业务开发主线和 GitHub 默认分支；上游更新同步到 `main` 后，将 `main` 合并到该分支，处理冲突并验证兼容性。
- 公共仓库的 Fork 仍为公共仓库，业务代码放在独立分支不会使其私有。

## 系统构成

- `admin/app/`：CodeIgniter 4 应用入口，包含路由、配置和通用应用代码；通过自动加载配置接入后台模块。
- `admin/geminus/Admin/`：后台管理模块，包含控制器、视图、组件、语言文件及数据库迁移等，提供仪表盘和后台页面布局。用户认证基于 CodeIgniter Shield。
- `admin/modules/`：独立业务模块目录；`Announcements/` 是[公告业务示例](docs/example-business-module.md)，展示独立的路由、权限、页面和迁移。
- `admin/public/`：Web 入口与静态资源目录，由 FrankenPHP 提供访问。
- `docker/`：本地运行环境，使用 Docker Compose 运行 FrankenPHP 应用、PostgreSQL 数据库、Redis、Adminer 数据库管理工具和 Swagger UI 接口调试工具。

## 底座能力与开发参考

底座提供登录、用户与权限管理、邮件、站内通知、操作审计、通用数据管理、Tabler 界面组件及仪表盘。业务开发优先复用这些能力，不重复建设通用机制，也不改变其原有语义。

- 身份与授权：[Microsoft Entra 登录](docs/microsoft-login.md)、[用户管理](docs/user-management.md)、[角色与权限](docs/roles-and-permissions.md)。成功登录不等于获得管理权限或证明所属培训班；业务模块仍须检查操作权限和记录归属。
- 数据与界面：[通用数据管理](docs/data-management.md)、[通用界面组件](docs/ui-components.md)、[仪表盘](docs/dashboard.md)。底座 CSV 导入允许逐行部分成功，考试券的整批一致性须由业务模块保证。
- 通知与留痕：[邮件发送与模板](docs/email-queue.md)、[通知中心](docs/notifications.md)、[操作审计](docs/operation-audit.md)。业务写操作接入底座审计配置，摘要不包含完整券码或凭证内容。
- 模块与接口：[业务模块示例](docs/example-business-module.md)、[底座 API 说明](docs/api.md)。首版不要求建设通用 API 或独立客户端。

## 快速启动

以下使用 Docker Compose 启动开发环境。部署时应独立配置数据库及应用参数。Azure 模型密钥不得写入仓库，接入前须明确机构隐私及数据处理要求。

需要安装 Docker 和 Docker Compose，并确保本机的 80、443 和 8080 端口未被占用。以下命令均在仓库根目录执行。

1. 复制环境配置文件：

	```sh
	cp admin/env admin/.env
	```

2. 在 `admin/.env` 中取消注释并设置数据库连接（主机名 `db` 是 Docker Compose 中的服务名）：

	```dotenv
	database.default.hostname = db
	database.default.database = teamsexam
	database.default.username = teamsexam
	database.default.password = root
	database.default.DBDriver = Postgre
	database.default.port = 5432
	```

3. 构建镜像，安装依赖后再启动应用及配套开发服务。首次安装使用临时容器，不要求应用容器已运行：

	```sh
	docker compose -f docker/docker-compose.yaml build teamsexam-admin
	docker compose -f docker/docker-compose.yaml run --rm --no-deps teamsexam-admin composer install
	docker compose -f docker/docker-compose.yaml up -d --build
	docker compose -f docker/docker-compose.yaml exec teamsexam-admin php spark migrate --all
	```

4. 创建首个 `superadmin` 账号（将示例邮箱替换为实际邮箱）：

	```sh
	docker compose -f docker/docker-compose.yaml exec teamsexam-admin php spark shield:user create -n admin -e admin@example.com -g superadmin
	```

	按终端提示输入并确认密码。公开注册已关闭；首个账号需要 `superadmin` 组以管理后台设置和其他管理员。此命令创建本地登录账号，不会自动配置 Microsoft 登录。

启动后访问 [https://localhost](https://localhost)，使用刚创建的账号登录。本地 HTTPS 使用自签名证书，浏览器可能提示证书不受信任。数据库管理界面位于 [http://localhost:8080](http://localhost:8080)。停止服务可运行 `docker compose -f docker/docker-compose.yaml down`。

接口调试界面位于 [https://localhost/swagger/](https://localhost/swagger/)，首次使用须生成文档并启动可选的 Swagger UI 容器：

```sh
docker compose -f docker/docker-compose.yaml exec -T teamsexam-admin php spark openapi:generate
docker compose -f docker/docker-compose.yaml up -d swagger-ui
```

接口变更后重新生成文档并刷新页面。在 Swagger UI 的 `Authorize` 中输入个人中心创建的 Access Token，即可调试已声明的接口。详见 [API 说明](docs/api.md#openapi-文档)。Swagger UI 属于 `dev` profile，仅用于本地开发，生产部署不启用该容器及对应代理规则。

## 运行测试

测试必须使用独立的 PostgreSQL 数据库，不要将 `database.tests.database` 指向业务库。启动容器后，在仓库根目录执行一次建库命令：

```sh
docker compose -f docker/docker-compose.yaml exec -T db psql -U teamsexam -d postgres -c 'CREATE DATABASE teamsexam_test OWNER teamsexam'
```

在 `admin/.env` 中添加以下测试连接配置（不要改动上面的 `database.default.*` 业务库配置）：

```dotenv
database.tests.hostname = db
database.tests.database = teamsexam_test
database.tests.username = teamsexam
database.tests.password = root
database.tests.DBDriver = Postgre
database.tests.DBPrefix =
database.tests.charset = utf8
database.tests.port = 5432
```

运行后台测试；测试所需的表由测试流程创建，无需在测试库中手动执行迁移：

```sh
docker compose -f docker/docker-compose.yaml exec -T teamsexam-admin vendor/bin/phpunit --testsuite App --no-coverage
```
