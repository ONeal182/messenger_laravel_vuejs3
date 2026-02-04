<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

class Ping
{
    #[OA\Get(
        path: "/api/ping",
        summary: "Проверка доступности API",
        description: "Простой endpoint для healthcheck",
        responses: [new OA\Response(response: 200, description: "pong")]
    )]
    public function ping(): void
    {
        // Атрибуты используются только для генерации схемы
    }
}
