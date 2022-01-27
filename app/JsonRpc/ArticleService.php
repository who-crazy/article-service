<?php


namespace App\JsonRpc;

use App\Model\ArticleArticleTag;
use Hyperf\DbConnection\Db;
use Hyperf\RpcServer\Annotation\RpcService;
use function Symfony\Component\String\s;
use Taoran\HyperfPackage\Core\AbstractController;
use function Taoran\HyperfPackage\Helpers\set_save_data;

/**
 * @RpcService(name="ArticleService", server="jsonrpc-http", protocol="jsonrpc-http", publishTo="consul")
 */
class ArticleService extends AbstractController implements ArticleServiceInterface
{
    /**
     * 列表
     *
     * @param array $params
     * @return \Hyperf\Contract\LengthAwarePaginatorInterface
     */
    public function getList($params)
    {
        $list = \App\Model\Article::getList(['id', 'title', 'desc', 'cover', 'content_html', 'cat_id', 'is_show'], $params, function ($query) use ($params) {
            //with
            $query->with('articleCategory')->with('articleTags');
            //where
            if (isset($params['title']) && $params['title'] != '') {
                $query->where('title', 'like', "%{$params['title']}%");
            }
            if (isset($params['is_show']) && $params['is_show'] != '') {
                $query->where('is_show', $params['is_show']);
            }
        });

        $list->each(function ($item) {
            $item->cat_name = $item->articleCategory->name ?? '';
            $item->content_html = htmlspecialchars_decode($item->content_html);
            unset($item->articleCategory);
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
        $data = \App\Model\Article::getOne(['id', 'title', 'desc', 'cover', 'content_html', 'cat_id', 'is_show'], function ($query) use ($id) {
            //with
            $query->with('articleCategory')->with('articleTags')->where('id', $id);
        });

        $data->cat_name = $data->articleCategory->name ?? '';
        $data->content_html = htmlspecialchars_decode($data->content_html);
        unset($data->articleCategory);
        return $data;
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
                'content' => '',
                'content_html' => '',
                'cat_id' => 'required|integer',
                'type' => 'integer',
                'tags.*' => ''
            ],
            [
                'title.required' => '标题不能为空！',
                'cat_id.integer' => '分类参数错误！',
                'type.integer' => '类型参数错误！',
            ]
        );

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        try {
            Db::beginTransaction();

            //添加文章
            $model = new \App\Model\Article();
            set_save_data($model, [
                'title' => $params['title'],
                'desc' => $params['desc'],
                'cover' => $params['cover'],
                'content' => $params['content'] ?? '',
                'content_html' => $params['content_html'],
                'cat_id' => $params['cat_id'],
                'is_show' => $params['is_show'] ?? 0,
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
                'cover' => '',
                'cover' => '',
                'content' => '',
                'content_html' => '',
                'cat_id' => 'required|integer',
                'type' => 'integer',
                'tags.*' => ''
            ],
            [
                'title.required' => '标题不能为空！',
                'cat_id.integer' => '分类参数错误！',
                'type.integer' => '类型参数错误！',
            ]
        );

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        $data = \App\Model\Article::getOneById(['*'], $id);
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

            $data = \App\Model\Article::getOneById(['*'], $id);
            $data->is_on = 0;
            $data->save();

            //清除tag绑定关系
            \App\Model\ArticleArticleTag::where('article_ud', $data->id)->delete();

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
    public function setTags($params, $article_id)
    {
        $tags = (isset($params['tags']) && is_array($params['tags'])) ? array_filter($params['tags']) : [];
        //验证
        if (count($tags) != 0) {
            return true;
        }
        //设置标签
        $article_tag_save = [];
        foreach ($tags as $v) {
            $tag = \App\Model\ArticleTag::getOne(['*'], function ($query) use ($v) {
                $query->where('name', $v);
            });

            if ($tag) {
                //已存在，绑定
                $article_tag_save[] = [
                    'article_id' => $article_id,
                    'tag_id' => $tag->id
                ];
            } else {
                //不存在，新增
                $tabObj = new \App\Model\Tag();
                set_save_data($tabObj, [
                    'name' => $v
                ]);
                $tabObj->save();
                //绑定
                $article_tag_save[] = [
                    'article_id' => $article_id,
                    'tag_id' => $tabObj->id
                ];
            }
        }
        //清除tag绑定关系
        \App\Model\ArticleArticleTag::where('article_id', $article_id)->delete();
        //写入tag绑定
        \App\Model\ArticleArticleTag::insert($article_tag_save);
    }
}