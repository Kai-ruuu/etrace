<?php

namespace App\Core;

class HttpResponse
{
    public static function ok(array $data)
    {
        Response::json($data);
    }

    public static function unauthorized(array $data = [])
    {
        Response::json(empty($data) ? ['message' => 'Unauthorized.'] : $data, 401);
    }

    public static function unprocessable(array $data = [])
    {
        Response::json(empty($data) ? ['message' => 'Unprocessable.'] : $data, 422);
    }

    public static function forbidden(array $data = [])
    {
        Response::json(empty($data) ? ['message' => 'Forbidden.'] : $data, 403);
    }

    public static function bad(array $data = [])
    {
        Response::json(empty($data) ? ['message' => 'Bad request.'] : $data, 400);
    }

    public static function conflict(array $data = [])
    {
        Response::json(empty($data) ? ['message' => 'Conflict.'] : $data, 409);
    }

    public static function notFound(array $data = [])
    {
        Response::json(empty($data) ? ['message' => 'Conflict.'] : $data, 404);
    }

    public static function server(array $data = [])
    {
        Response::json(empty($data) ? ['message' => 'Internal server error.'] : $data, 500);
    }
}