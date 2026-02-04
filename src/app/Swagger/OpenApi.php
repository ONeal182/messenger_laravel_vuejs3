<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Messenger API",
    description: "1) Получи токен: POST /api/auth/login или /api/auth/register\n2) В Swagger UI нажми Authorize (замок справа) и введи: Bearer <token>\n3) После этого все защищённые запросы будут отправляться с заголовком Authorization."
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: "Базовый URL API"
)]
#[OA\SecurityScheme(
    securityScheme: "BearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Получите токен через /api/auth/login или /api/auth/register и передавайте в заголовке: Authorization: Bearer <token>"
)]
#[OA\SecurityRequirement(name: "BearerAuth")]
class OpenApi
{
    // Класс–контейнер для базовых OpenAPI-аннотаций
}
