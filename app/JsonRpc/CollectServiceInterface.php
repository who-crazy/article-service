<?php


namespace App\JsonRpc;


interface CollectServiceInterface
{
    /**
     * 获取列表
     *
     * @param array $params
     * @return mixed
     */
    public function getList(array $params);

    /**获取单挑
     *
     *
     * @param int $id
     * @return mixed
     */
    public function getOne(int $id);

    /**
     * 新增
     *
     * @param $params
     * @return mixed
     */
    public function add($params);

    /**
     * 更新
     *
     * @param int $id
     * @param array $params
     * @return mixed
     */
    public function update(int $id, array $params);

    /**
     * 删除
     *
     * @param int $id
     * @return mixed
     */
    public function destroy(int $id);

    /**
     * 获取标签
     *
     * @param $params
     * @return mixed
     */
    public function getTags($params);

    /**
     * 用户操作 - 点赞，收藏等
     *
     * @param $params
     * @return mixed
     */
    public function updateAction($params);

    /**
     * 评论
     *
     * @param $params
     * @return mixed
     */
    public function comment($params);

    /**
     * 收录申请
     *
     * @param $params
     * @return mixed
     */
    public function apply($params);

    /**
     * 收录申请审核
     *
     * @param $params
     * @return mixed
     */
    public function applyCheck($params);

    public function test($params);
}