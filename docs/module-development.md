# 模块开发与底座扩展

本文说明独立业务模块接入底座的通用扩展约定。业务代码归所属模块维护，底座负责共享能力和统一入口，不引用具体业务模块。路由、权限、页面和迁移的完整接入示例见[公告业务模块](example-business-module.md)。

## 侧栏菜单

模块在自己的 `Config/Registrar.php` 中通过 `AdminMenu()` 返回 `items` 列表，向 [`AdminMenu`](../admin/geminus/Admin/Config/AdminMenu.php) 追加侧栏入口。单层入口继续使用 `permission`、`route`、`label`、`icon` 和 `active`，无需修改已有声明。

需要分组时，父项保留 `permission`、`label`、`icon`，用 `children` 列表代替 `route` 和 `active`：

```php
public static function AdminMenu(): array
{
    return ['items' => [[
        'permission' => ['example.view', 'example.manage'],
        'label'      => 'Example.menu',
        'icon'       => 'ti-folder',
        'children'   => [
            ['route' => 'admin/example', 'label' => 'Example.list', 'active' => '*/admin/example'],
            ['route' => 'admin/example/reports', 'label' => 'Example.reports', 'active' => '*/admin/example/reports*'],
        ],
    ]]];
}
```

模块须提供示例中的命名路由和语言文本。`label` 是语言键，`icon` 是 Tabler 图标类；子项 `route` 使用不需要额外参数的命名路由，`active` 使用 CI4 `url_is()` 路径匹配模式。根据各页面路径合理设置模式，避免多个子项同时匹配。任一子项匹配时，父菜单高亮并展开，匹配的子项高亮；链接由 `route_to()` 生成并保留当前 locale。

父项 `permission` 可以是单个权限字符串或权限列表，列表中任一权限满足即显示父项及其全部子项，子项不单独配置可见权限。菜单可见性不是访问控制，模块仍须为每条业务路由配置适当的认证和权限过滤器；统一显示的子项可能指向当前用户无权访问的路由。

## 模块定时命令

统一调度入口是 [`Geminus\Admin\Config\Tasks`](../admin/geminus/Admin/Config/Tasks.php)，无需创建 `app/Config/Tasks.php`。业务模块负责自己的命令与周期声明，底座负责聚合、声明校验和统一注册。

在 [`admin/app/Config/Autoload.php`](../admin/app/Config/Autoload.php) 中注册模块命名空间，例如将 `Modules\Example` 映射到 `admin/modules/Example`，并保持框架 `Modules` 的自动发现及 `registrars` 开启。模块在自己的 `Config/Registrar.php` 中添加 `Tasks()`（已有 Registrar 时直接追加方法）：

```php
namespace Modules\Example\Config;

class Registrar
{
    public static function Tasks(): array
    {
        return ['commands' => [[
            'name'     => 'example-daily-job',
            'command'  => 'example:run',
            'schedule' => '0 0 * * *',
        ]]];
    }
}
```

示例中的 `example:run` 应由业务模块提供。CodeIgniter 原生 Registrar 对 `commands` 的数值索引列表追加合并，因此多个模块与默认邮件任务同时生效，不需调整 PSR-4 注册顺序，也不扫描模块目录。

**不要通过模块同名的 `Config/Tasks.php` 贡献任务**：`config('Tasks')` 只发现一个配置类，不会聚合多个同名类；应用级 `Config\Tasks` 还会优先于模块配置。如应用确需该配置类，应继承底座 Tasks 并保留其 `init()` 聚合逻辑，不另起一套调度入口。

当前只支持命令声明；`commands` 必须是列表，每项仅包含非空字符串 `name`、`command`、`schedule`。名称在所有模块和默认任务间唯一，不用名称作为数组键。注册任何任务前检查完整列表，错误直接抛出异常，指出列表位置、可用的任务名称及问题字段，不跳过或覆盖。不要在 Registrar 中执行业务操作。

`schedule` 是开发者维护的配置，底座仅检查其为非空字符串，随后原样传给 Tasks 原生 `cron()`，不另行解析或校验表达式合法性，也不引入 cron 验证依赖。

**模块开发约定：** 使用单个空格分隔的五字段数字表达式，依次为分钟（0–59）、小时（0–23）、日（1–31）、月（1–12）、星期（0–6，0 为星期日）。按 Tasks 1.x 支持的方式使用 `*`、数字、数字列表（如 `1,3,5`）、升序数字范围（如 `1-5`）及 `*/n` 正整数步长；不要使用宏（`@daily`）、文本月份/星期、`L/W/#/?`、范围步长或列表与范围混合等扩展。调度时区沿用应用与 Tasks 的原生行为。

上述格式是文档约定，不是注册时的校验保证。模块需在隔离环境中检查 `tasks:list`，并通过测试验证预期触发时间，避免直接执行有副作用的生产任务。非法表达式可能到查看或执行任务时才报错，也可能导致任务不触发；底座不保证注册前拒绝它们。

`php spark tasks:list` 和 `php spark tasks:run` 使用同一聚合入口。默认邮件任务仍为 `send-email`，每分钟执行 `queue:work email --stop-when-empty`；Tasks 原有 `logPerformance` 和 `maxLogsPerTask` 配置保持不变。
