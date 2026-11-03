# 模块开发与底座扩展

本文说明独立业务模块接入底座的通用扩展约定。业务代码归所属模块维护，底座负责共享能力和统一入口，不引用具体业务模块。路由、权限、页面和迁移的完整接入示例见[公告业务模块](example-business-module.md)。

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
