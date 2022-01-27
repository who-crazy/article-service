<?php


namespace App\JsonRpc;


interface UserServiceInterface
{
    public function register(array $params);
    public function login(array $params);
}