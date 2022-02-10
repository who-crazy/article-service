<?php


namespace App\JsonRpc;


class CalcNameService implements CalcNameServiceInterface
{
    public function gen(array $params)
    {

        /**
         *
         * calc name / search
         * 数据库留一份
         * 数据导入es分词使用
         *
         *
         * 提供用户提交收录入口，点赞，收藏，评价
         * 宝宝起名
         * 设置字数，姓氏（人名，地名，技能，药品，道具）
         */

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


    public function dict()
    {
        return [

        ];
    }
}