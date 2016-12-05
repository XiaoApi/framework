<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\model\relation;

use think\Db;
use think\db\Query;
use think\Loader;
use think\Model;
use think\model\Relation;

class HasOne extends Relation
{
    /**
     * 架构函数
     * @access public
     * @param Model $parent 上级模型对象
     * @param string $model 模型名
     * @param string $foreignKey 关联外键
     * @param string $localKey 关联主键
     * @param array  $alias 别名定义
     * @param string $joinType JOIN类型
     */
    public function __construct(Model $parent, $model, $foreignKey, $localKey, $alias = [], $joinType = 'INNER')
    {
        $this->parent     = $parent;
        $this->model      = $model;
        $this->foreignKey = $foreignKey;
        $this->localKey   = $localKey;
        $this->alias      = $alias;
        $this->joinType   = $joinType;
        $this->query      = (new $model)->db();
    }

    // 动态获取关联数据
    public function getRelation()
    {
        // 执行关联定义方法
        $localKey = $this->localKey;

        // 判断关联类型执行查询
        return $this->query->where($this->foreignKey, $this->parent->$localKey)->find();
    }

    /**
     * 预载入关联查询
     * @access public
     * @param Query     $query 查询对象
     * @param string    $relation 关联名
     * @return void
     */
    public function eagerly(Query $query, $relation, $subRelation, &$closure, $first)
    {
        $name  = Loader::parseName(basename(str_replace('\\', '/', $query->getModel())));
        $alias = isset($this->alias[$name]) ? $this->alias[$name] : $name;
        if ($first) {
            $table = $query->getTable();
            $query->table([$table => $alias]);
            if ($query->getOptions('field')) {
                $field = $query->getOptions('field');
                $query->removeOption('field');
            } else {
                $field = true;
            }
            $query->field($field, false, $table, $alias);

        }

        // 预载入封装
        $joinTable = $this->query->getTable();
        $joinName  = Loader::parseName(basename(str_replace('\\', '/', $this->model)));
        $joinAlias = isset($this->alias[$joinName]) ? $this->alias[$joinName] : $relation;
        $query->via($joinAlias);

        $query->join($joinTable . ' ' . $joinAlias, $alias . '.' . $this->localKey . '=' . $joinAlias . '.' . $this->foreignKey, $this->joinType);

        if ($closure) {
            // 执行闭包查询
            call_user_func_array($closure, [ & $query]);
            //指定获取关联的字段
            //需要在 回调中 调方法 withField 方法，如
            // $query->where(['id'=>1])->withField('id,name');
            if ($query->getOptions('with_field')) {
                $field = $query->getOptions('with_field');
                $query->removeOption('with_field');
            }
            $closure = null;
        } elseif (isset($this->option['field'])) {
            $field = $this->option['field'];
        } else {
            $field = true;
        }
        $query->field($field, false, $joinTable, $joinAlias, $relation . '__');
    }

    /**
     * 预载入关联查询 返回数据集
     * @access public
     * @param array     $resultSet 数据集
     * @param string    $relation 关联名
     * @param string    $class 数据集对象名 为空表示数组
     * @return array
     */
    public function eagerlyResultSet(&$resultSet, $relation)
    {
        foreach ($resultSet as $result) {
            // 模型关联组装
            $this->match($this->model, $relation, $result);
        }
    }

    /**
     * 预载入关联查询 返回模型对象
     * @access public
     * @param Model     $result 数据对象
     * @param string    $relation 关联名
     * @param string    $class 数据集对象名 为空表示数组
     * @return Model
     */
    public function eagerlyResult(&$result, $relation)
    {
        // 模型关联组装
        $this->match($this->model, $relation, $result);
    }

    /**
     * 一对一 关联模型预查询拼装
     * @access public
     * @param string    $model 模型名称
     * @param string    $relation 关联名
     * @param Model     $result 模型对象实例
     * @return void
     */
    protected function match($model, $relation, &$result)
    {
        // 重新组装模型数据
        foreach ($result->getData() as $key => $val) {
            if (strpos($key, '__')) {
                list($name, $attr) = explode('__', $key, 2);
                if ($name == $relation) {
                    $list[$name][$attr] = $val;
                    unset($result->$key);
                }
            }
        }

        $result->setAttr($relation, !isset($list[$relation]) ? null : (new $model($list[$relation]))->isUpdate(true));
    }

    /**
     * 保存（新增）当前关联数据对象
     * @access public
     * @param mixed     $data 数据 可以使用数组 关联模型对象 和 关联对象的主键
     * @return integer
     */
    public function save($data)
    {
        if ($data instanceof Model) {
            $data = $data->getData();
        }
        // 保存关联表数据
        $data[$this->foreignKey] = $this->parent->{$this->localKey};
        $model                   = new $this->model;
        return $model->save($data);
    }
}
