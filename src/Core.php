<?php

namespace Brooke\Porto;

use Brooke\Porto\Facade\Porto;
use Brooke\Porto\Exceptions\ClassDoesNotExistException;
use Brooke\Porto\Exceptions\MissingContainerException;
use think\facade\Log;

class Core
{
    /**
     * 用法例子：BrookePorto::call('Siteapp@SwitchTemplateAction', [ $post['apply_id'], $post['template_id'] ])
     * 通过此架构，可以让代码更简洁，结构更清晰，所有的业务逻辑都写到applicaiton/containers下面，命名需要遵循架构规范
     * 例子中的的路径在 applicaiton/containers/Siteapp/Actions/SwitchTemplateAction.php
     * 其中，Actions文件夹是随意文件夹名称，需要和SwitchTemplateAction最后一个驼峰的单词一致加一个s。本例是Action + s = Actions
     * 详细规范见 https://laravel-china.org/articles/5338/porto-advanced-software-architecture-model#5fc8ba
     */
    public function call($class, $runMethodArguments = [], $extraMethodsToCall = [])
    {
        $class = $this->resolveClass($class);

        $this->callExtraMethods($class, $extraMethodsToCall);

        return $class->run(...$runMethodArguments);
    }

    /**
     * [resolveClass 解析class]
     * @param  [type] $class [例如：Siteapp@SwitchTemplateAction]
     * @return [type]        [返回容器实例]
     */
    private function resolveClass($class)
    {
        if ($this->needsParsing($class)) {
            $parsedClass   = $this->parseClassName($class);
            $containerName = $this->capitalizeFirstLetter($parsedClass[0]);
            $className     = $parsedClass[1];
            BrookePorto::verifyContainerExist($containerName);
            $class = $classFullName = BrookePorto::buildClassFullName($containerName, $className);
            BrookePorto::verifyClassExist($classFullName);
        } else {
            Log::debug('It is recommended to use the apiato caller style (containerName@className) for ' . $class);
        }
        return container()->make($class);
    }

    /**
     * 验证containers是否存在
     * @param  [type] $containerName [description]
     * @return [type]                [description]
     */
    public function verifyContainerExist($containerName)
    {
        if (!is_dir(env('app_path') . 'containers/' . $containerName)) {
            throw new MissingContainerException("Container ($containerName) is not installed.");
        }
    }

    public function verifyClassExist($className)
    {
        if (!class_exists($className)) {
            throw new ClassDoesNotExistException("Class ($className) is not installed.");
        }
    }

    /**
     * build类的完整路径
     */
    public function buildClassFullName($containerName, $className)
    {
        return 'app\containers\\' . $containerName . '\\' . $this->getClassType($className) . 's\\' . $className;
    }

    /**
     * 获取Class类型，通过正则将名称按照大写字母分隔为数组，然后取最后一个，这就要求命名要严格按照规范
     * @param  [type] $className [例如：SwitchTemplateAction]
     * @return [type]            [返回最后一个，例如Action]
     */
    public function getClassType($className)
    {
        $array = preg_split('/(?=[A-Z])/', $className);
        return end($array);
    }

    private function callExtraMethods($class, $extraMethodsToCall)
    {
        foreach ($extraMethodsToCall as $methodInfo) {
            if (is_array($methodInfo)) {
                $this->callWithArguments($class, $methodInfo);
            } else {
                $this->callWithoutArguments($class, $methodInfo);
            }
        }
    }

    private function callWithArguments($class, $methodInfo)
    {
        $method = key($methodInfo);

        $arguments = (array) $methodInfo[$method];
        if (method_exists($class, $method)) {
            $class->$method(...$arguments);
        }
    }

    private function callWithoutArguments($class, $methodInfo)
    {
        if (method_exists($class, $methodInfo)) {
            $class->$methodInfo();
        }
    }

    /**
     * 正则表达式匹配$class里面是否包含@
     * @param  [type] $class     [需要检验的类字符串，一般格式 User@RegisterUserAction]
     * @param  string $separator [分隔符，默认 @ ]
     * @return [type]            [正确返回1，否则返回0]
     */
    private function needsParsing($class, $separator = '@')
    {
        return preg_match('/' . $separator . '/', $class);
    }

    /**
     * 将参数分割为数组
     */
    private function parseClassName($class, $delimiter = '@')
    {
        return explode($delimiter, $class);
    }

    /**
     * 把参数（传递过来的类名）的首字母变大写
     */
    private function capitalizeFirstLetter($string)
    {
        return ucfirst($string);
    }
}
