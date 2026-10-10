# tpModelFilter

为 ThinkPHP 5–8 模型提供输入驱动的查询过滤器，设计参考 [EloquentFilter](https://github.com/Tucker-Eric/EloquentFilter)。过滤器实现不绑定某个 ORM Builder 类型，查询方法由实际框架查询对象处理。

## 安装

```bash
composer require brianbrian666/tp-model-filter
```

要求 PHP 7.0+。ThinkPHP 5 使用框架自带 ORM；ThinkPHP 6–8 使用项目对应版本的 `topthink/think-orm`。

## 使用

在模型中引入 `Filterable`：

```php
<?php

namespace app\model;

use think\Model;
use tpModelFilter\Filterable;

class Users extends Model
{
    use Filterable;
}
```

默认过滤器位置为 `app\modelFilter\UsersFilter`。将类文件放入项目对应的 PSR-4 目录：

```php
<?php

namespace app\modelFilter;

use tpModelFilter\ModelFilter;

class UsersFilter extends ModelFilter
{
    public function name($value)
    {
        return $this->whereLike('name', $value);
    }

    public function status($value)
    {
        return $this->where('status', $value);
    }
}
```

调用过滤器时，`name` 对应 `name()`，`nick_name` 对应 `nickName()`。查询仍可继续链式调用：

```php
$users = Users::filter($request->param())->order('id', 'desc')->select();
```

过滤器不认识的输入键会被忽略。空字符串、`null` 和空数组默认会被过滤掉。

## 选择过滤器

可在模型中定义默认过滤器：

```php
public function modelFilter()
{
    return $this->provideFilter(\app\modelFilter\AdminUsersFilter::class);
}
```

也可在单次调用时传入过滤器类名或当前过滤器命名空间内的类名；该参数优先级最高：

```php
Users::filter($input, \app\modelFilter\AdminUsersFilter::class);
Users::filter($input, 'AdminUsersFilter');
```

默认命名空间是 `app\modelFilter\`。模型可以覆盖 `modelFilterNamespace()` 来改变它：

```php
protected function modelFilterNamespace()
{
    return '\\app\\filters\\';
}
```

## 输入与执行顺序

如果过滤器定义了 `setup()`，它会在输入过滤方法执行前运行。过滤器可以通过 `$this->input()` 读取全部有效输入，或读取指定键；也可用 `$this->push($key, $value)` 添加供后续处理的输入。

```php
protected function setup()
{
    if ($this->input('only_active', false)) {
        $this->push('status', 1);
    }
}
```

可在过滤器类中设置以下选项：

- `protected $allowedEmptyFilters = true;` 保留空字符串、`null` 和空数组。
- `protected $drop_id = false;` 不将 `user_id` 映射到 `user()`。
- `protected $camel_cased_methods = false;` 不把 `nick_name` 映射为 `nickName()`。
- `protected $blacklist = ['internal'];` 阻止指定方法被输入调用。

黑名单也可在 `setup()` 中用 `blacklistMethod()` 和 `whitelistMethod()` 动态调整。只有过滤器类中声明的公开方法可以被输入触发；查询构造器 API 和 `setup()` 不会作为输入过滤方法执行。

过滤器中可直接调用查询对象的方法，也提供 `whereLike()`、`whereBeginsWith()`、`whereEndsWith()` 三个常用 LIKE 帮助方法。关系过滤请在具体过滤器方法中调用 ThinkPHP 对应版本提供的关系查询 API，以适配项目的 ORM 版本。

## 命令生成器

`FilterCommond` 继承 ThinkPHP 6/7 的 `Make` 命令基类。TP6/7 可按项目 console 命令配置注册 `make:filter`。TP5/TP8 项目可按上述示例手动创建过滤器类。
