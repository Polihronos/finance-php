<?php

namespace App;

class Request
{
    public static function json(): ?array
    {
        $data = json_decode(file_get_contents('php://input'), true);

        return is_array($data) ? $data : null;
    }
}
