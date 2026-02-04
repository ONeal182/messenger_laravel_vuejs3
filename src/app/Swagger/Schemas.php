<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "User",
    description: "Пользователь",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "name", type: "string", nullable: true, example: "John"),
        new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
        new OA\Property(property: "nickname", type: "string", example: "johnny"),
        new OA\Property(property: "last_name", type: "string", nullable: true, example: "Doe"),
        new OA\Property(property: "avatar_path", type: "string", nullable: true, example: "/storage/avatars/1.png"),
        new OA\Property(property: "avatar_thumb_path", type: "string", nullable: true, example: "/storage/avatars/thumbs/1.png"),
    ]
)]
#[OA\Schema(
    schema: "AuthTokenResponse",
    description: "Ответ при успешной аутентификации/регистрации",
    properties: [
        new OA\Property(property: "token", type: "string", example: "1|Z4qz...jwtbearerstring"),
        new OA\Property(property: "user", ref: "#/components/schemas/User"),
    ]
)]
#[OA\Schema(
    schema: "Chat",
    description: "Чат",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 10),
        new OA\Property(property: "title", type: "string", nullable: true, example: "Рабочий чат"),
        new OA\Property(property: "type", type: "string", example: "group"),
        new OA\Property(
            property: "users",
            type: "array",
            items: new OA\Items(ref: "#/components/schemas/User")
        ),
        new OA\Property(property: "unread_count", type: "integer", example: 3),
    ]
)]
#[OA\Schema(
    schema: "Message",
    description: "Сообщение",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 501),
        new OA\Property(property: "body", type: "string", example: "Привет!"),
        new OA\Property(property: "sender_id", type: "integer", example: 1),
        new OA\Property(property: "chat_id", type: "integer", example: 10),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2026-02-04T12:30:00Z"),
        new OA\Property(property: "sender", ref: "#/components/schemas/User"),
    ]
)]
class Schemas
{
    // Набор общих схем для переиспользования в описании API
}
