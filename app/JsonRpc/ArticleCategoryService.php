<?php


namespace App\JsonRpc;


use App\Model\ArticleCategory;
use Hyperf\Di\Annotation\Inject;
use Hyperf\RpcServer\Annotation\RpcService;
use Taoran\HyperfPackage\Core\AbstractController;
use Taoran\HyperfPackage\Core\Code;
use Taoran\HyperfPackage\Core\Verify;
use function Taoran\HyperfPackage\Helpers\set_save_data;

/**
 * @RpcService(name="ArticleCategoryService", server="jsonrpc-http", protocol="jsonrpc-http", publishTo="consul")
 */
class ArticleCategoryService extends AbstractController implements ArticleCategoryServiceInterface
{
    /**
     * 列表
     *
     * @param array $params
     * @return \Hyperf\Contract\LengthAwarePaginatorInterface
     */
    public function getList($params)
    {
        $list = \App\Model\ArticleCategory::getList(['id', 'name', 'parent_id'], $params, function ($query) use ($params) {
            if (isset($params['name']) && $params['name'] != '') {
                $query->where('name', 'like', "%{$params['name']}%");
            }
        });

        //分级显示
        if (isset($params['is_tree']) && $params['is_tree'] == 1) {
            $list = $this->tree($list);
        }

        return $list;
    }

    /**
     * 单条
     *
     * @param int $id
     */
    public function getOne(int $id)
    {
        return \App\Model\ArticleCategory::getOneById(['id', 'name', 'parent_id'], $id);
    }

    /**
     * 添加
     */
    public function add($params)
    {
        //参数验证
        $validator = $this->validationFactory->make(
            $params,
            [
                'name' => 'required',
                'parent_id' => 'integer',
                'sort' => 'integer',
            ],
            [
                'name.required' => '名称不能为空！',
                'parent_id.integer' => '参数错误！',
                'sort.integer' => '参数错误！',
            ]
        );

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        $model = new \App\Model\ArticleCategory();
        set_save_data($model, [
            'name' => $params['name'],
            'parent_id' => $params['parent_id'],
            'sort' => $params['sort'],
        ])->save();

        return true;
    }

    /**
     * 更新
     *
     * @param int $id
     */
    public function update(int $id, $params)
    {
        //参数验证
        $validator = $this->validationFactory->make(
            $params,
            [
                'name' => 'required',
                'parent_id' => 'integer',
                'sort' => 'integer',
            ],
            [
                'name.required' => '名称不能为空！',
                'parent_id.integer' => '参数错误！',
                'sort.integer' => '参数错误！',
            ]
        );

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        $articleCategory = \App\Model\ArticleCategory::getOneById(['*'], $id);
        set_save_data($articleCategory, $params)->save();
        return true;
    }

    /**
     * 删除
     *
     * @param int $id
     */
    public function destroy(int $id)
    {
        $articleCategory = \App\Model\ArticleCategory::getOneById(['*'], $id);
        $articleCategory->is_on = 0;
        $articleCategory->save();
        return true;
    }

    /**
     * 分级显示
     *
     * @param $menu
     * @return array
     */
    public function tree($list)
    {
        $new_data = [];
        if (!$list->isEmpty()) {
            $list->each(function ($item) use (&$new_data) {
                if ($item->p_id == 0) {
                    $new_data[$item->id] = $item->toArray();
                } else {
                    $new_data[$item->p_id]['child'][] = $item->toArray();
                }
            });
        }

        return $new_data;
    }
}