<?php


namespace App\JsonRpc;

use Hyperf\DbConnection\Db;
use Hyperf\RpcServer\Annotation\RpcService;
use Taoran\HyperfPackage\Core\AbstractController;
use function Taoran\HyperfPackage\Helpers\set_save_data;

/**
 * @RpcService(name="CollectService", server="jsonrpc-http", protocol="jsonrpc-http", publishTo="consul")
 */
class CollectService extends AbstractController implements CollectServiceInterface
{
    /**
     * 列表
     *
     * @param array $params
     * @return \Hyperf\Contract\LengthAwarePaginatorInterface
     */
    public function getList($params)
    {
        $list = \App\Model\Collect::getList(['id', 'title', 'desc', 'cover', 'link', 'cat_id', 'type'], $params, function ($query) use ($params) {
            //with
            $query->with('collectCategory')
                ->with(['collectTags' => function ($_query) {
                    $_query->select(['name']);
                }]);
            //where
            if (isset($params['title']) && $params['title'] != '') {
                $query->where('title', 'like', "%{$params['title']}%");
            }
            if (isset($params['is_show']) && $params['is_show'] != '') {
                $query->where('is_show', $params['is_show']);
            }
            if (isset($params['tags']) && $params['tags'] != '') {
                $query->whereHas(['collectTags', function ($_query) use ($params) {
                    $_query->whereIn('name', $params['tags']);
                }]);
            }
        });

        $list->each(function ($item) {
            $item->cat_name = $item->collectCategory->name ?? '';
            $item->content_html = htmlspecialchars_decode($item->content_html);
            unset($item->collectCategory);
        });
        return $list;
    }

    /**
     * 单条
     *
     * @param int $id
     */
    public function getOne(int $id)
    {
        $data = \App\Model\Collect::getOne(['id', 'title', 'desc', 'cover', 'link', 'content_html', 'cat_id', 'type'], function ($query) use ($id) {
            //with
            $query->with('collectCategory')
                ->with(['collectTags' => function ($_query) {
                    $_query->select(['name']);
                }])
                ->where('id', $id);
        });

        if (!empty($data)) {
            $data->cat_name = $data->collectCategory->name ?? '';
            $data->content_html = htmlspecialchars_decode($data->content_html);
            unset($data->collectCategory);
        }

        return $data ?? [];
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
                'title' => 'required',
                'desc' => '',
                'cover' => '',
                'author' => '',
                'content' => '',
                'content_html' => '',
                //'cat_id' => 'required|integer',
                'user_id' => 'required|integer',
                'type' => 'integer',
                'link' => 'required',
                'tags.*' => ''
            ],
            [
                'title.required' => '标题不能为空！',
                //'cat_id.integer' => '分类参数错误！',
                'user_id.required' => '用户参数错误！',
                'user_id.integer' => '用户参数错误！',
                'type.integer' => '类型参数错误！',
                'link.required' => '链接不能为空！',
            ]
        );

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        try {
            Db::beginTransaction();

            //添加文章
            $model = new \App\Model\Collect();
            set_save_data($model, [
                'title' => $params['title'],
                'desc' => $params['desc'],
                'author' => $params['author'],
                'cover' => $params['cover'],
                'content' => $params['content'] ?? '',
                'content_html' => $params['content_html'],
                //'cat_id' => $params['cat_id'],
                'user_id' => $params['user_id'],
                'link' => $params['link'],
                'type' => $params['type'] ?? 0,
            ])->save();

            //设置标签
            $this->setTags($params, $model->id);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollBack();
            throw new \Exception($e->getMessage());
        }
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
                'title' => 'required',
                'desc' => '',
                'author' => '',
                'cover' => '',
                'content' => '',
                'content_html' => '',
                //'cat_id' => 'required|integer',
                'type' => 'integer',
                'link' => 'required',
                'tags.*' => ''
            ],
            [
                'title.required' => '标题不能为空！',
                //'cat_id.integer' => '分类参数错误！',
                'type.integer' => '类型参数错误！',
                'link.required' => '链接不能为空！',
            ]
        );

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        $data = \App\Model\Collect::getOneById(['*'], $id);
        set_save_data($data, $params)->save();
        //设置标签
        $this->setTags($params, $data->id);
        return true;
    }

    /**
     * 删除
     *
     * @param int $id
     */
    public function destroy(int $id)
    {
        try {
            Db::beginTransaction();

            $data = \App\Model\Collect::getOneById(['*'], $id);
            $data->is_on = 0;
            $data->save();

            //清除tag绑定关系
            \App\Model\CollectCollectTag::where('collect_id', $data->id)->delete();

            Db::commit();
        } catch (\Exception $e) {
            Db::rollBack();
            throw new \Exception($e->getMessage());
        }

        return true;
    }

    /**
     * 设置标签
     *
     * @param $params
     */
    public function setTags($params, $collect_id)
    {
        $tags = (isset($params['tags']) && is_array($params['tags'])) ? array_filter($params['tags']) : [];
        //验证
        if (count($tags) == 0) {
            return true;
        }
        //设置标签
        $tag_save = [];
        foreach ($tags as $v) {
            $tag = \App\Model\CollectTag::getOne(['*'], function ($query) use ($v) {
                $query->where('name', $v);
            });

            if ($tag) {
                //已存在，绑定
                $tag_save[] = [
                    'collect_id' => $collect_id,
                    'tag_id' => $tag->id
                ];
            } else {
                //不存在，新增
                $tabObj = new \App\Model\CollectTag();
                set_save_data($tabObj, [
                    'name' => $v
                ]);
                $tabObj->save();
                //绑定
                $tag_save[] = [
                    'collect_id' => $collect_id,
                    'tag_id' => $tabObj->id
                ];
            }
        }
        //清除tag绑定关系
        \App\Model\CollectCollectTag::where('collect_id', $collect_id)->delete();
        //写入tag绑定
        \App\Model\CollectCollectTag::insert($tag_save);
    }

    /**
     * 获取标签
     *
     * @param $params
     * @return \Hyperf\Contract\LengthAwarePaginatorInterface
     */
    public function getTags($params)
    {
        $tags = \App\Model\CollectTag::getList(['id', 'name'], $params);
        $tags->each(function ($item) {
            $item->num = $item->collects()->count();
        });

        return $tags;
    }

    /**
     * 审核
     *
     * @param $params
     * @return bool
     * @throws \Exception
     */
    public function applyCheck($params)
    {
        //参数验证
        $validator = $this->validationFactory->make(
            $params,
            [
                'status' => 'required|in:0,1,2',
                'collect_id' => 'required|integer',
                'reject_desc' => '',
            ],
            [
                'status.required' => '参数错误！',
                'status.in' => '参数错误！',
                'collect_id.required' => '参数错误！',
                'collect_id.integer' => '参数错误！',
            ]
        );

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        $collect = \App\Model\Collect::getOneById(['*'],$params['collect_id']);
        switch ($params['status']) {
            case 0:
                //设置为未审核
                $collect->status = \App\Model\Collect::STATUS_UNCHECKED;
                break;
            case 1:
                //通过
                $collect->status = \App\Model\Collect::STATUS_ALLOW;
                break;
            case 2:
                //拒绝
                $collect->status = \App\Model\Collect::STATUS_REJECT;
                $collect->reject_desc = $params['reject_desc'] ?? '不合规！';
                break;
        }
        $collect->save();

        return true;
    }

    /**
     *
     * 用户操作更新（点赞，收藏等）
     *
     * @param $params
     */
    public function updateAction($params)
    {
        //参数验证
        $validator = $this->validationFactory->make(
            $params,
            [
                'type' => 'required|integer',
                'user_id' => 'required|integer',
                'collect_id' => 'required|integer',
            ],
            [
                'type.required' => '操作类型错误！',
                'type.integer' => '操作类型错误！',
                'user_id.required' => '用户信息参数错误！',
                'user_id.integer' => '用户信息参数错误！',
                'collect_id.required' => '参数错误！',
                'collect_id.integer' => '参数错误！',
            ]
        );

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        //用户操作更新（点赞，收藏等） - handle
        $this->updateActionHandle($params);

        return true;
    }

    /**
     * 用户操作更新（点赞，收藏等） - handle
     *
     * @param $params
     * @throws \Exception
     */
    private function updateActionHandle($params)
    {
        switch($params['type']) {
            case 1:
                //点赞
                $exists = \App\Model\UserCollectPraise::where('user_id', $params['user_id'])->where('collect_id', $params['collect_id'])->first();
                if (!$exists) {
                    //点赞
                    $exists->user_id = $params['user_id'];
                    $exists->collect_id = $params['collect_id'];
                    $exists->save();
                }

                break;
            case 2:
                //收藏
                $exists = \App\Model\UserCollectEnshrine::where('user_id', $params['user_id'])->where('collect_id', $params['collect_id'])->first();
                if (!$exists) {
                    //添加收藏
                    $exists->user_id = $params['user_id'];
                    $exists->collect_id = $params['collect_id'];
                    $exists->save();
                } else {
                    //取消收藏
                    \App\Model\UserCollectEnshrine::where('user_id', $params['user_id'])->where('collect_id', $params['collect_id'])->delete();
                }
                break;
            default:
                throw new \Exception("参数错误！");
                break;
        }
    }

    /**
     *
     * 用户操作更新（点赞，收藏等）
     *
     * @param $params
     */
    public function comment($params)
    {
        //参数验证
        $validator = $this->validationFactory->make(
            $params,
            [
                'user_id' => 'required|integer',
                'collect_id' => 'required|integer',
                'comment' => 'required'
            ],
            [
                'user_id.required' => '参数错误！',
                'user_id.integer' => '参数错误！',
                'collect_id.required' => '参数错误！',
                'collect_id.integer' => '参数错误！',
                'comment.required' => '请填写内容！',
            ]
        );

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        $collect = \App\Model\UserCollectPraise::where('user_id', $params['user_id'])->where('collect_id', $params['collect_id'])->first();
        if (!$collect) {
            throw new \Exception("数据不存在！");
        }

        $comment = new \App\Model\CollectComment();
        set_save_data($comment, [
            'user_id' => $params['user_id'],
            'collect_id' => $params['collect_id'],
            'comment' => $params['comment'],
        ])->save();


        return true;
    }

    /**
     *
     * 用户操作更新（点赞，收藏等）
     *
     * @param $params
     */
    public function apply($params)
    {
        $this->add($params);

        return true;
    }
}